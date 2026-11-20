<?php

declare(strict_types=1);

use HiveNova\Core\GalaxyViewChargeService;
use PHPUnit\Framework\TestCase;

class GalaxyViewChargeServiceTest extends TestCase
{
	public function test_detects_navigation_from_arrows_or_form_marker(): void
	{
		$this->assertTrue(GalaxyViewChargeService::isNavigationRequest(['galaxyLeft' => 'dr']));
		$this->assertTrue(GalaxyViewChargeService::isNavigationRequest(['systemRight' => 'dr']));
		$this->assertTrue(GalaxyViewChargeService::isNavigationRequest([
			GalaxyViewChargeService::NAV_PARAM => '1',
			'galaxy' => 2,
			'system' => 5,
		]));
		$this->assertFalse(GalaxyViewChargeService::isNavigationRequest([
			'page' => 'galaxy',
			'galaxy' => 2,
			'system' => 5,
		]));
	}

	public function test_resolve_coordinates_applies_arrows_and_clamps(): void
	{
		$this->assertSame(
			['galaxy' => 1, 'system' => 3],
			GalaxyViewChargeService::resolveCoordinates(2, 3, 9, 499, 'dr', '', '', '')
		);
		$this->assertSame(
			['galaxy' => 9, 'system' => 499],
			GalaxyViewChargeService::resolveCoordinates(9, 498, 9, 499, '', '', '', 'dr')
		);
		$this->assertSame(
			['galaxy' => 1, 'system' => 1],
			GalaxyViewChargeService::resolveCoordinates(1, 1, 9, 499, 'dr', '', 'dr', '')
		);
	}

	public function test_home_system_never_charges(): void
	{
		$this->assertFalse(GalaxyViewChargeService::shouldCharge(1, 1, 1, 1, true, []));
		$this->assertFalse(GalaxyViewChargeService::shouldCharge(1, 1, 1, 1, false, []));
	}

	public function test_navigation_always_charges_non_home(): void
	{
		$paid = GalaxyViewChargeService::markPaid([], 2, 5);
		$this->assertTrue(GalaxyViewChargeService::shouldCharge(2, 5, 1, 1, true, $paid));
		$this->assertTrue(GalaxyViewChargeService::shouldCharge(3, 1, 1, 1, true, []));
	}

	public function test_plain_get_skips_already_paid_system(): void
	{
		$paid = GalaxyViewChargeService::markPaid([], 2, 5);
		$this->assertFalse(GalaxyViewChargeService::shouldCharge(2, 5, 1, 1, false, $paid));
	}

	public function test_plain_get_charges_unpaid_deep_link_once(): void
	{
		$this->assertTrue(GalaxyViewChargeService::shouldCharge(4, 10, 1, 1, false, []));
		$paid = GalaxyViewChargeService::markPaid([], 4, 10);
		$this->assertFalse(GalaxyViewChargeService::shouldCharge(4, 10, 1, 1, false, $paid));
	}

	public function test_clean_url_strips_navigation_params(): void
	{
		$this->assertSame(
			'game.php?page=galaxy&galaxy=2&system=8',
			GalaxyViewChargeService::cleanUrl(2, 8)
		);
	}
}
