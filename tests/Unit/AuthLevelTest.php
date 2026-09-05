<?php

declare(strict_types=1);

use HiveNova\Core\AuthLevel;
use PHPUnit\Framework\TestCase;

class AuthLevelTest extends TestCase
{
	public function testUsrIsNotStaffAndCannotEnterAdminOrDashboard(): void
	{
		$this->assertFalse(AuthLevel::isStaff(AUTH_USR));
		$this->assertFalse(AuthLevel::canEnterAdmin(AUTH_USR));
		$this->assertFalse(AuthLevel::canViewReferralDashboard(AUTH_USR));
	}

	public function testPromoCanViewDashboardButIsNotStaff(): void
	{
		$this->assertFalse(AuthLevel::isStaff(AUTH_PROMO));
		$this->assertFalse(AuthLevel::canEnterAdmin(AUTH_PROMO));
		$this->assertTrue(AuthLevel::canViewReferralDashboard(AUTH_PROMO));
	}

	public function testModAndOpsAreStaffWithoutInGameDashboard(): void
	{
		foreach ([AUTH_MOD, AUTH_OPS] as $level) {
			$this->assertTrue(AuthLevel::isStaff($level));
			$this->assertTrue(AuthLevel::canEnterAdmin($level));
			$this->assertFalse(AuthLevel::canViewReferralDashboard($level));
		}
	}

	public function testAdmIsStaffAndCanViewDashboard(): void
	{
		$this->assertTrue(AuthLevel::isStaff(AUTH_ADM));
		$this->assertTrue(AuthLevel::canEnterAdmin(AUTH_ADM));
		$this->assertTrue(AuthLevel::canViewReferralDashboard(AUTH_ADM));
	}

	public function testRankSelectorCoversEveryDefinedRank(): void
	{
		$lng = [
			'rank_0' => 'Player',
			'rank_1' => 'Promoter',
			'rank_2' => 'Moderator',
			'rank_3' => 'Operator',
			'rank_4' => 'Admin',
		];

		$this->assertSame(
			[
				AUTH_USR => 'Player',
				AUTH_PROMO => 'Promoter',
				AUTH_MOD => 'Moderator',
				AUTH_OPS => 'Operator',
				AUTH_ADM => 'Admin',
			],
			AuthLevel::rankLabels($lng)
		);
	}
}
