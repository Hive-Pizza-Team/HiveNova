<?php

use PHPUnit\Framework\TestCase;

class OPBEGetShipTypeTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2) . '/includes/classes/missions/functions/OPBE.php';
	}

	protected function setUp(): void
	{
		$GLOBALS['resource'][120] = 'laser_tech';
		$GLOBALS['resource'][121] = 'ionic_tech';
		$GLOBALS['resource'][122] = 'buster_tech';
		$GLOBALS['requirements'][206] = [121 => 2];
		$GLOBALS['requirements'][204] = [115 => 1];
		$GLOBALS['requirements'][402] = [120 => 3];
		$GLOBALS['requirements'][404] = [109 => 3];
		$GLOBALS['CombatCaps'][206] = ['attack' => 150, 'shield' => 50, 'sd' => []];
		$GLOBALS['CombatCaps'][204] = ['attack' => 50, 'shield' => 10, 'sd' => []];
		$GLOBALS['CombatCaps'][402] = ['attack' => 100, 'shield' => 25, 'sd' => []];
		$GLOBALS['CombatCaps'][404] = ['attack' => 1100, 'shield' => 200, 'sd' => []];
		$GLOBALS['pricelist'][206]['cost'] = [901 => 20000, 902 => 7000];
		$GLOBALS['pricelist'][204]['cost'] = [901 => 3000, 902 => 1000];
		$GLOBALS['pricelist'][402]['cost'] = [901 => 1500, 902 => 500];
		$GLOBALS['pricelist'][404]['cost'] = [901 => 20000, 902 => 15000];
	}

	public function testCruiserIonLeftoverScalesBasePower(): void
	{
		$ship = getShipType(206, 1, ['ionic_tech' => 10]);
		$this->assertEqualsWithDelta(165.0, $ship->getPower(), 1e-6);
	}

	public function testLightFighterIgnoresLaserLeftover(): void
	{
		$ship = getShipType(204, 1, ['laser_tech' => 20]);
		$this->assertEqualsWithDelta(50.0, $ship->getPower(), 1e-6);
	}

	public function testLightLaserLeftoverThenWeaponsTech(): void
	{
		$defense = getShipType(402, 1, ['laser_tech' => 5]);
		$defense->setWeaponsTech(8);
		$this->assertEqualsWithDelta(100 * 1.05 * 1.80, $defense->getPower(), 1e-6);
	}

	public function testGaussIgnoresLaserLeftover(): void
	{
		$defense = getShipType(404, 1, ['laser_tech' => 20]);
		$this->assertEqualsWithDelta(1100.0, $defense->getPower(), 1e-6);
	}

	public function testGetTechsFromArrayIgnoresLeftoverTechs(): void
	{
		$player = [
			'military_tech' => 10,
			'shield_tech'   => 0,
			'defence_tech'  => 0,
			'laser_tech'    => 20,
			'factor'        => ['Attack' => 0, 'Shield' => 0, 'Defensive' => 0],
		];
		[$attTech] = getTechsFromArray($player);
		$this->assertEqualsWithDelta(10.0, $attTech, 1e-9);
	}

	public function testAcsPlayersKeepSeparateLeftover(): void
	{
		$weak = getShipType(206, 1, ['ionic_tech' => 0]);
		$strong = getShipType(206, 1, ['ionic_tech' => 15]);
		$this->assertEqualsWithDelta(150.0, $weak->getPower(), 1e-6);
		$this->assertEqualsWithDelta(172.5, $strong->getPower(), 1e-6);
	}
}
