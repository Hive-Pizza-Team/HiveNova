<?php

use HiveNova\Core\ReferralStatsService;
use PHPUnit\Framework\TestCase;

class ReferralStatsServiceTest extends TestCase
{
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
}
