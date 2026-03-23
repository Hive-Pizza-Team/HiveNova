<?php

use HiveNova\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Static analysis tests for UserRepository SQL queries.
 *
 * These tests do not connect to a real database; they verify that the
 * SQL strings in each method reference columns that actually exist in
 * the schema (install.sql), catching typos like the stat_points →
 * total_points regression introduced in #122.
 */
class UserRepositoryTest extends TestCase
{
    private function getMethodSource(string $method): string
    {
        $ref    = new ReflectionMethod(UserRepository::class, $method);
        $start  = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;
        $lines  = array_slice(file($ref->getFileName()), $start, $length);
        return implode('', $lines);
    }

    /**
     * `stat_points` does not exist in the statpoints table.
     * The correct column is `total_points`.
     * Regression: was introduced in #122 and caused a fatal DB error on fleetStep3.
     */
    public function testGetUserWithStatsDoesNotUseNonExistentStatPointsColumn(): void
    {
        $source = $this->getMethodSource('getUserWithStats');

        $this->assertStringNotContainsString(
            'stat_points',
            $source,
            'stat_points does not exist in the statpoints table — use total_points'
        );
    }

    public function testGetUserWithStatsSelectsTotalPoints(): void
    {
        $source = $this->getMethodSource('getUserWithStats');

        $this->assertStringContainsString(
            'total_points',
            $source,
            'getUserWithStats must select total_points from the statpoints table'
        );
    }

    public function testGetUserWithStatsJoinsStatpointsTable(): void
    {
        $source = $this->getMethodSource('getUserWithStats');

        $this->assertStringContainsString(
            '%%STATPOINTS%%',
            $source,
            'getUserWithStats must JOIN %%STATPOINTS%% using the dbtables placeholder'
        );
    }
}
