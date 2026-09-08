<?php

use HiveNova\Core\Config;
use HiveNova\Core\FleetMissionAvailability;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetMissionAvailabilityTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		$this->defineModules();

		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);

		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 50, 1)),
			'max_planets' => 15,
		]), 1);
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);

		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testHasMoonDestroyerAcceptsDeathstarOrBlackMoon(): void
	{
		$this->assertFalse(FleetMissionAvailability::hasMoonDestroyer([SHIP_COLONY_SHIP => 1]));
		$this->assertTrue(FleetMissionAvailability::hasMoonDestroyer([SHIP_DEATHSTAR => 1]));
		$this->assertTrue(FleetMissionAvailability::hasMoonDestroyer([SHIP_BLACK_MOON => 2]));
		$this->assertTrue(FleetMissionAvailability::hasMoonDestroyer([
			SHIP_DEATHSTAR => 1,
			SHIP_BLACK_MOON => 1,
		]));
	}

	public function testPlanetHasMoonDestroyerUsesResourceColumns(): void
	{
		$resource = [
			SHIP_DEATHSTAR => 'dearth_star',
			SHIP_BLACK_MOON => 'lune_noir',
		];

		$this->assertFalse(FleetMissionAvailability::planetHasMoonDestroyer([
			'dearth_star' => 0,
			'lune_noir' => 0,
		], $resource));
		$this->assertTrue(FleetMissionAvailability::planetHasMoonDestroyer([
			'dearth_star' => 1,
			'lune_noir' => 0,
		], $resource));
		$this->assertTrue(FleetMissionAvailability::planetHasMoonDestroyer([
			'dearth_star' => 0,
			'lune_noir' => 3,
		], $resource));
	}

	public function testMoonDestroyerCountSumsDeathstarAndBlackMoon(): void
	{
		$this->assertSame(0.0, FleetMissionAvailability::moonDestroyerCount([202 => 50]));
		$this->assertSame(4.0, FleetMissionAvailability::moonDestroyerCount([SHIP_DEATHSTAR => 4]));
		$this->assertSame(2.0, FleetMissionAvailability::moonDestroyerCount([SHIP_BLACK_MOON => 2]));
		$this->assertSame(5.0, FleetMissionAvailability::moonDestroyerCount([
			SHIP_DEATHSTAR => 3,
			SHIP_BLACK_MOON => 2,
		]));
	}

	public function testDestroyMissionOfferedForBlackMoonOnEnemyMoon(): void
	{
		$missions = FleetMissionAvailability::forTarget(
			$this->user(),
			$this->missionInfo([SHIP_BLACK_MOON => 1]),
			$this->enemyMoon()
		);

		$this->assertContains(FLEET_MISSION_DESTROY, $missions);
	}

	public function testDestroyMissionOfferedForDeathstarOnEnemyMoon(): void
	{
		$missions = FleetMissionAvailability::forTarget(
			$this->user(),
			$this->missionInfo([SHIP_DEATHSTAR => 1]),
			$this->enemyMoon()
		);

		$this->assertContains(FLEET_MISSION_DESTROY, $missions);
	}

	public function testDestroyMissionNotOfferedWithoutMoonDestroyer(): void
	{
		$missions = FleetMissionAvailability::forTarget(
			$this->user(),
			$this->missionInfo([202 => 10]),
			$this->enemyMoon()
		);

		$this->assertNotContains(FLEET_MISSION_DESTROY, $missions);
	}

	public function testDestroyMissionNotOfferedOnEnemyPlanet(): void
	{
		$missions = FleetMissionAvailability::forTarget(
			$this->user(),
			$this->missionInfo([SHIP_BLACK_MOON => 1], 1),
			$this->enemyMoon(['planet_type' => 1])
		);

		$this->assertNotContains(FLEET_MISSION_DESTROY, $missions);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function user(): array
	{
		return ['id' => 1, 'universe' => 1];
	}

	/**
	 * @param array<int, int> $ships
	 * @return array<string, mixed>
	 */
	private function missionInfo(array $ships, int $planetType = 3): array
	{
		return [
			'planet' => 9,
			'planettype' => $planetType,
			'galaxy' => 1,
			'system' => 50,
			'Ship' => $ships,
		];
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function enemyMoon(array $overrides = []): array
	{
		return array_merge([
			'id_owner' => 2,
			'planet_type' => 3,
		], $overrides);
	}

	private function defineModules(): void
	{
		$modules = [
			'MODULE_MISSION_EXPEDITION' => 30,
			'MODULE_MISSION_TRADE' => 44,
			'MODULE_MISSION_RECYCLE' => 32,
			'MODULE_MISSION_SALVAGE' => 47,
			'MODULE_MISSION_COLONY' => 35,
			'MODULE_MISSION_TRANSPORT' => 34,
			'MODULE_MISSION_SPY' => 24,
			'MODULE_MISSION_TRANSFER' => 45,
			'MODULE_MISSION_ATTACK' => 1,
			'MODULE_MISSION_HOLD' => 33,
			'MODULE_MISSION_STATION' => 36,
			'MODULE_MISSION_ACS' => 42,
			'MODULE_MISSION_DESTROY' => 29,
			'MODULE_MISSION_DARKMATTER' => 31,
		];

		foreach ($modules as $name => $value) {
			if (!defined($name)) {
				define($name, $value);
			}
		}
	}
}
