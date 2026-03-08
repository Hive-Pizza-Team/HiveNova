<?php

use PHPUnit\Framework\TestCase;

/**
 * Source-inspection tests for CleanerCronjob.
 *
 * These tests do not connect to a real database; they verify the shape of the
 * cleanup logic so regressions (e.g. re-commenting the raport delete) are
 * caught in CI.
 */
class CleanerCronjobTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $file = __DIR__ . '/../../includes/classes/cronjob/CleanerCronjob.class.php';
        $this->source = file_get_contents($file);
    }

    public function testRaportDeleteIsNotCommentedOut(): void
    {
        $this->assertStringContainsString(
            "DELETE FROM %%RW%%",
            $this->source,
            'Combat report cleanup must be active in CleanerCronjob::run()'
        );

        // Ensure the active line is not preceded by a comment marker on the same line
        preg_match_all('/([^\n]*)DELETE FROM %%RW%%/', $this->source, $matches);
        foreach ($matches[1] as $prefix) {
            $this->assertStringNotContainsString('//', trim($prefix),
                'DELETE FROM %%RW%% must not be commented out');
        }
    }

    public function testRaportDeletePreservesTopKbEntries(): void
    {
        $this->assertMatchesRegularExpression(
            '/DELETE FROM %%RW%%[^;]+NOT IN\s*\(SELECT[^)]+%%TOPKB%%/s',
            $this->source,
            'Combat report delete must exclude hall-of-fame entries via NOT IN (SELECT rid FROM %%TOPKB%%)'
        );
    }

    public function testRaportDeleteUsesDelBeforeParam(): void
    {
        $this->assertMatchesRegularExpression(
            '/DELETE FROM %%RW%%.*:time.*del_before/s',
            $this->source,
            'Combat report delete must be bounded by $del_before (:time param)'
        );
    }

    public function testMessageDeleteIsActive(): void
    {
        $this->assertStringContainsString(
            "DELETE FROM %%MESSAGES%% WHERE `message_time` < :time",
            $this->source,
            'Message cleanup by message_time must be active'
        );
    }

    public function testLogBuildingsCleanupIsActive(): void
    {
        $this->assertMatchesRegularExpression(
            '/DELETE FROM %%LOG_BUILDINGS%%[^;]+queued_at[^;]+:time/s',
            $this->source,
            'LOG_BUILDINGS cleanup must be active and bounded by queued_at / :time'
        );
    }

    public function testLogResearchCleanupIsActive(): void
    {
        $this->assertMatchesRegularExpression(
            '/DELETE FROM %%LOG_RESEARCH%%[^;]+queued_at[^;]+:time/s',
            $this->source,
            'LOG_RESEARCH cleanup must be active and bounded by queued_at / :time'
        );
    }

    public function testLogShipyardCleanupIsActive(): void
    {
        $this->assertMatchesRegularExpression(
            '/DELETE FROM %%LOG_SHIPYARD%%[^;]+queued_at[^;]+:time/s',
            $this->source,
            'LOG_SHIPYARD cleanup must be active and bounded by queued_at / :time'
        );
    }
}
