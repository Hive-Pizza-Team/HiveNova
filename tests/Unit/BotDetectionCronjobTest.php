<?php

use HiveNova\Core\Universe;
use HiveNova\Cronjob\BotDetectionCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Inline fake that serves BotDetectionCronjob UNION + admin queries.
 */
class BotDetectionFakeDatabase extends FakeDatabase
{
    /** @var list<array{user_id: int, username: string, event_time: int}> */
    public array $botDetectionEvents = [];

    /** @var list<array{id: int}> */
    public array $adminUsers = [];

    public function select($qry, array $params = [])
    {
        if (str_contains($qry, '%%LOG_FLEETS%%')
            && str_contains($qry, '%%LOG_BUILDINGS%%')
            && str_contains($qry, 'event_time')) {
            $cutoff = (int) ($params[':cutoff'] ?? 0);
            $rows = array_values(array_filter(
                $this->botDetectionEvents,
                static fn (array $row): bool => (int) $row['event_time'] >= $cutoff
            ));
            usort(
                $rows,
                static function (array $a, array $b): int {
                    $uidCmp = $a['user_id'] <=> $b['user_id'];
                    return $uidCmp !== 0 ? $uidCmp : $a['event_time'] <=> $b['event_time'];
                }
            );

            return $rows;
        }

        if (str_contains($qry, 'FROM %%USERS%%') && str_contains($qry, 'authlevel')) {
            return $this->adminUsers;
        }

        return parent::select($qry, $params);
    }
}

/**
 * Source-inspection and runtime tests for BotDetectionCronjob.
 */
class BotDetectionCronjobTest extends TestCase
{
    use SwapDatabaseInstance;

    private string $source;

    private BotDetectionFakeDatabase $fake;

    protected function setUp(): void
    {
        $this->source = file_get_contents(
            __DIR__ . '/../../includes/classes/cronjob/BotDetectionCronjob.php'
        );

        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $this->fake = new BotDetectionFakeDatabase();
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
     * @return list<array{user_id: int, username: string, event_time: int}>
     */
    private function seedEvents(int $userId, string $username, int $count, int $gapSeconds, ?int $longGapAfterIndex = null, int $longGapSeconds = 0): array
    {
        $rows = [];
        $time = TIMESTAMP - ($count * $gapSeconds);
        for ($i = 0; $i < $count; $i++) {
            if ($longGapAfterIndex !== null && $i === $longGapAfterIndex + 1) {
                $time += $longGapSeconds;
            } else {
                $time += $gapSeconds;
            }
            $rows[] = [
                'user_id'    => $userId,
                'username'   => $username,
                'event_time' => $time,
            ];
        }
        $this->fake->botDetectionEvents = array_merge($this->fake->botDetectionEvents, $rows);

        return $rows;
    }

    // -----------------------------------------------------------------------
    // Runtime — run()
    // -----------------------------------------------------------------------

    public function test_run_does_nothing_when_no_events(): void
    {
        (new BotDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function test_run_skips_users_with_fewer_than_min_actions(): void
    {
        $this->seedEvents(42, 'CasualPlayer', BotDetectionCronjob::MIN_ACTIONS - 1, 1800);
        $this->fake->adminUsers = [['id' => 99]];

        (new BotDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function test_run_skips_users_with_natural_sleep_break(): void
    {
        $this->seedEvents(
            42,
            'Sleeper',
            BotDetectionCronjob::MIN_ACTIONS,
            1800,
            4,
            BotDetectionCronjob::SLEEP_THRESHOLD
        );
        $this->fake->adminUsers = [['id' => 99]];

        (new BotDetectionCronjob())->run();

        $this->assertSame([], $this->fake->achievement->messages);
    }

    public function test_run_sends_bot_report_to_admins(): void
    {
        $this->seedEvents(42, 'BotSuspect', BotDetectionCronjob::MIN_ACTIONS, 1800);
        $this->fake->adminUsers = [
            ['id' => 99],
            ['id' => 100],
        ];

        (new BotDetectionCronjob())->run();

        $this->assertCount(2, $this->fake->achievement->messages);

        $recipientIds = array_map(
            static fn (array $row): int => (int) $row[':userId'],
            $this->fake->achievement->messages
        );
        $this->assertSame([99, 100], $recipientIds);

        $message = $this->fake->achievement->messages[0];
        $this->assertSame('Bot Detection Report', $message[':subject']);
        $this->assertSame('Game Master', $message[':from']);
        $this->assertSame(1, $message[':unread']);
        $this->assertStringContainsString('BotSuspect', $message[':text']);
        $this->assertStringContainsString('longest break: 0h 30m', $message[':text']);
        $this->assertStringContainsString(
            'no natural sleep break in the last ' . BotDetectionCronjob::DAYS_WINDOW . ' days',
            $message[':text']
        );
    }

    public function test_run_processes_each_available_universe(): void
    {
        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue([1, 2]);

        $this->seedEvents(42, 'UniOneBot', BotDetectionCronjob::MIN_ACTIONS, 1800);
        $this->fake->adminUsers = [['id' => 99]];

        (new BotDetectionCronjob())->run();

        $this->assertCount(2, $this->fake->achievement->messages);
        $this->assertSame(99, $this->fake->achievement->messages[0][':userId']);
        $this->assertSame(99, $this->fake->achievement->messages[1][':userId']);
    }

    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    public function testSleepThresholdIs7200(): void
    {
        $this->assertMatchesRegularExpression(
            '/const\s+SLEEP_THRESHOLD\s*=\s*7200\s*;/',
            $this->source,
            'SLEEP_THRESHOLD must be 7200 (2 hours in seconds)'
        );
    }

    public function testDaysWindowIs7(): void
    {
        $this->assertMatchesRegularExpression(
            '/const\s+DAYS_WINDOW\s*=\s*7\s*;/',
            $this->source,
            'DAYS_WINDOW must be 7'
        );
    }

    public function testMinActionsIs10(): void
    {
        $this->assertMatchesRegularExpression(
            '/const\s+MIN_ACTIONS\s*=\s*10\s*;/',
            $this->source,
            'MIN_ACTIONS must be 10'
        );
    }

    // -----------------------------------------------------------------------
    // Query — event sources
    // -----------------------------------------------------------------------

    public function testQueryIncludesLogFleets(): void
    {
        $this->assertStringContainsString(
            '%%LOG_FLEETS%%',
            $this->source,
            'UNION query must include %%LOG_FLEETS%%'
        );
    }

    public function testQueryIncludesLogBuildings(): void
    {
        $this->assertStringContainsString(
            '%%LOG_BUILDINGS%%',
            $this->source,
            'UNION query must include %%LOG_BUILDINGS%%'
        );
    }

    public function testQueryIncludesLogResearch(): void
    {
        $this->assertStringContainsString(
            '%%LOG_RESEARCH%%',
            $this->source,
            'UNION query must include %%LOG_RESEARCH%%'
        );
    }

    public function testQueryUsesFleetStartTime(): void
    {
        $this->assertStringContainsString(
            'fleet_start_time',
            $this->source,
            'Fleet events must use fleet_start_time as the event timestamp'
        );
    }

    public function testQueryUsesBuildingQueuedAt(): void
    {
        $this->assertMatchesRegularExpression(
            '/%%LOG_BUILDINGS%%.*queued_at/s',
            $this->source,
            '%%LOG_BUILDINGS%% events must use queued_at as the event timestamp'
        );
    }

    public function testQueryUsesResearchQueuedAt(): void
    {
        $this->assertMatchesRegularExpression(
            '/%%LOG_RESEARCH%%.*queued_at/s',
            $this->source,
            '%%LOG_RESEARCH%% events must use queued_at as the event timestamp'
        );
    }

    // -----------------------------------------------------------------------
    // Query — user filters
    // -----------------------------------------------------------------------

    public function testQueryFiltersBannedUsers(): void
    {
        $this->assertStringContainsString(
            'bana = 0',
            $this->source,
            'Query must exclude banned users (bana = 0)'
        );
    }

    public function testQueryFiltersVacationMode(): void
    {
        $this->assertStringContainsString(
            'urlaubs_modus = 0',
            $this->source,
            'Query must exclude players in vacation mode (urlaubs_modus = 0)'
        );
    }

    public function testQueryJoinsUsersTable(): void
    {
        $this->assertStringContainsString(
            '%%USERS%%',
            $this->source,
            'Query must JOIN %%USERS%% to apply ban/vacation filters'
        );
    }

    // -----------------------------------------------------------------------
    // Query — ordering
    // -----------------------------------------------------------------------

    public function testQueryOrdersByUserIdThenEventTime(): void
    {
        $this->assertMatchesRegularExpression(
            '/ORDER BY\s+user_id\s+ASC\s*,\s*event_time\s+ASC/i',
            $this->source,
            'Events must be ordered by user_id ASC, event_time ASC for gap analysis to work'
        );
    }

    // -----------------------------------------------------------------------
    // Gap analysis logic
    // -----------------------------------------------------------------------

    public function testMinActionsGateIsEnforced(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$count\s*<\s*self::MIN_ACTIONS/',
            $this->source,
            'Users with fewer than MIN_ACTIONS events must be skipped'
        );
    }

    public function testSleepThresholdComparisonIsStrictlyLessThan(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$maxGap\s*<\s*self::SLEEP_THRESHOLD/',
            $this->source,
            'A player is flagged only when maxGap is strictly less than SLEEP_THRESHOLD'
        );
    }

    public function testCutoffUsesDaysWindow(): void
    {
        $this->assertMatchesRegularExpression(
            '/TIMESTAMP\s*-\s*\(\s*self::DAYS_WINDOW\s*\*\s*24\s*\*\s*60\s*\*\s*60\s*\)/',
            $this->source,
            'Cutoff timestamp must be calculated from DAYS_WINDOW constant'
        );
    }

    // -----------------------------------------------------------------------
    // Message sending
    // -----------------------------------------------------------------------

    public function testReportsAreSentToAdminsOnly(): void
    {
        $this->assertStringContainsString(
            'AUTH_ADM',
            $this->source,
            'Bot detection reports must be sent to admin-level users (AUTH_ADM)'
        );
        $this->assertStringNotContainsString(
            'AUTH_USR',
            $this->source,
            'Reports must NOT be sent to regular users (AUTH_USR) — use AUTH_ADM until name-and-shame is enabled'
        );
    }

    public function testMessageSubject(): void
    {
        $this->assertStringContainsString(
            'Bot Detection Report',
            $this->source,
            "Message subject must be 'Bot Detection Report'"
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
}
