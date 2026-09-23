<?php

use HiveNova\Core\FleetTargetValidationService;
use PHPUnit\Framework\TestCase;

class FleetTargetValidationServiceTest extends TestCase
{
	public function testEmptyPlanetIsBlockedWithoutColonyShip(): void
	{
		$this->assertFalse(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_ESPIONAGE_PROBE => 1],
			FleetTargetValidationService::TYPE_PLANET,
			1,
			15
		));
	}

	public function testEmptyPlanetAllowsColonisation(): void
	{
		$this->assertTrue(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_COLONY_SHIP => 1],
			FleetTargetValidationService::TYPE_PLANET,
			1,
			15
		));
	}

	public function testZeroColonyShipsDoNotUnlockEmptyPlanet(): void
	{
		$this->assertFalse(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_COLONY_SHIP => 0],
			FleetTargetValidationService::TYPE_PLANET,
			1,
			15
		));
	}

	public function testReservedSlotsAndPvePackagesAreAllowed(): void
	{
		$this->assertTrue(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_ESPIONAGE_PROBE => 1],
			FleetTargetValidationService::TYPE_PLANET,
			16,
			15
		));
		$this->assertTrue(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_ESPIONAGE_PROBE => 1],
			FleetTargetValidationService::TYPE_PLANET,
			17,
			15
		));
		$this->assertTrue(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_ESPIONAGE_PROBE => 1],
			FleetTargetValidationService::TYPE_PLANET,
			4,
			15,
			true
		));
	}

	public function testEmptyMoonOrDebrisWithoutPackageIsBlocked(): void
	{
		$this->assertFalse(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_COLONY_SHIP => 1],
			FleetTargetValidationService::TYPE_MOON,
			1,
			15
		));
		$this->assertFalse(FleetTargetValidationService::allowsMissingPlanet(
			[SHIP_RECYCLER => 1],
			FleetTargetValidationService::TYPE_DEBRIS,
			1,
			15
		));
	}

	public function testResolveShipsPrefersSessionTokenThenKoloFlag(): void
	{
		$session = [
			'abc' => ['fleet' => [SHIP_ESPIONAGE_PROBE => 3]],
		];

		$this->assertSame(
			[SHIP_ESPIONAGE_PROBE => 3],
			FleetTargetValidationService::resolveShipsForCheck($session, 'abc', true)
		);
		$this->assertSame(
			[SHIP_COLONY_SHIP => 1],
			FleetTargetValidationService::resolveShipsForCheck($session, 'missing', true)
		);
		$this->assertSame(
			[],
			FleetTargetValidationService::resolveShipsForCheck($session, '', false)
		);
	}
}
