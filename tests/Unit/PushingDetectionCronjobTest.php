<?php

use HiveNova\Core\Universe;
use HiveNova\Cronjob\PushingDetectionCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Inline fake that serves PushingDetectionCronjob fleet-log + player queries.
 */
class PushingDetectionFakeDatabase extends FakeDatabase
{
    /** @var list<array{fleet_id: int, fleet_owner: int, fleet_end_id: int, fleet_mission: int, start_time: int}> */
    public array $fleetLogs = [];

    /** @var array<int, array{id: int, id_owner: int, universe: int}> */
    public array $planets = [];

    /** @var array<int, array{id: int, username: string}> */
    public array $pushUsers = [];

    /** @var list<array{id: int, universe: int}> */
    public array $universePlayers = [];

    /** @var array<string, array{total_points: int}> key "ownerId:universe" */
    public array $statPoints = [];

    /** @var list<array<string, mixed>> completed marketplace trades */
    public array $tradeRows = [];

    public function select($qry, array $params = [])
    {
        if (str_contains($qry, '%%LOG_FLEETS%%')
            && str_contains($qry, 'fleet_count')
            && str_contains($qry, 'fleet_mission = 3')) {
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

            return array_values(array_filter(
                $this->universePlayers,
                static fn (array $row): bool => (int) $row['universe'] === $universe
            ));
        }

        return parent::select($qry, $params);
    }

    /**
     * Mirrors PushingAccusationQuery::transportPairs(): weaker sender -> stronger
     * planet owner, mission 3, inside the window, counted per pair (no threshold).
     *
     * @return list<array{source_id: int, dest_id: int, source_name: string, dest_name: string, fleet_count: int}>
     */
    private function selectTransportPairs(int $universe, int $cutoff): array
    {
        $pairs = [];

        foreach ($this->fleetLogs as $fl) {
            if ((int) $fl['fleet_mission'] !== 3) {
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
            if ($sp1 === null || $sp2 === null) {
                continue;
            }
            if ($sp1['total_points'] >= $sp2['total_points']) {
                continue;
            }

            $key = "$sourceId:$destId";
            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'source_id'   => $sourceId,
                    'dest_id'     => $destId,
                    'source_name' => $this->pushUsers[$sourceId]['username'] ?? "user$sourceId",
                    'dest_name'   => $this->pushUsers[$destId]['username'] ?? "user$destId",
                    'fleet_ids'   => [],
                ];
            }
            $pairs[$key]['fleet_ids'][(int) $fl['fleet_id']] = true;
        }

        $rows = [];
        foreach ($pairs as $pair) {
            $pair['fleet_count'] = count($pair['fleet_ids']);
            unset($pair['fleet_ids']);
            $rows[] = $pair;
        }

        return $rows;
    }
}

/**
 * Source-inspection and runtime tests for PushingDetectionCronjob.
 */
class PushingDetectionCronjobTest extends TestCase
{
    use SwapDatabaseInstance;

    private string $source;

    private PushingDetectionFakeDatabase $fake;

    protected function setUp(): void
    {
        // The detection SQL lives in PushingAccusationQuery (shared with PvE, salvage and the marketplace).
        $this->source = file_get_contents(
            __DIR__ . '/../../includes/classes/cronjob/PushingDetectionCronjob.php'
        ) . file_get_contents(
            __DIR__ . '/../../includes/classes/PushingAccusationQuery.php'
        );

        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $this->fake = new PushingDetectionFakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue([1]);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue(null);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    /**
     * @param list<int> $playerIds
     */
    private function seedUniversePlayers(int $universe, array $playerIds): void
    {
        foreach ($playerIds as $id) {
            $this->fake->universePlayers[] = [
                'id'       => $id,
                'universe' => $universe,
            ];
        }
    }

    private function seedPushingPair(
        int $universe,
        int $sourceId,
        string $sourceName,
        int $destId,
        string $destName,
        int $planetId,
        int $sourcePoints,
        int $destPoints,
        int $fleetCount,
        ?int $startTime = null
    ): void {
        $startTime ??= TIMESTAMP - 3600;

        $this->fake->pushUsers[$sourceId] = [
            'id'       => $sourceId,
            'username' => $sourceName,
        ];
        $this->fake->pushUsers[$destId] = [
            'id'       => $destId,
            'username' => $destName,
        ];
        $this->fake->planets[$planetId] = [
            'id'       => $planetId,
            'id_owner' => $destId,
            'universe' => $universe,
        ];
        $this->fake->statPoints["$sourceId:$universe"] = ['total_points' => $sourcePoints];
        $this->fake->statPoints["$destId:$universe"] = ['total_points' => $destPoints];

        for ($i = 1; $i <= $fleetCount; $i++) {
            $this->fake->fleetLogs[] = [
                'fleet_id'      => ($sourceId * 1000) + $i,
                'fleet_owner'   => $sourceId,
                'fleet_end_id'  => $planetId,
                'fleet_mission' => 3,
                'start_time'    => $startTime + $i,
            ];
        }
    }

    // -----------------------------------------------------------------------
    // Runtime — run()
    // -----------------------------------------------------------------------

    public function test_run_does_nothing_when_no_pushers(): void
    {
        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function test_run_skips_when_fleet_count_at_threshold(): void
    {
        $this->seedPushingPair(1, 42, 'WeakAttacker', 200, 'StrongVictim', 100, 1000, 50000, 5);
        $this->seedUniversePlayers(1, [42, 200]);

        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function test_run_skips_when_attacker_has_more_points(): void
    {
        $this->seedPushingPair(1, 42, 'StrongAttacker', 200, 'WeakVictim', 100, 50000, 1000, 6);
        $this->seedUniversePlayers(1, [42, 200]);

        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function test_run_sends_warning_to_all_players_in_universe(): void
    {
        $this->seedPushingPair(1, 42, 'PusherOne', 200, 'VictimAlpha', 100, 1000, 50000, 6);
        $this->seedUniversePlayers(1, [42, 200, 300]);

        (new PushingDetectionCronjob())->run();

        $this->assertCount(3, $this->fake->achievement->messages);

        $recipientIds = array_map(
            static fn (array $row): int => (int) $row[':userId'],
            $this->fake->achievement->messages
        );
        $this->assertSame([42, 200, 300], $recipientIds);

        $message = $this->fake->achievement->messages[0];
        $this->assertSame('Pushing Warning', $message[':subject']);
        $this->assertSame('Game Master', $message[':from']);
        $this->assertSame(4, $message[':type']);
        $this->assertSame(1, $message[':unread']);
        $this->assertSame(1, $message[':universe']);
        $this->assertStringContainsString('VictimAlpha', $message[':text']);
        $this->assertStringContainsString('1 player is pushing to VictimAlpha', $message[':text']);
        $this->assertStringContainsString(
            'Suspicious attack patterns have been detected in this universe:',
            $message[':text']
        );
    }

    public function test_run_uses_plural_line_when_multiple_sources_push(): void
    {
        $this->seedPushingPair(1, 42, 'PusherOne', 200, 'VictimAlpha', 100, 1000, 50000, 6);
        $this->seedPushingPair(1, 43, 'PusherTwo', 200, 'VictimAlpha', 101, 2000, 50000, 7, TIMESTAMP - 7200);
        $this->fake->planets[101] = [
            'id'       => 101,
            'id_owner' => 200,
            'universe' => 1,
        ];
        $this->seedUniversePlayers(1, [42]);

        (new PushingDetectionCronjob())->run();

        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertStringContainsString(
            '2 player(s) are pushing to VictimAlpha',
            $this->fake->achievement->messages[0][':text']
        );
    }

    public function test_run_processes_each_available_universe(): void
    {
        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue([1, 2]);

        $this->seedPushingPair(1, 42, 'UniOnePusher', 200, 'UniOneVictim', 100, 1000, 50000, 6);
        $this->seedUniversePlayers(1, [42, 200]);
        $this->seedUniversePlayers(2, [500]);

        (new PushingDetectionCronjob())->run();

        $this->assertCount(2, $this->fake->achievement->messages);
        $this->assertSame(42, $this->fake->achievement->messages[0][':userId']);
        $this->assertSame(200, $this->fake->achievement->messages[1][':userId']);
    }

    // -----------------------------------------------------------------------
    // Query — detection criteria
    // -----------------------------------------------------------------------

    public function testQueryUsesAttackMission(): void
    {
        $this->assertStringContainsString(
            'fleet_mission = 3',
            $this->source,
            'Query must filter for attack missions (fleet_mission = 3)'
        );
    }

    public function testQueryExcludesSelfAttacks(): void
    {
        $this->assertStringContainsString(
            'fleet_owner != p.id_owner',
            $this->source,
            'Query must exclude attacks where fleet owner owns the target planet'
        );
    }

    public function testQueryRequiresWeakerAttacker(): void
    {
        $this->assertStringContainsString(
            'sp1.total_points < sp2.total_points',
            $this->source,
            'Query must only flag weaker players attacking stronger targets'
        );
    }

    public function testQueryRequiresMoreThanFiveFleets(): void
    {
        $this->assertSame(5, \HiveNova\Core\MarketAbuseRules::PUSH_MIN_EVENTS_EXCLUSIVE);
        $this->assertStringContainsString('MarketAbuseRules::mergePushPairs', $this->source);
    }

    public function testQueryUsesFourteenDayWindow(): void
    {
        $this->assertSame(14 * 24 * 60 * 60, \HiveNova\Core\MarketAbuseRules::PUSH_WINDOW_SECONDS);
        $this->assertStringContainsString('MarketAbuseRules::PUSH_WINDOW_SECONDS', $this->source);
    }

    public function testQueryOnlyJoinsPlayerStatRows(): void
    {
        $this->assertStringContainsString('sp1.stat_type = 1', $this->source);
        $this->assertStringContainsString('sp2.stat_type = 1', $this->source);
    }

    public function testQueryExcludesMarketplaceLegsFromTransports(): void
    {
        $this->assertMatchesRegularExpression(
            '/NOT EXISTS\s*\(\s*SELECT 1 FROM %%TRADES%%/i',
            $this->source
        );
    }

    public function testQueryJoinsLogFleetsPlanetsUsersAndStatpoints(): void
    {
        $this->assertStringContainsString('%%LOG_FLEETS%%', $this->source);
        $this->assertStringContainsString('%%PLANETS%%', $this->source);
        $this->assertStringContainsString('%%USERS%%', $this->source);
        $this->assertStringContainsString('%%STATPOINTS%%', $this->source);
    }

    public function testQueryGroupsByDestination(): void
    {
        $rows = PushingDetectionCronjob::byDestination([
            ['source_id' => 1, 'dest_id' => 9, 'source_name' => 'a', 'dest_name' => 'Nine'],
            ['source_id' => 2, 'dest_id' => 9, 'source_name' => 'b', 'dest_name' => 'Nine'],
            ['source_id' => 3, 'dest_id' => 7, 'source_name' => 'c', 'dest_name' => 'Seven'],
        ]);
        $this->assertSame([
            ['destination' => 'Nine', 'dest_id' => 9, 'source_count' => 2],
            ['destination' => 'Seven', 'dest_id' => 7, 'source_count' => 1],
        ], $rows);
    }

    public function test_run_counts_bad_marketplace_trades_toward_the_threshold(): void
    {
        // 3 transports + 3 lopsided trades from the same weaker seller = 6 > 5.
        $this->seedPushingPair(1, 42, 'AltSeller', 200, 'MainBuyer', 100, 1000, 50000, 3);
        $this->seedUniversePlayers(1, [42, 200]);
        for ($i = 0; $i < 3; $i++) {
            $this->fake->tradeRows[] = [
                'seller_id' => 42, 'buyer_id' => 200,
                'seller_name' => 'AltSeller', 'buyer_name' => 'MainBuyer',
                'seller_points' => 1000, 'buyer_points' => 50000,
                'transaction_type' => 0, 'ex_resource_type' => 1, 'ex_resource_amount' => 100000,
                'fleet_array' => '203,10', 'fleet_resource_metal' => 0,
                'fleet_resource_crystal' => 100000, 'fleet_resource_deuterium' => 0,
            ];
        }

        (new PushingDetectionCronjob())->run();

        $this->assertCount(2, $this->fake->achievement->messages);
        $this->assertStringContainsString('1 player is pushing to MainBuyer', $this->fake->achievement->messages[0][':text']);
    }

    public function test_run_ignores_fair_marketplace_trades(): void
    {
        $this->seedPushingPair(1, 42, 'AltSeller', 200, 'MainBuyer', 100, 1000, 50000, 3);
        $this->seedUniversePlayers(1, [42, 200]);
        for ($i = 0; $i < 3; $i++) {
            // 50k crystal (25k units) for 100k metal (25k units): fair.
            $this->fake->tradeRows[] = [
                'seller_id' => 42, 'buyer_id' => 200,
                'seller_name' => 'AltSeller', 'buyer_name' => 'MainBuyer',
                'seller_points' => 1000, 'buyer_points' => 50000,
                'transaction_type' => 0, 'ex_resource_type' => 1, 'ex_resource_amount' => 100000,
                'fleet_array' => '203,10', 'fleet_resource_metal' => 0,
                'fleet_resource_crystal' => 50000, 'fleet_resource_deuterium' => 0,
            ];
        }

        (new PushingDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    // -----------------------------------------------------------------------
    // Message sending
    // -----------------------------------------------------------------------

    public function testWarningsAreSentToAllUniversePlayers(): void
    {
        $this->assertStringContainsString(
            'SELECT id FROM %%USERS%% WHERE universe = :universe',
            $this->source,
            'Warnings must be broadcast to every player in the universe'
        );
    }

    public function testMessageSubject(): void
    {
        $this->assertStringContainsString(
            'Pushing Warning',
            $this->source,
            "Message subject must be 'Pushing Warning'"
        );
    }

    public function testMessageSenderIsGameMaster(): void
    {
        $this->assertStringContainsString(
            'Game Master',
            $this->source,
            "Message sender name must be 'Game Master'"
        );
    }

    public function testSendsMessageViaPlayerUtil(): void
    {
        $this->assertStringContainsString(
            'PlayerUtil::sendMessage(',
            $this->source,
            'Messages must be sent via PlayerUtil::sendMessage()'
        );
    }

    public function testMessageUnreadFlagIsSet(): void
    {
        $this->assertMatchesRegularExpression(
            '/PlayerUtil::sendMessage\([^;]+,\s*1\s*,\s*\$uni\s*\)/s',
            $this->source,
            'sendMessage must pass unread=1 as the second-to-last argument'
        );
    }

    public function testSingularPusherLineFormat(): void
    {
        $this->assertStringContainsString(
            "1 player is pushing to '",
            $this->source,
            'Single-source destinations must use singular wording'
        );
    }

    public function testPluralPusherLineFormat(): void
    {
        $this->assertStringContainsString(
            "player(s) are pushing to '",
            $this->source,
            'Multi-source destinations must use plural wording'
        );
    }
}
