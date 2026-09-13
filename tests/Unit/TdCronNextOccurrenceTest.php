<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/libs/tdcron/class.tdcron.entry.php';
require_once __DIR__ . '/../../includes/libs/tdcron/class.tdcron.php';

/**
 * Regression: tdCron treated hour 0 as "no match" via !$nhour, so every-15-minute
 * jobs under UTC jumped from ~00:00 to the next midnight (parked season cron ~24h).
 */
class TdCronNextOccurrenceTest extends TestCase
{
	private string $previousTimezone;

	protected function setUp(): void
	{
		parent::setUp();
		$this->previousTimezone = date_default_timezone_get();
	}

	protected function tearDown(): void
	{
		date_default_timezone_set($this->previousTimezone);
		parent::tearDown();
	}

	/**
	 * @return list<array{0: string, 1: string, 2: string}>
	 */
	public static function utcQuarterHourCases(): array
	{
		$everyFifteen = '*/15 * * * *';

		return [
			// Exact prod failure: season cron finished at 00:00:03 UTC, next was Sep 14 00:00.
			['2026-09-13 00:00:03', $everyFifteen, '2026-09-13 00:15:00'],
			['2026-09-13 00:15:00', $everyFifteen, '2026-09-13 00:30:00'],
			['2026-09-13 00:45:00', $everyFifteen, '2026-09-13 01:00:00'],
			['2026-09-12 23:45:02', $everyFifteen, '2026-09-13 00:00:00'],
			['2026-09-13 01:00:00', $everyFifteen, '2026-09-13 01:15:00'],
		];
	}

	#[DataProvider('utcQuarterHourCases')]
	public function test_quarter_hour_schedule_under_utc(string $fromUtc, string $expr, string $expectedUtc): void
	{
		date_default_timezone_set('UTC');
		$from = strtotime($fromUtc . ' UTC');
		$next = tdCron::getNextOccurrence($expr, $from + 60);

		$this->assertSame(
			$expectedUtc,
			gmdate('Y-m-d H:i:s', $next),
			sprintf('from %s UTC (+60s)', $fromUtc)
		);
	}

	public function test_hour_zero_job_under_utc(): void
	{
		date_default_timezone_set('UTC');
		$from = strtotime('2026-09-13 00:00:03 UTC');
		$next = tdCron::getNextOccurrence('0 * * * *', $from + 60);

		$this->assertSame('2026-09-13 01:00:00', gmdate('Y-m-d H:i:s', $next));
	}
}
