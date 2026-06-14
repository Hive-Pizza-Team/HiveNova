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

	private function invokeBuildPlanetVizJson(array $galaxyRow, bool $ownPlanet, string $themePath): string
	{
		global $resource, $reslist;

		$resource = $GLOBALS['resource'];
		$reslist = $GLOBALS['reslist'];

		$rows = new GalaxyRows();
		$ref = new ReflectionObject($rows);
		$prop = $ref->getProperty('galaxyRow');
		$prop->setAccessible(true);
		$prop->setValue($rows, $galaxyRow);

		$method = new ReflectionMethod(GalaxyRows::class, 'buildPlanetVizJson');
		$method->setAccessible(true);

		return $method->invoke($rows, $ownPlanet, $themePath);
	}

	private function invokeBuildMoonVizJson(array $galaxyRow, bool $ownPlanet, string $themePath): string
	{
		$rows = new GalaxyRows();
		$ref = new ReflectionObject($rows);
		$prop = $ref->getProperty('galaxyRow');
		$prop->setAccessible(true);
		$prop->setValue($rows, $galaxyRow);

		$method = new ReflectionMethod(GalaxyRows::class, 'buildMoonVizJson');
		$method->setAccessible(true);

		return $method->invoke($rows, $ownPlanet, $themePath);
	}

	private function decodePayload(string $json): array
	{
		return json_decode($json, true);
	}

	private function assertJsContractValidJson(string $json, array $options = []): void
	{
		$root = dirname(__DIR__, 2);
		$cmd = sprintf(
			'node %s %s %s',
			escapeshellarg($root . '/tests/helpers/validate-payload.js'),
			escapeshellarg($json),
			escapeshellarg(json_encode($options, JSON_THROW_ON_ERROR))
		);

		exec($cmd, $output, $code);
		$this->assertSame(0, $code, implode("\n", $output));
		$result = json_decode(implode("\n", $output), true);
		$this->assertTrue($result['valid'], implode('; ', $result['errors'] ?? []));
	}

	public function testOwnPlanetUsesCalculateMaxPlanetFields(): void
	{
		$json = $this->invokeBuildPlanetVizJson([
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
		$payload = $this->decodePayload($json);

		$this->assertSame(['current' => 42, 'max' => 173], $payload['fields']);
		$this->assertArrayNotHasKey('vizState', $payload);
		$this->assertJsContractValidJson($json);
	}

	public function testOwnPlanetIncludesBuildingFleetDefenseMaps(): void
	{
		$json = $this->invokeBuildPlanetVizJson([
			'image'          => 'normaltempplanet03',
			'temp_min'       => 30,
			'temp_max'       => 70,
			'diameter'       => 12767,
			'field_current'  => 42,
			'field_max'      => 163,
			'terraformer'    => 0,
			'metal_mine'     => 10,
			'light_fighter'  => 4,
			'rocket_launcher'=> 12,
			'galaxy'         => 1,
			'system'         => 88,
			'planet'         => 7,
			'der_metal'      => 0,
			'der_crystal'    => 0,
		], true, './styles/theme/hive/');
		$payload = $this->decodePayload($json);

		$this->assertSame(10, $payload['buildings'][1]);
		$this->assertSame(4, $payload['fleet'][202]);
		$this->assertSame(12, $payload['defense'][401]);
		$this->assertJsContractValidJson($json);
	}

	public function testOtherPlanetPayloadIsSparseUnknown(): void
	{
		$json = $this->invokeBuildPlanetVizJson([
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
		$payload = $this->decodePayload($json);

		$this->assertSame(['current' => 0, 'max' => 0], $payload['fields']);
		$this->assertSame('unknown', $payload['vizState']);
		$this->assertSame([], (array) $payload['buildings']);
		$this->assertJsContractValidJson($json, ['sparse' => true]);
	}

	public function testOwnMoonVizJsonIncludesMoonBaseWithoutUnknownState(): void
	{
		$json = $this->invokeBuildMoonVizJson([
			'm_temp_min'   => -50,
			'm_temp_max'   => -10,
			'm_diameter'   => 4200,
			'm_mondbasis'  => 3,
			'galaxy'       => 1,
			'system'       => 88,
			'planet'       => 7,
		], true, './styles/theme/hive/');
		$payload = $this->decodePayload($json);

		$this->assertSame('mond', $payload['texture']);
		$this->assertSame(3, $payload['type']);
		$this->assertSame(3, $payload['buildings'][41]);
		$this->assertArrayNotHasKey('vizState', $payload);
		$this->assertJsContractValidJson($json);
	}

	public function testOtherMoonVizJsonUsesUnknownSpyState(): void
	{
		$json = $this->invokeBuildMoonVizJson([
			'm_temp_min'   => -50,
			'm_temp_max'   => -10,
			'm_diameter'   => 4200,
			'm_mondbasis'  => 3,
			'galaxy'       => 1,
			'system'       => 88,
			'planet'       => 7,
		], false, './styles/theme/hive/');
		$payload = $this->decodePayload($json);

		$this->assertSame('unknown', $payload['vizState']);
		$this->assertSame([], (array) $payload['buildings']);
		$this->assertJsContractValidJson($json, ['sparse' => true]);
	}
}
