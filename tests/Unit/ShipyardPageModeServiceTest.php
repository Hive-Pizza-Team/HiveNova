<?php

declare(strict_types=1);

use HiveNova\Core\ShipyardPageModeService;
use PHPUnit\Framework\TestCase;

class ShipyardPageModeServiceTest extends TestCase
{
	public function test_defaults_to_fleet_when_both_modules_available(): void
	{
		$this->assertSame(
			ShipyardPageModeService::MODE_FLEET,
			ShipyardPageModeService::resolveMode('fleet', true, true)
		);
		$this->assertSame(
			ShipyardPageModeService::MODE_FLEET,
			ShipyardPageModeService::resolveMode('', true, true)
		);
	}

	public function test_keeps_defense_when_requested_and_available(): void
	{
		$this->assertSame(
			ShipyardPageModeService::MODE_DEFENSE,
			ShipyardPageModeService::resolveMode('defense', true, true)
		);
	}

	public function test_falls_back_to_fleet_when_defense_module_is_off(): void
	{
		$this->assertSame(
			ShipyardPageModeService::MODE_FLEET,
			ShipyardPageModeService::resolveMode('defense', true, false)
		);
	}

	public function test_falls_back_to_defense_when_only_defense_module_is_on(): void
	{
		$this->assertSame(
			ShipyardPageModeService::MODE_DEFENSE,
			ShipyardPageModeService::resolveMode('fleet', false, true)
		);
		$this->assertSame(
			ShipyardPageModeService::MODE_DEFENSE,
			ShipyardPageModeService::resolveMode('', false, true)
		);
	}

	public function test_honors_requested_mode_when_both_modules_are_off(): void
	{
		$this->assertSame(
			ShipyardPageModeService::MODE_FLEET,
			ShipyardPageModeService::resolveMode('fleet', false, false)
		);
		$this->assertSame(
			ShipyardPageModeService::MODE_DEFENSE,
			ShipyardPageModeService::resolveMode('defense', false, false)
		);
	}

	public function test_tabs_include_both_modes_when_available(): void
	{
		$tabs = ShipyardPageModeService::tabs(ShipyardPageModeService::MODE_DEFENSE, true, true);

		$this->assertSame([
			['mode' => 'fleet', 'active' => false],
			['mode' => 'defense', 'active' => true],
		], $tabs);
	}

	public function test_tabs_omit_unavailable_mode(): void
	{
		$tabs = ShipyardPageModeService::tabs(ShipyardPageModeService::MODE_FLEET, true, false);

		$this->assertSame([
			['mode' => 'fleet', 'active' => true],
		], $tabs);
	}

	public function test_tabs_show_both_when_modules_are_unknown(): void
	{
		$tabs = ShipyardPageModeService::tabs(ShipyardPageModeService::MODE_FLEET, false, false);

		$this->assertCount(2, $tabs);
		$this->assertTrue($tabs[0]['active']);
		$this->assertFalse($tabs[1]['active']);
	}

	public function test_is_defense_mode(): void
	{
		$this->assertTrue(ShipyardPageModeService::isDefenseMode('defense'));
		$this->assertFalse(ShipyardPageModeService::isDefenseMode('fleet'));
		$this->assertFalse(ShipyardPageModeService::isDefenseMode(''));
	}
}
