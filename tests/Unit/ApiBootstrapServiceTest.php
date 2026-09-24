<?php

use HiveNova\Core\ApiBootstrapService;
use HiveNova\Core\OverviewPlanetActionService;
use PHPUnit\Framework\TestCase;

class ApiBootstrapServiceTest extends TestCase
{
	public function testOmitsSecretsAndKeepsAllowlist(): void
	{
		$service = new ApiBootstrapService();
		$payload = $service->build(
			[
				'id' => 9,
				'username' => 'pilot',
				'password' => 'secret',
				'email' => 'a@b.c',
				'authlevel' => AUTH_USR,
				'urlaubs_modus' => 0,
				'lang' => 'en',
				'timezone' => 'UTC',
				'messages' => 3,
			],
			planet: [
				'id' => 4,
				'name' => 'Home',
				'galaxy' => 1,
				'system' => 2,
				'planet' => 3,
				'planet_type' => 1,
				'image' => 'dune',
			],
			planets: [[
				'id' => 4,
				'name' => 'Home',
				'galaxy' => 1,
				'system' => 2,
				'planet' => 3,
				'planet_type' => 1,
				'image' => 'dune',
			]],
			resourceTable: [901 => ['name' => 'metal', 'current' => 10.5, 'max' => 100, 'production' => 1]],
			attackAlertCount: 2,
			cronjobIds: [7],
			modules: [1, 2],
			csrf: 'abc',
			serverTime: 1000,
			rev: 'rev1',
			gameClosed: false,
			seasonBlocked: false,
			gameName: 'HiveNova Test',
		);

		$json = json_encode($payload);
		$this->assertIsString($json);
		$this->assertStringNotContainsString('secret', $json);
		$this->assertStringNotContainsString('a@b.c', $json);
		$this->assertSame(9, $payload['user']['id']);
		$this->assertSame('pilot', $payload['user']['username']);
		$this->assertFalse($payload['user']['isStaff']);
		$this->assertSame(4, $payload['planet']['id']);
		$this->assertSame('abc', $payload['csrf']);
		$this->assertSame(2, $payload['attackAlertCount']);
		$this->assertSame([7], $payload['cronjobs']);
		$this->assertSame('HiveNova Test', $payload['gameName']);
		$this->assertSame('dune', $payload['planet']['image']);
	}

	public function testResourceTableAddsBasicIncomeAndEnergyUsed(): void
	{
		$config = (object) [
			'resource_multiplier' => 2,
			'metal_basic_income' => 20,
		];
		$table = ApiBootstrapService::resourceTable(
			['urlaubs_modus' => 0],
			[
				'planet_type' => 1,
				'metal' => 50,
				'metal_max' => 100,
				'metal_perhour' => 10,
				'energy' => 4,
				'energy_max' => 8,
				'energy_used' => -3,
			],
			[901 => 'metal', 911 => 'energy'],
			['resstype' => [1 => [901], 2 => [911]]],
			$config
		);
		$this->assertSame(50.0, $table[901]['production']);
		$this->assertSame(-3.0, $table[911]['production']);

		$withBits = ApiBootstrapService::resourceTable(
			['darkmatter' => 42],
			[],
			[921 => 'darkmatter'],
			['resstype' => [3 => [921]]],
			$config
		);
		$this->assertSame(42.0, $withBits[921]['current']);
		$this->assertSame('darkmatter', $withBits[921]['name']);

		$vacation = ApiBootstrapService::resourceTable(
			['urlaubs_modus' => 1],
			['planet_type' => 1, 'metal' => 1, 'metal_max' => 1, 'metal_perhour' => 5],
			[901 => 'metal'],
			['resstype' => [1 => [901]]],
			$config
		);
		$this->assertSame(5.0, $vacation[901]['production']);
	}

	public function testRenameAcceptsSimpleName(): void
	{
		$service = new OverviewPlanetActionService();
		$result = $service->validateRename('New Home', ['id' => 1, 'name' => 'Home']);
		$this->assertTrue($result['ok']);
	}

	public function testRenameRejectsSpecialCharacters(): void
	{
		$service = new OverviewPlanetActionService();
		$result = $service->validateRename('bad name!', ['id' => 1, 'name' => 'Home']);
		$this->assertFalse($result['ok']);
		$this->assertSame(OverviewPlanetActionService::ERROR_SPECIAL_CHAR, $result['error']);
	}

	public function testAbandonRejectsHomeWhenOnlyColony(): void
	{
		$service = new OverviewPlanetActionService();
		$result = $service->validateAbandon('Home', ['id_planet' => 1], ['id' => 1, 'name' => 'Home'], 0, 1);
		$this->assertFalse($result['ok']);
		$this->assertSame(OverviewPlanetActionService::ERROR_HOME, $result['error']);
	}

	public function testAbandonRejectsFleets(): void
	{
		$service = new OverviewPlanetActionService();
		$result = $service->validateAbandon('Colony', ['id_planet' => 1], ['id' => 2, 'name' => 'Colony'], 3, 2);
		$this->assertFalse($result['ok']);
		$this->assertSame(OverviewPlanetActionService::ERROR_FLEETS, $result['error']);
	}

	public function testOverviewSnapshot(): void
	{
		if (!defined('FIELDS_BY_TERRAFORMER')) {
			define('FIELDS_BY_TERRAFORMER', 5);
		}
		if (!defined('FIELDS_BY_MOONBASIS_LEVEL')) {
			define('FIELDS_BY_MOONBASIS_LEVEL', 3);
		}
		$service = new OverviewPlanetActionService();
		$payload = $service->snapshot(
			['username' => 'pilot', 'messages' => 4],
			[
				'id' => 4,
				'name' => 'Home',
				'galaxy' => 1,
				'system' => 2,
				'planet' => 3,
				'planet_type' => 1,
				'image' => 'wasserplanet02',
				'diameter' => 12800,
				'field_current' => 10,
				'field_max' => 163,
				'temp_min' => 10,
				'temp_max' => 40,
				'terraformer' => 0,
				'mondbasis' => 0,
			],
			[],
			[[
				'id' => 8,
				'name' => 'Luna',
				'galaxy' => 1,
				'system' => 2,
				'planet' => 3,
				'planet_type' => 3,
				'image' => 'mond',
			]]
		);
		$this->assertSame('pilot', $payload['username']);
		$this->assertSame('wasserplanet02', $payload['planet']['image']);
		$this->assertSame(163, $payload['planet']['fieldMax']);
		$this->assertNull($payload['queues']['building']);
		$this->assertSame(4, $payload['unreadMessages']);
		$this->assertSame('Luna', $payload['moon']['name']);
		$this->assertCount(1, $payload['colonies']);
	}

	public function testHangarHeadAndColonyCard(): void
	{
		$lng = ['tech' => [202 => 'Light Fighter', 1 => 'Metal Mine']];
		$hangar = OverviewPlanetActionService::hangarHead([
			'b_hangar_id' => serialize([[202, 5]]),
			'b_hangar' => 12,
		], $lng);
		$this->assertSame('Light Fighter', $hangar['name']);
		$this->assertSame(5, $hangar['count']);
		$card = OverviewPlanetActionService::colonyCard([
			'id' => 9,
			'name' => 'Outpost',
			'galaxy' => 1,
			'system' => 2,
			'planet' => 4,
			'planet_type' => 1,
			'image' => 'eisplanet02',
			'b_building' => TIMESTAMP + 40,
			'b_building_id' => serialize([[1, 8, 40, TIMESTAMP + 40, 'build']]),
		], $lng);
		$this->assertSame('Metal Mine', $card['building']);
		$this->assertNull(OverviewPlanetActionService::hangarHead(['b_hangar_id' => ''], $lng));
	}
}
