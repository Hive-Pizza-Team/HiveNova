<?php

declare(strict_types=1);

use HiveNova\Core\AdminLogDetailRows;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for admin log detail rendering (ShowLogDetail).
 *
 * Serialized before/after snapshots can diverge (e.g. config key removed); PHP 8 must not
 * emit undefined index notices when building the comparison table.
 */
class AdminLogDetailRowsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['userNumberFormat']);
    }

    public function testMissingAfterKeyProducesEmptyNewValue(): void
    {
        $lng = ['php_tdformat' => 'Y-m-d'];
        $before = ['multi' => 2, 'universe' => 1];
        $after = ['universe' => 1];

        $rows = AdminLogDetailRows::build($before, $after, [], $lng);

        $this->assertCount(1, $rows);
        $this->assertSame('multi', $rows[0]['Element']);
        $this->assertSame('2', strip_tags($rows[0]['old']));
        $this->assertSame('', $rows[0]['new']);
    }

    public function testExplicitNullInAfterIsRenderedAsEmptyNew(): void
    {
        $lng = ['php_tdformat' => 'Y-m-d'];
        $before = ['multi' => 1];
        $after = ['multi' => null];

        $rows = AdminLogDetailRows::build($before, $after, [], $lng);

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['new']);
    }

    public function testSkipsUniverseRow(): void
    {
        $lng = ['php_tdformat' => 'Y-m-d'];
        $before = ['universe' => 99, 'metal' => 100];
        $after = ['universe' => 99, 'metal' => 200];

        $rows = AdminLogDetailRows::build($before, $after, [], $lng);

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('100', strip_tags($rows[0]['old']));
        $this->assertStringContainsString('200', strip_tags($rows[0]['new']));
    }

    public function testUsesWrapperLabelWhenPresent(): void
    {
        $lng = ['php_tdformat' => 'Y-m-d', 'tech' => []];
        $wrapper = ['metal' => 'Metal resource'];

        $rows = AdminLogDetailRows::build(
            ['metal' => 10],
            ['metal' => 20],
            $wrapper,
            $lng
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Metal resource', $rows[0]['Element']);
    }
}
