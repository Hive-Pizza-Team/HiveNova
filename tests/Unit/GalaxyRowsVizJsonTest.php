<?php

use HiveNova\Core\GalaxyRows;
use PHPUnit\Framework\TestCase;

/**
 * Validates GalaxyRows planet/moon vizJson payloads.
 */
class GalaxyRowsVizJsonTest extends TestCase
{
	protected function setUp(): void
	{
		if (!defined('FIELDS_BY_TERRAFORMER')) {
			define('FIELDS_BY_TERRAFORMER', 5);
		}
		if (!defined('FIELDS_BY_MOONBASIS_LEVEL')) {
			define('FIELDS_BY_MOONBASIS_LEVEL', 3);
		}
	}

	private function invokeBuildPlanetVizJson(array $galaxyRow, bool $ownPlanet, string $themePath): array
	{
		global $resource;

		$resource = $GLOBALS['resource'];

		$rows = new GalaxyRows();
		$ref = new ReflectionObject($rows);
		$prop = $ref->getProperty('galaxyRow');
		$prop->setAccessible(true);
		$prop->setValue($rows, $galaxyRow);

		$method = new ReflectionMethod(GalaxyRows::class, 'buildPlanetVizJson');
		$method->setAccessible(true);

		return json_decode($method->invoke($rows, $ownPlanet, $themePath), true);
	}

	private function invokeBuildMoonVizJson(array $galaxyRow, string $themePath): array
	{
		$rows = new GalaxyRows();
		$ref = new ReflectionObject($rows);
		$prop = $ref->getProperty('galaxyRow');
		$prop->setAccessible(true);
		$prop->setValue($rows, $galaxyRow);

		$method = new ReflectionMethod(GalaxyRows::class, 'buildMoonVizJson');
		$method->setAccessible(true);

		return json_decode($method->invoke($rows, $themePath), true);
	}

	public function testOwnPlanetUsesCalculateMaxPlanetFields(): void
	{
		$payload = $this->invokeBuildPlanetVizJson([
			'image'         => 'normaltempplanet03',
			'temp_min'      => 30,
			'temp_max'      => 70,
			'diameter'      => 12767,
			'field_current' => 42,
			'field_max'     => 163,
			'terraformer'   => 2,
			'galaxy'        => 1,
			'system'        => 88,
			'planet'        => 7,
			'der_metal'     => 0,
			'der_crystal'   => 0,
		], true, './styles/theme/hive/');

		$this->assertSame(['current' => 42, 'max' => 173], $payload['fields']);
		$this->assertArrayNotHasKey('vizState', $payload);
	}

	public function testOtherPlanetPayloadIsSparseUnknown(): void
	{
		$payload = $this->invokeBuildPlanetVizJson([
			'image'         => 'wasserplanet04',
			'temp_min'      => 20,
			'temp_max'      => 60,
			'diameter'      => 11800,
			'field_current' => 99,
			'field_max'     => 163,
			'terraformer'   => 4,
			'galaxy'        => 2,
			'system'        => 145,
			'planet'        => 9,
			'der_metal'     => 0,
			'der_crystal'   => 0,
		], false, './styles/theme/nova/');

		$this->assertSame(['current' => 0, 'max' => 0], $payload['fields']);
		$this->assertSame('unknown', $payload['vizState']);
		$this->assertSame([], (array) $payload['buildings']);
	}

	public function testMoonVizJsonIncludesMoonBaseBuilding(): void
	{
		$payload = $this->invokeBuildMoonVizJson([
			'm_temp_min'   => -50,
			'm_temp_max'   => -10,
			'm_diameter'   => 4200,
			'm_mondbasis'  => 3,
			'galaxy'       => 1,
			'system'       => 88,
			'planet'       => 7,
		], './styles/theme/hive/');

		$this->assertSame('mond', $payload['texture']);
		$this->assertSame(3, $payload['type']);
		$this->assertSame(3, $payload['buildings'][41]);
		$this->assertSame('unknown', $payload['vizState']);
	}
}
