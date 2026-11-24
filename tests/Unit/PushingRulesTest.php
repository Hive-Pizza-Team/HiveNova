<?php

declare(strict_types=1);

use HiveNova\Core\PushingRules;
use PHPUnit\Framework\TestCase;

class PushingRulesTest extends TestCase
{
	private function pair(int $src, int $dst, float $value, int $count = 1): array
	{
		return ['source_id' => $src, 'dest_id' => $dst, 'source_name' => 'S' . $src, 'dest_name' => 'D' . $dst, 'fleet_count' => $count, 'value' => $value];
	}

	public function testDefaults(): void
	{
		$this->assertSame(14 * 24 * 3600, PushingRules::WINDOW_SECONDS);
		$this->assertSame(250000.0, PushingRules::PAIR_VALUE_THRESHOLD);
		$this->assertSame(3, PushingRules::FUNNEL_MIN_SOURCES);
		$this->assertSame(10000.0, PushingRules::FUNNEL_MIN_SOURCE_VALUE);
		$this->assertSame(500000.0, PushingRules::FUNNEL_VALUE_THRESHOLD);
	}

	public function testResourceValueAtReferenceRatio(): void
	{
		$this->assertSame(1000.0 + 500.0 + 100.0, PushingRules::resourceValue(4000, 1000, 100, [4, 2, 1]));
	}

	public function testPairThresholdIsValueNotCount(): void
	{
		$this->assertSame([], PushingRules::buildReport([$this->pair(1, 9, 249999, 50)], []));
		$report = PushingRules::buildReport([$this->pair(1, 9, 250000, 1)], []);
		$this->assertCount(1, $report);
		$this->assertSame(['pair'], $report[0]['reasons']);
		$this->assertTrue($report[0]['sources'][0]['over_pair_threshold']);
	}

	public function testTradesAddToTheSamePair(): void
	{
		$report = PushingRules::buildReport(
			[$this->pair(1, 9, 200000, 2)],
			[['source' => 1, 'dest' => 9, 'value' => 30000], ['source' => 1, 'dest' => 9, 'value' => 20000]]
		);
		$this->assertSame(250000.0, $report[0]['total_value']);
		$this->assertSame(2, $report[0]['sources'][0]['transports']);
		$this->assertSame(2, $report[0]['sources'][0]['trades']);
	}

	public function testFunnelNeedsEnoughSourcesAndValue(): void
	{
		// Three sources under the pair limit, 600k total: funnel.
		$report = PushingRules::buildReport([$this->pair(1, 9, 200000), $this->pair(2, 9, 200000), $this->pair(3, 9, 200000)], []);
		$this->assertSame(['funnel'], $report[0]['reasons']);
		$this->assertSame(600000.0, $report[0]['total_value']);

		// Only two sources: not a funnel.
		$this->assertSame([], PushingRules::buildReport([$this->pair(1, 9, 240000), $this->pair(2, 9, 240000)], []));
		// Three sources but under 500k: not a funnel.
		$this->assertSame([], PushingRules::buildReport([$this->pair(1, 9, 150000), $this->pair(2, 9, 150000), $this->pair(3, 9, 150000)], []));
		// Trickle sources below the per-source minimum do not count toward the funnel.
		$this->assertSame([], PushingRules::buildReport([$this->pair(1, 9, 240000), $this->pair(2, 9, 240000), $this->pair(3, 9, 9999), $this->pair(4, 9, 9999)], []));
	}

	public function testBothReasonsAndSorting(): void
	{
		$report = PushingRules::buildReport([
			$this->pair(1, 9, 300000), $this->pair(2, 9, 200000), $this->pair(3, 9, 100000),
			$this->pair(4, 8, 900000),
		], []);
		$this->assertSame([8, 9], array_column($report, 'dest_id'));
		$this->assertSame(['pair', 'funnel'], $report[1]['reasons']);
		$this->assertSame([1, 2, 3], array_column($report[1]['sources'], 'source_id'));
	}

	public function testReportTextIsForAdminReview(): void
	{
		$report = PushingRules::buildReport([$this->pair(1, 9, 300000, 3)], [['source' => 1, 'dest' => 9, 'value' => 1000]]);
		$text = PushingRules::reportText($report, 0, 14 * 24 * 3600);
		$this->assertStringContainsString('between 1970-01-01 00:00 and 1970-01-15 00:00 UTC', $text);
		$this->assertStringContainsString('D9 (id 9): received 301.000 from 1 weaker account(s) [single source over limit]', $text);
		$this->assertStringContainsString('  - S1 (id 1): 3 transport(s), 1 trade(s), 301.000 (over limit)', $text);
		$this->assertStringContainsString('Points are current points.', $text);
		$this->assertStringContainsString('Please review', $text);
	}
}
