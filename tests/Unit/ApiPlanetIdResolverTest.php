<?php

use HiveNova\Core\ApiPlanetIdResolver;
use PHPUnit\Framework\TestCase;

class ApiPlanetIdResolverTest extends TestCase
{
	public function testOmittedPlanetIdKeepsSession(): void
	{
		$result = ApiPlanetIdResolver::fromRequest(null);
		$this->assertTrue($result['ok']);
		$this->assertNull($result['planetId']);
		$this->assertTrue(ApiPlanetIdResolver::requireOwned(null, 1)['ok']);
	}

	public function testInvalidPlanetIdIsUnprocessable(): void
	{
		foreach (['', 'abc', '0', '-1'] as $raw) {
			$result = ApiPlanetIdResolver::fromRequest($raw);
			$this->assertFalse($result['ok'], $raw);
			$this->assertSame(422, $result['status']);
			$this->assertSame('planet', $result['error']);
		}
	}

	public function testUnownedPlanetIdIsForbidden(): void
	{
		$resolved = ApiPlanetIdResolver::fromRequest('999999999');
		$this->assertTrue($resolved['ok']);
		$this->assertSame(999999999, $resolved['planetId']);

		$owned = ApiPlanetIdResolver::requireOwned(999999999, null);
		$this->assertFalse($owned['ok']);
		$this->assertSame(403, $owned['status']);
		$this->assertSame('planet', $owned['error']);
	}

	public function testOwnedPlanetIdIsAccepted(): void
	{
		$resolved = ApiPlanetIdResolver::fromRequest('42');
		$this->assertTrue($resolved['ok']);
		$this->assertTrue(ApiPlanetIdResolver::requireOwned(42, 42)['ok']);
	}
}
