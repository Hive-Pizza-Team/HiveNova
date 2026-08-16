<?php

use HiveNova\Core\LeftoverBonus;
use PHPUnit\Framework\TestCase;

class LeftoverBonusTest extends TestCase
{
	protected function setUp(): void
	{
		$GLOBALS['resource'][114] = 'hyperspace_tech';
		$GLOBALS['resource'][120] = 'laser_tech';
		$GLOBALS['resource'][121] = 'ionic_tech';
		$GLOBALS['resource'][122] = 'buster_tech';

		$GLOBALS['requirements'][204] = [115 => 1];
		$GLOBALS['requirements'][206] = [121 => 2];
		$GLOBALS['requirements'][211] = [122 => 5];
		$GLOBALS['requirements'][215] = [114 => 5, 120 => 12];
		$GLOBALS['requirements'][202] = [115 => 1];
		$GLOBALS['requirements'][217] = [114 => 10];
		$GLOBALS['requirements'][209] = [115 => 6, 110 => 2];
		$GLOBALS['requirements'][43]  = [114 => 7];
		$GLOBALS['requirements'][999] = [120 => 1, 121 => 1];
	}

	public function testLightFighterGetsNoLaserAttackLeftover(): void
	{
		$this->assertSame(1.0, LeftoverBonus::attackMultiplier(204, ['laser_tech' => 20]));
	}

	public function testCruiserGetsIonAttackLeftover(): void
	{
		$this->assertEqualsWithDelta(1.10, LeftoverBonus::attackMultiplier(206, ['ionic_tech' => 10]), 1e-9);
	}

	public function testBomberGetsPlasmaOnlyNotLaser(): void
	{
		$player = ['buster_tech' => 7, 'laser_tech' => 20];
		$this->assertEqualsWithDelta(1.07, LeftoverBonus::attackMultiplier(211, $player), 1e-9);
	}

	public function testBattlecruiserGetsLaserAttackAndHyperspaceCargo(): void
	{
		$player = ['laser_tech' => 12, 'hyperspace_tech' => 8];
		$this->assertEqualsWithDelta(1.12, LeftoverBonus::attackMultiplier(215, $player), 1e-9);
		$this->assertEqualsWithDelta(1.08, LeftoverBonus::cargoMultiplier(215, $player), 1e-9);
	}

	public function testSmallCargoIgnoresHyperspaceLeftover(): void
	{
		$this->assertSame(1.0, LeftoverBonus::cargoMultiplier(202, ['hyperspace_tech' => 50]));
	}

	public function testMissingPlayerKeysDefaultToZero(): void
	{
		$this->assertSame(1.0, LeftoverBonus::attackMultiplier(206, []));
		$this->assertSame(1.0, LeftoverBonus::cargoMultiplier(217, []));
	}

	public function testAttackLeftoversStackWhenUnitListsTwoWeaponTechs(): void
	{
		$player = ['laser_tech' => 4, 'ionic_tech' => 6];
		$this->assertEqualsWithDelta(1.10, LeftoverBonus::attackMultiplier(999, $player), 1e-9);
	}

	public function testJumpGateDoesNotGetCargoLeftover(): void
	{
		$this->assertSame(1.0, LeftoverBonus::cargoMultiplier(43, ['hyperspace_tech' => 20]));
	}

	public function testRecyclerGetsNoHyperspaceCargo(): void
	{
		$this->assertSame(1.0, LeftoverBonus::cargoMultiplier(209, ['hyperspace_tech' => 20]));
	}

	public function testBattleInputMapsLeftoverTechs(): void
	{
		$techs = LeftoverBonus::playerTechsFromBattleInput([
			109 => 8,
			114 => 5,
			120 => 10,
			122 => 3,
		]);

		$this->assertSame(5, $techs['hyperspace_tech']);
		$this->assertSame(10, $techs['laser_tech']);
		$this->assertSame(0, $techs['ionic_tech']);
		$this->assertSame(3, $techs['buster_tech']);
	}

	public function testShipCapacityAppliesHyperspaceLeftover(): void
	{
		$GLOBALS['pricelist'][217]['capacity'] = 400000;
		$capacity = LeftoverBonus::shipCapacity(217, 10, ['hyperspace_tech' => 10]);
		$this->assertEqualsWithDelta(10 * 400000 * 1.10, $capacity, 1e-6);
	}
}
