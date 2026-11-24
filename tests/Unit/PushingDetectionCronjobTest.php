<?php

use HiveNova\Core\PushingAccusationQuery;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\PushingDetectionCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Inline fake that serves the shared pushing query (transport totals + trades)
 * and the reviewer lookup.
 */
class PushingDetectionFakeDatabase extends FakeDatabase
{
    /** @var list<array{fleet_id: int, fleet_owner: int, fleet_end_id: int, fleet_mission: int, start_time: int, fleet_state?: int, metal?: int, crystal?: int, deuterium?: int}> */
    public array $fleetLogs = [];

    /** @var array<int, array{id: int, id_owner: int, universe: int}> */
    public array $planets = [];

    /** @var array<int, array{id: int, username: string}> */
    public array $pushUsers = [];

    /** @var list<array{id: int, universe: int, authlevel: int}> */
    public array $universePlayers = [];

    /** @var array<string, array{total_points: int}> key "ownerId:universe" */
    public array $statPoints = [];

    /** @var list<array<string, mixed>> completed marketplace trades */
    public array $tradeRows = [];

    public int $transportQueries = 0;

    public function select($qry, array $params = [])
    {
        if (str_contains($qry, '%%LOG_FLEETS%%')
            && str_contains($qry, 'fleet_count')
            && str_contains($qry, 'fleet_mission = 3')) {
            $this->transportQueries++;
            return $this->selectTransportPairs(
                (int) ($params[':universe'] ?? 0),
                (int) ($params[':cutoff'] ?? 0)
            );
        }

        if (str_contains($qry, '%%TRADES%%') && str_contains($qry, 'seller_points')) {
            return $this->tradeRows;
        }

        if (str_contains($qry, 'FROM %%USERS%%')
            && str_contains($qry, 'WHERE universe = :universe')) {
            $universe = (int) ($params[':universe'] ?? 0);
            $min = (int) ($params[':authlevel'] ?? PHP_INT_MIN);

            return array_values(array_map(
                static fn (array $row): array => ['id' => $row['id']],
                array_filter(
                    $this->universePlayers,
                    static fn (array $row): bool => (int) $row['universe'] === $universe && (int) ($row['authlevel'] ?? 0) >= $min
                )
            ));
        }

        return parent::select($qry, $params);
    }

    /**
     * Mirrors PushingAccusationQuery::transportPairs(): weaker sender -> stronger
     * planet owner, mission 3, not recalled, inside the window, summed per pair.
     *
     * @return list<array<string, mixed>>
     */
    private function selectTransportPairs(int $universe, int $cutoff): array
    {
        $pairs = [];

        foreach ($this->fleetLogs as $fl) {
            if ((int) $fl['fleet_mission'] !== 3 || (int) ($fl['fleet_state'] ?? 0) === 2) {
                continue;
            }
            if ((int) $fl['start_time'] <= $cutoff) {
                continue;
            }

            $planet = $this->planets[(int) $fl['fleet_end_id']] ?? null;
            if ($planet === null || (int) $planet['universe'] !== $universe) {
                continue;
            }
            if ((int) $fl['fleet_owner'] === (int) $planet['id_owner']) {
                continue;
            }

            $sourceId = (int) $fl['fleet_owner'];
            $destId = (int) $planet['id_owner'];
            $sp1 = $this->statPoints["$sourceId:$universe"] ?? null;
            $sp2 = $this->statPoints["$destId:$universe"] ?? null;
            if ($sp1 === null || $sp2 === null || $sp1['total_points'] >= $sp2['total_points']) {
                continue;
            }

            $key = "$sourceId:$destId";
            $pairs[$key] ??= [
                'source_id'   => $sourceId,
                'dest_id'     => $destId,
                'source_name' => $this->pushUsers[$sourceId]['username'] ?? "user$sourceId",
                'dest_name'   => $this->pushUsers[$destId]['username'] ?? "user$destId",
                'fleet_count' => 0,
                'metal' => 0, 'crystal' => 0, 'deuterium' => 0,
            ];
            $pairs[$key]['fleet_count']++;
            $pairs[$key]['metal'] += (int) ($fl['metal'] ?? 0);
            $pairs[$key]['crystal'] += (int) ($fl['crystal'] ?? 0);
            $pairs[$key]['deuterium'] += (int) ($fl['deuterium'] ?? 0);
        }

        return array_values($pairs);
    }
}

class PushingDetectionCronjobTest extends TestCase
{
    use SwapDatabaseInstance;

    private string $source;

    private PushingDetectionFakeDatabase $fake;

    private int $nextFleetId = 1;

    protected function setUp(): void
    {
        $this->source = file_get_contents(__DIR__ . '/../../includes/classes/cronjob/PushingDetectionCronjob.php')
            . file_get_contents(__DIR__ . '/../../includes/classes/PushingAccusationQuery.php');

        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $this->fake = new PushingDetectionFakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue(null, [1]);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function player(int $id, string $name, int $points, int $authlevel = 0, int $universe = 1): void
    {
        $this->fake->pushUsers[$id] = ['id' => $id, 'username' => $name];
        $this->fake->statPoints["$id:$universe"] = ['total_points' => $points];
        $this->fake->planets[$id] = ['id' => $id, 'id_owner' => $id, 'universe' => $universe];
        $this->fake->universePlayers[] = ['id' => $id, 'universe' => $universe, 'authlevel' => $authlevel];
    }

    /** Transports carrying $metalEach metal from $from to $to's planet. */
    private function transports(int $from, int $to, int $count, int $metalEach, int $state = 0): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->fake->fleetLogs[] = [
                'fleet_id' => $this->nextFleetId++, 'fleet_owner' => $from, 'fleet_end_id' => $to,
                'fleet_mission' => 3, 'start_time' => TIMESTAMP - 3600, 'fleet_state' => $state,
                'metal' => $metalEach,
            ];
        }
    }

    /** @return list<int> */
    private function recipients(): array
    {
        return array_map(static fn (array $m): int => (int) $m[':userId'], $this->fake->achievement->messages);
    }

    public function testNoMailWithoutFlaggedReceivers(): void
    {
        $this->player(1, 'Admin', 10, AUTH_ADM);
        (new PushingDetectionCronjob())->run();
        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function testManySmallTripsUnderTheValueLimitAreNotFlagged(): void
    {
        // 20 trips is far over the old ">5 fleets" rule, but only 20 x 1k metal = 5k units.
        $this->player(1, 'Admin', 10, AUTH_ADM);
        $this->player(42, 'Small', 1000);
        $this->player(200, 'Big', 50000);
        $this->transports(42, 200, 20, 1000);

        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function testOneLargeTripOverTheValueLimitIsFlaggedAndMailedToStaffOnly(): void
    {
        $this->player(1, 'Admin', 10, AUTH_ADM);
        $this->player(2, 'Operator', 10, AUTH_OPS);
        $this->player(3, 'Moderator', 10, AUTH_MOD);
        $this->player(4, 'Promo', 10, AUTH_PROMO);
        $this->player(42, 'AltOne', 1000);
        $this->player(200, 'MainAcc', 50000);
        $this->transports(42, 200, 1, 1200000); // 300k units >= 250k

        (new PushingDetectionCronjob())->run();

        $this->assertSame([1, 2, 3], $this->recipients());
        $message = $this->fake->achievement->messages[0];
        $this->assertSame('Pushing review', $message[':subject']);
        $this->assertSame('Game Master', $message[':from']);
        $this->assertSame(1, $message[':universe']);
        $text = $message[':text'];
        $this->assertStringContainsString('MainAcc (id 200): received 300.000 from 1 weaker account(s) [single source over limit]', $text);
        $this->assertStringContainsString('AltOne (id 42): 1 transport(s), 0 trade(s), 300.000 (over limit)', $text);
        $this->assertStringContainsString(gmdate('Y-m-d H:i', TIMESTAMP - 14 * 24 * 3600), $text);
        $this->assertStringNotContainsString('attack', strtolower($text));
    }

    public function testRegularPlayersAreNeverMailed(): void
    {
        $this->player(42, 'AltOne', 1000);
        $this->player(200, 'MainAcc', 50000);
        $this->transports(42, 200, 1, 2000000);

        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function testFunnelOfSourcesEachUnderThePairLimitIsFlagged(): void
    {
        $this->player(1, 'Admin', 10, AUTH_ADM);
        $this->player(200, 'MainAcc', 50000);
        foreach ([41, 42, 43] as $alt) {
            $this->player($alt, 'Alt' . $alt, 1000);
            $this->transports($alt, 200, 2, 400000); // 200k units each, under 250k
        }

        (new PushingDetectionCronjob())->run();

        $this->assertSame([1], $this->recipients());
        $text = $this->fake->achievement->messages[0][':text'];
        $this->assertStringContainsString('MainAcc (id 200): received 600.000 from 3 weaker account(s) [many-to-one funnel]', $text);
        $this->assertStringContainsString('Alt41 (id 41): 2 transport(s), 0 trade(s), 200.000', $text);
    }

    public function testRecalledTransportsAndStrongerSendersDoNotCount(): void
    {
        $this->player(1, 'Admin', 10, AUTH_ADM);
        $this->player(42, 'Small', 1000);
        $this->player(200, 'Big', 50000);
        $this->transports(42, 200, 1, 2000000, 2); // recalled
        $this->transports(200, 42, 1, 2000000);    // stronger -> weaker

        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function testLopsidedTradesAddTheirSurplus(): void
    {
        $this->player(1, 'Admin', 10, AUTH_ADM);
        $this->player(42, 'AltSeller', 1000);
        $this->player(200, 'MainBuyer', 50000);
        $this->transports(42, 200, 1, 400000); // 100k units
        for ($i = 0; $i < 2; $i++) {
            // gives 200k crystal (100k units) for 100k metal (25k units): surplus 75k units
            $this->fake->tradeRows[] = [
                'seller_id' => 42, 'buyer_id' => 200, 'seller_name' => 'AltSeller', 'buyer_name' => 'MainBuyer',
                'seller_points' => 1000, 'buyer_points' => 50000,
                'transaction_type' => 0, 'ex_resource_type' => 1, 'ex_resource_amount' => 100000,
                'fleet_array' => '203,10', 'fleet_resource_metal' => 0, 'fleet_resource_crystal' => 200000, 'fleet_resource_deuterium' => 0,
            ];
        }

        (new PushingDetectionCronjob())->run();

        $this->assertSame([1], $this->recipients());
        $this->assertStringContainsString('AltSeller (id 42): 1 transport(s), 2 trade(s), 250.000 (over limit)', $this->fake->achievement->messages[0][':text']);
    }

    public function testEachUniverseIsReviewedSeparately(): void
    {
        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue(null, [1, 2]);

        $this->player(1, 'Admin1', 10, AUTH_ADM, 1);
        $this->player(5, 'Admin2', 10, AUTH_ADM, 2);
        $this->player(42, 'AltOne', 1000, 0, 1);
        $this->player(200, 'MainAcc', 50000, 0, 1);
        $this->transports(42, 200, 1, 2000000);

        (new PushingDetectionCronjob())->run();

        $this->assertSame([1], $this->recipients());
    }

    public function testCronAndAccusationQueryShareOneCachedReport(): void
    {
        $this->player(1, 'Admin', 10, AUTH_ADM);
        $this->player(42, 'AltOne', 1000);
        $this->player(200, 'MainAcc', 50000);
        $this->transports(42, 200, 1, 2000000);

        (new PushingDetectionCronjob())->run();
        $this->assertSame([200], PushingAccusationQuery::accusedReceiverIds(1, TIMESTAMP));
        $this->assertTrue(PushingAccusationQuery::isAccusedReceiver(200, 1, TIMESTAMP));
        $this->assertTrue(PushingAccusationQuery::isAccusedReceiver(200, 1));
        $this->assertSame(1, $this->fake->transportQueries);
    }

    public function testSharedQueryFiltersPlayersMarketLegsAndRecalls(): void
    {
        $this->assertStringContainsString('sp1.stat_type = 1', $this->source);
        $this->assertStringContainsString('fl.fleet_state != 2', $this->source);
        $this->assertMatchesRegularExpression('/NOT EXISTS\s*\(\s*SELECT 1 FROM %%TRADES%%/i', $this->source);
        $this->assertStringContainsString('PushingAccusationQuery::report(', $this->source);
        $this->assertStringNotContainsString('Suspicious attack patterns', $this->source);
    }
}
