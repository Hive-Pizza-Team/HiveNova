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
}
