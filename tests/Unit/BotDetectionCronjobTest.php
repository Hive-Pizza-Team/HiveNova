<?php

use HiveNova\Core\PlayerUtil;

use PHPUnit\Framework\TestCase;

/**
 * Source-inspection tests for BotDetectionCronjob.
 *
 * No database connection is required. These tests verify that the detection
 * constants, query structure, filter conditions, and message-sending logic
 * are correct and cannot silently regress.
 */
class BotDetectionCronjobTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = file_get_contents(
            __DIR__ . '/../../includes/classes/cronjob/BotDetectionCronjob.php'
        );
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
