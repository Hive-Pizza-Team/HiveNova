<?php

use HiveNova\Core\BotDetectionService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/BotDetectionFakeDatabase.php';

class BotDetectionServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private BotDetectionFakeDatabase $fake;

	private string $source;

	protected function setUp(): void
	{
		$this->source = file_get_contents(
			__DIR__ . '/../../includes/classes/BotDetectionService.php'
		);

		if (!defined('AUTH_USR')) {
			define('AUTH_USR', 0);
		}

		$this->fake = new BotDetectionFakeDatabase();
		$this->swapDatabaseInstance($this->fake);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_computeMaxGapSeconds_uses_internal_and_boundary_gaps(): void
	{
		$cutoff = 1_000_000;
		$now    = 1_001_500;
		$times  = [1_000_500, 1_001_000, 1_001_500];

		$this->assertSame(
			500,
			BotDetectionService::computeMaxGapSeconds($times, $cutoff, $now)
		);
	}

	public function test_computeMaxGapSeconds_leading_boundary_clears_burst_player(): void
	{
		$now    = TIMESTAMP;
		$cutoff = BotDetectionService::cutoffTimestamp($now);
		$times  = [];
		$start  = $now - (10 * 1800);
		for ($i = 0; $i < 10; $i++) {
			$times[] = $start + ($i * 1800);
		}

		$maxGap = BotDetectionService::computeMaxGapSeconds($times, $cutoff, $now);

		$this->assertGreaterThanOrEqual(BotDetectionService::SLEEP_THRESHOLD, $maxGap);
		$this->assertFalse(BotDetectionService::isFlagged($maxGap, count($times)));
	}

	public function test_computeMaxGapSeconds_flags_continuous_week_activity(): void
	{
		$now    = TIMESTAMP;
		$cutoff = BotDetectionService::cutoffTimestamp($now);
		$times  = [];
		$time   = $cutoff + 60;
		for ($i = 0; $i < 85; $i++) {
			$times[] = $time;
			$time += 7140;
		}

		$maxGap = BotDetectionService::computeMaxGapSeconds($times, $cutoff, $now);

		$this->assertLessThan(BotDetectionService::SLEEP_THRESHOLD, $maxGap);
		$this->assertTrue(BotDetectionService::isFlagged($maxGap, count($times)));
	}

	public function test_findSuspects_excludes_npc_bot_accounts(): void
	{
		$this->seedContinuousEvents(42, 'NpcBot', 85, 7140);
		$this->fake->botDetectionUsers[42] = ['email' => BotDetectionService::NPC_BOT_EMAIL];

		$suspects = (new BotDetectionService())->findSuspects(1);

		$this->assertSame([], $suspects);
	}

	public function test_findSuspects_flags_shipyard_only_activity(): void
	{
		$this->seedContinuousEvents(55, 'ShipyardBot', 85, 7140, 'shipyard');

		$suspects = (new BotDetectionService())->findSuspects(1);

		$this->assertCount(1, $suspects);
		$this->assertSame('ShipyardBot', $suspects[0]['username']);
		$this->assertSame(85, $suspects[0]['shipyard_count']);
		$this->assertSame(0, $suspects[0]['fleet_count']);
	}

	public function test_shouldNotify_skips_unchanged_digest(): void
	{
		$service = new BotDetectionService();
		$suspects = [
			['id' => 1, 'max_gap_seconds' => 100],
			['id' => 2, 'max_gap_seconds' => 200],
		];
		$hash = BotDetectionService::computeDigestHash($suspects);

		$this->assertTrue($service->shouldNotify(1, $hash));

		$service->markNotified(1, $hash);

		$this->assertFalse($service->shouldNotify(1, $hash));
	}

	public function test_query_includes_log_shipyard(): void
	{
		$this->assertStringContainsString('%%LOG_SHIPYARD%%', $this->source);
	}

	public function test_query_excludes_npc_email(): void
	{
		$this->assertStringContainsString("u.email != :npcEmail", $this->source);
		$this->assertStringContainsString("self::NPC_BOT_EMAIL", $this->source);
	}

	public function test_query_uses_window_functions(): void
	{
		$this->assertStringContainsString('LAG(event_time) OVER', $this->source);
	}

	/**
	 * @param 'fleet'|'building'|'research'|'shipyard' $source
	 */
	private function seedContinuousEvents(
		int $userId,
		string $username,
		int $count,
		int $gapSeconds,
		string $source = 'fleet',
	): void {
		$cutoff = BotDetectionService::cutoffTimestamp();
		$time   = $cutoff + 60;
		for ($i = 0; $i < $count; $i++) {
			$this->fake->botDetectionEvents[] = [
				'user_id'    => $userId,
				'username'   => $username,
				'event_time' => $time,
				'source'     => $source,
			];
			$time += $gapSeconds;
		}
	}
}
