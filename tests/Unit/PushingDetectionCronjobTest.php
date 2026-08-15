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

    public function select($qry, array $params = [])
    {
        if (str_contains($qry, '%%LOG_FLEETS%%')
            && str_contains($qry, 'source_count')
            && str_contains($qry, 'fleet_mission = 3')) {
            return $this->selectPushers(
                (int) ($params[':universe'] ?? 0),
                (int) ($params[':cutoff'] ?? 0)
            );
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
     * @return list<array{destination: string, dest_id: int, source_count: int}>
     */
    private function selectPushers(int $universe, int $cutoff): array
    {
        /** @var array<string, array{source: string, destination: string, dest_id: int, fleet_ids: array<int, true>}> */
        $innerRows = [];

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

            $sourceName = $this->pushUsers[$sourceId]['username'] ?? "user$sourceId";
            $destName = $this->pushUsers[$destId]['username'] ?? "user$destId";
            $key = "$sourceId:$destId";

            if (!isset($innerRows[$key])) {
                $innerRows[$key] = [
                    'source'      => $sourceName,
                    'destination' => $destName,
                    'dest_id'     => $destId,
                    'fleet_ids'   => [],
                ];
            }
            $innerRows[$key]['fleet_ids'][(int) $fl['fleet_id']] = true;
        }

        /** @var array<string, array{destination: string, dest_id: int, sources: array<string, true>}> */
        $byDest = [];
        foreach ($innerRows as $row) {
            if (count($row['fleet_ids']) <= 5) {
                continue;
            }

            $dkey = $row['destination'] . ':' . $row['dest_id'];
            if (!isset($byDest[$dkey])) {
                $byDest[$dkey] = [
                    'destination' => $row['destination'],
                    'dest_id'     => $row['dest_id'],
                    'sources'     => [],
                ];
            }
            $byDest[$dkey]['sources'][$row['source']] = true;
        }

        $result = [];
        foreach ($byDest as $row) {
            $result[] = [
                'destination'  => $row['destination'],
                'dest_id'      => $row['dest_id'],
                'source_count' => count($row['sources']),
            ];
        }

        usort(
            $result,
            static function (array $a, array $b): int {
                $cmp = $b['source_count'] <=> $a['source_count'];
                return $cmp !== 0 ? $cmp : strcmp($b['destination'], $a['destination']);
            }
        );

        return $result;
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
        $this->source = file_get_contents(
            __DIR__ . '/../../includes/classes/cronjob/PushingDetectionCronjob.php'
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
        $this->assertMatchesRegularExpression(
            '/HAVING\s+COUNT\s*\(\s*DISTINCT\s+fl\.fleet_id\s*\)\s*>\s*5/i',
            $this->source,
            'Inner query must require more than 5 distinct fleet attacks per source-destination pair'
        );
    }

    public function testQueryUsesFourteenDayWindow(): void
    {
        $this->assertMatchesRegularExpression(
            '/TIMESTAMP\s*-\s*\(\s*14\s*\*\s*24\s*\*\s*60\s*\*\s*60\s*\)/',
            $this->source,
            'Cutoff timestamp must use a 14-day lookback window'
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
        $this->assertMatchesRegularExpression(
            '/GROUP BY\s+destination\s*,\s*dest_id/i',
            $this->source,
            'Outer query must aggregate pushers by destination player'
        );
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
