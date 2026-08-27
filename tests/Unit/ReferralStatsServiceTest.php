<?php

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\ReferralStatsService;
use PHPUnit\Framework\TestCase;

class ReferralStatsServiceTest extends TestCase
{
	private ReferralStatsService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->service = new ReferralStatsService();
	}

	public function testBonusStatusPendingWhenBelowMinPoints(): void
	{
		$this->assertSame(
			ReferralStatsService::STATUS_PENDING,
			ReferralStatsService::bonusStatus(1, 500, 1000)
		);
	}

	public function testBonusStatusReadyWhenQualifiedButNotPaid(): void
	{
		$this->assertSame(
			ReferralStatsService::STATUS_READY,
			ReferralStatsService::bonusStatus(1, 1500, 1000)
		);
	}

	public function testBonusStatusPaidAfterCron(): void
	{
		$this->assertSame(
			ReferralStatsService::STATUS_PAID,
			ReferralStatsService::bonusStatus(0, 1500, 1000)
		);
	}

	public function testGetSummaryMapsRowAndDefaults(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn([
			'total_recruits'   => '5',
			'active_referrers' => '2',
			'pending_bonus'    => '3',
			'bonus_paid'       => '2',
		]);

		$summary = $this->service->getSummary($db, 1, 1000);

		$this->assertSame([
			'total_recruits'   => 5,
			'active_referrers' => 2,
			'pending_bonus'    => 3,
			'bonus_paid'       => 2,
			'ref_minpoints'    => 1000,
		], $summary);
	}

	public function testGetSummaryDefaultsWhenRowMissing(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn([]);

		$summary = $this->service->getSummary($db, 2, 500);

		$this->assertSame(0, $summary['total_recruits']);
		$this->assertSame(0, $summary['active_referrers']);
		$this->assertSame(0, $summary['pending_bonus']);
		$this->assertSame(0, $summary['bonus_paid']);
		$this->assertSame(500, $summary['ref_minpoints']);
	}

	public function testCountReferrers(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn(['cnt' => '7']);

		$this->assertSame(7, $this->service->countReferrers($db, 1));
	}

	public function testCountReferrersDefaultsZero(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn([]);

		$this->assertSame(0, $this->service->countReferrers($db, 1));
	}

	public function testGetReferrerRowsMapsRows(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn([
			[
				'referrer_id'       => 10,
				'referrer_username' => 'alice',
				'referrer_hive'     => 'aliceaaa',
				'recruit_count'     => 2,
				'pending_bonus'     => 1,
				'bonus_paid'        => 1,
				'qualified_count'   => 1,
			],
		]);

		$rows = $this->service->getReferrerRows($db, 1, 1000, 25, 0);

		$this->assertCount(1, $rows);
		$this->assertSame([
			'referrer_id'       => 10,
			'referrer_username' => 'alice',
			'referrer_hive'     => 'aliceaaa',
			'recruit_count'     => 2,
			'pending_bonus'     => 1,
			'bonus_paid'        => 1,
			'qualified_count'   => 1,
			'referral_link'     => 'index.php?ref=10',
		], $rows[0]);
	}

	public function testGetReferrerRowsDefaultsMissingHive(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn([
			[
				'referrer_id'       => 11,
				'referrer_username' => 'bob',
				'recruit_count'     => 1,
				'pending_bonus'     => 0,
				'bonus_paid'        => 1,
				'qualified_count'   => 0,
			],
		]);

		$rows = $this->service->getReferrerRows($db, 1, 1000, 10, 5);

		$this->assertSame('', $rows[0]['referrer_hive']);
	}

	public function testGetRecentRecruitsMapsBonusStatus(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn([
			[
				'recruit_id'        => 20,
				'recruit_username'  => 'recruit1',
				'register_time'     => 1700000000,
				'ref_bonus'         => 1,
				'referrer_id'       => 10,
				'referrer_username' => 'alice',
				'total_points'      => 500,
			],
			[
				'recruit_id'        => 21,
				'recruit_username'  => 'recruit2',
				'register_time'     => 1700000100,
				'ref_bonus'         => 0,
				'referrer_id'       => 10,
				'referrer_username' => 'alice',
				'total_points'      => 2000,
			],
		]);

		$rows = $this->service->getRecentRecruits($db, 1, 1000, 10, 0);

		$this->assertCount(2, $rows);
		$this->assertSame(ReferralStatsService::STATUS_PENDING, $rows[0]['bonus_status']);
		$this->assertSame(ReferralStatsService::STATUS_PAID, $rows[1]['bonus_status']);
		$this->assertSame('recruit1', $rows[0]['recruit_username']);
		$this->assertSame(1700000000, $rows[0]['register_time']);
	}

	public function testCountRecruits(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn(['cnt' => '12']);

		$this->assertSame(12, $this->service->countRecruits($db, 3));
	}

	public function testCountRecruitsDefaultsZero(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn(null);

		$this->assertSame(0, $this->service->countRecruits($db, 3));
	}
}
