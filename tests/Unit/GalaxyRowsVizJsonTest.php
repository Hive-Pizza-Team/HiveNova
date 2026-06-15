<?php

use HiveNova\Core\Config;
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

	private function invokeBuildPlanetVizJson(array $galaxyRow, bool $shareIntel, string $themePath, bool $galaxyPreview = false): string
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

		return $method->invoke($rows, $shareIntel, $themePath, $galaxyPreview);
	}

	private function invokeBuildMoonVizJson(array $galaxyRow, bool $shareIntel, string $themePath, bool $galaxyPreview = false): string
	{
		$rows = new GalaxyRows();
		$ref = new ReflectionObject($rows);
		$prop = $ref->getProperty('galaxyRow');
		$prop->setAccessible(true);
		$prop->setValue($rows, $galaxyRow);

		$method = new ReflectionMethod(GalaxyRows::class, 'buildMoonVizJson');
		$method->setAccessible(true);

		return $method->invoke($rows, $shareIntel, $themePath, $galaxyPreview);
	}

	private function invokeHasSharedPlanetVizIntel(array $galaxyRow, bool $ownPlanet, array $user): bool
	{
		$rows = new GalaxyRows();
		$ref = new ReflectionObject($rows);
		$galaxyRowProp = $ref->getProperty('galaxyRow');
		$galaxyRowProp->setAccessible(true);
		$galaxyRowProp->setValue($rows, $galaxyRow);

		$galaxyDataProp = $ref->getProperty('galaxyData');
		$galaxyDataProp->setAccessible(true);
		$galaxyDataProp->setValue($rows, [
			$galaxyRow['planet'] => ['ownPlanet' => $ownPlanet],
		]);

		global $USER;
		$USER = $user;

		$method = new ReflectionMethod(GalaxyRows::class, 'hasSharedPlanetVizIntel');
		$method->setAccessible(true);

		return (bool) $method->invoke($rows);
	}

	private function invokeGetColonizeSlotStatus(int $position): array
	{
		$rows = new GalaxyRows();
		$method = new ReflectionMethod(GalaxyRows::class, 'getColonizeSlotStatus');
		$method->setAccessible(true);

		return $method->invoke($rows, $position);
	}

	private function invokeBuildVizPayloadFromGalaxyRow(array $galaxyRow, string $type, array $user): ?array
	{
		global $USER;
		$USER = $user;

		$rows = new GalaxyRows();
		$method = new ReflectionMethod(GalaxyRows::class, 'buildVizPayloadFromGalaxyRow');
		$method->setAccessible(true);

		return $method->invoke($rows, $galaxyRow, $type, './styles/theme/hive/');
	}

	private function makeLazyVizGalaxyRow(array $overrides = []): array
	{
		return array_merge([
			'galaxy'        => 1,
			'system'        => 88,
			'planet'        => 7,
			'id'            => 501,
			'id_owner'      => 42,
			'name'          => 'Colony',
			'image'         => 'normaltempplanet03',
			'temp_min'      => 30,
			'temp_max'      => 70,
			'diameter'      => 12767,
			'field_current' => 55,
			'field_max'     => 163,
			'der_metal'     => 0,
			'der_crystal'   => 0,
			'buddy'         => 0,
			'ally_id'       => 0,
			'allyid'        => 0,
			'diploLevel'    => 0,
			'metal_mine'    => 12,
			'light_fighter' => 3,
		], $overrides);
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
		$this->assertTrue($payload['shareIntel']);
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

	public function testOtherPlanetPayloadIsSparsePublic(): void
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
		$this->assertFalse($payload['shareIntel']);
		$this->assertArrayNotHasKey('vizState', $payload);
		$this->assertSame([], (array) $payload['buildings']);
		$this->assertJsContractValidJson($json, ['sparse' => true]);
	}

	public function testFriendlyPlanetPayloadIncludesFieldsAndInventory(): void
	{
		$json = $this->invokeBuildPlanetVizJson([
			'image'          => 'normaltempplanet03',
			'temp_min'       => 30,
			'temp_max'       => 70,
			'diameter'       => 12767,
			'field_current'  => 55,
			'field_max'      => 163,
			'terraformer'    => 1,
			'metal_mine'     => 8,
			'light_fighter'  => 2,
			'galaxy'         => 1,
			'system'         => 88,
			'planet'         => 7,
			'der_metal'      => 0,
			'der_crystal'    => 0,
		], true, './styles/theme/hive/');
		$payload = $this->decodePayload($json);

		$this->assertSame(['current' => 55, 'max' => 168], $payload['fields']);
		$this->assertTrue($payload['shareIntel']);
		$this->assertSame(8, $payload['buildings'][1]);
		$this->assertSame(2, $payload['fleet'][202]);
		$this->assertJsContractValidJson($json);
	}

	public function testHasSharedPlanetVizIntelForBuddyAllianceAndDiploFriend(): void
	{
		$row = [
			'planet'    => 5,
			'id_owner'  => 42,
			'buddy'     => 1,
			'ally_id'   => 0,
		];
		$user = ['id' => 7, 'ally_id' => 0];

		$this->assertTrue($this->invokeHasSharedPlanetVizIntel($row, false, $user));

		$row['buddy'] = 0;
		$row['ally_id'] = 9;
		$user['ally_id'] = 9;
		$this->assertTrue($this->invokeHasSharedPlanetVizIntel($row, false, $user));

		$row['ally_id'] = 9;
		$user['ally_id'] = 3;
		$row['allyid'] = 99;
		$row['diploLevel'] = 1;
		$this->assertTrue($this->invokeHasSharedPlanetVizIntel($row, false, $user));

		$row['diploLevel'] = 0;
		$this->assertFalse($this->invokeHasSharedPlanetVizIntel($row, false, $user));
	}

	public function testFriendlyMoonVizJsonIncludesMoonBase(): void
	{
		$json = $this->invokeBuildMoonVizJson([
			'm_temp_min'   => -50,
			'm_temp_max'   => -10,
			'm_diameter'   => 4200,
			'm_mondbasis'  => 4,
			'galaxy'       => 1,
			'system'       => 88,
			'planet'       => 7,
		], true, './styles/theme/hive/');
		$payload = $this->decodePayload($json);

		$this->assertSame(4, $payload['buildings'][41]);
		$this->assertJsContractValidJson($json);
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

	public function testOtherMoonVizJsonOmitsVizStateAndInventory(): void
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

		$this->assertArrayNotHasKey('vizState', $payload);
		$this->assertFalse($payload['shareIntel']);
		$this->assertSame([], (array) $payload['buildings']);
		$this->assertJsContractValidJson($json, ['sparse' => true]);
	}

	public function testUncolonizedPlanetVizJsonUsesUnknownState(): void
	{
		$rows = new GalaxyRows();
		$json = $rows->buildUncolonizedPlanetVizJson(1, 88, 4, './styles/theme/hive/');
		$payload = $this->decodePayload($json);

		$this->assertSame('unknown', $payload['vizState']);
		$this->assertFalse($payload['shareIntel']);
		$this->assertSame(['current' => 0, 'max' => 0], $payload['fields']);
		$this->assertSame(1, $payload['galaxy']);
		$this->assertSame(88, $payload['system']);
		$this->assertSame(4, $payload['planet']);
		$this->assertJsContractValidJson($json);
	}

	private function makeColonizeConfig(array $overrides = []): Config
	{
		return new Config(array_merge([
			'uni'                 => 1,
			'max_planets'         => 15,
			'min_player_planets'  => 1,
			'planets_tech'        => 4,
			'planets_officier'    => 2,
			'planets_per_tech'    => 1,
		], $overrides));
	}

	public function testFillUncolonizedSlotsAddsUnknownEntriesWithoutOverwriting(): void
	{
		global $USER, $resource;

		Config::setInstance($this->makeColonizeConfig(), 1);
		$resource = array_replace($resource ?? [], [124 => 'astrophysics_tech']);
		$USER = [
			'universe'          => 1,
			'astrophysics_tech' => 8,
			'factor'            => ['Planets' => 0],
		];

		$rows = new GalaxyRows();
		$data = [
			3 => ['ownPlanet' => true, 'planet' => ['id' => 99]],
		];
		$rows->fillUncolonizedSlots($data, 5, 2, 145, './styles/theme/hive/');

		$this->assertArrayHasKey(1, $data);
		$this->assertTrue($data[1]['uncolonized']);
		$this->assertTrue($data[1]['canColonize']);
		$this->assertSame('unknown', $data[1]['planet']['image']);
		$this->assertArrayHasKey(3, $data);
		$this->assertArrayNotHasKey('uncolonized', $data[3]);
		$this->assertArrayHasKey(5, $data);
		$this->assertTrue($data[5]['uncolonized']);
		$this->assertTrue($data[5]['canColonize']);

		$USER['astrophysics_tech'] = 0;
		$USER['factor'] = ['Planets' => 0];
		$data = [];
		$rows->fillUncolonizedSlots($data, 15, 2, 145, './styles/theme/hive/');
		$this->assertFalse($data[1]['canColonize']);
		$this->assertFalse($data[8]['canColonize']);

		$USER['astrophysics_tech'] = 1;
		$data = [];
		$rows->fillUncolonizedSlots($data, 15, 2, 145, './styles/theme/hive/');
		$this->assertFalse($data[1]['canColonize']);
		$this->assertTrue($data[8]['canColonize']);
	}

	public function testHasSharedPlanetVizIntelRequiresAcceptedBuddyNotPendingRequest(): void
	{
		// Galaxy SQL excludes pending requests via NOT EXISTS on buddy_request, so buddy=0
		// is the row shape for both strangers and pending buddy requests.
		$row = [
			'planet'    => 5,
			'id_owner'  => 42,
			'buddy'     => 0,
			'ally_id'   => 0,
		];
		$user = ['id' => 7, 'ally_id' => 0];

		$this->assertFalse($this->invokeHasSharedPlanetVizIntel($row, false, $user));
	}

	public function testPlanetVizLazyPayloadStrangerOmitsInventory(): void
	{
		$payload = $this->invokeBuildVizPayloadFromGalaxyRow(
			$this->makeLazyVizGalaxyRow(),
			'planet',
			['id' => 7, 'ally_id' => 0]
		);

		$this->assertIsArray($payload);
		$this->assertFalse($payload['shareIntel']);
		$this->assertSame([], (array) $payload['buildings']);
		$this->assertSame([], (array) $payload['fleet']);
	}

	public function testPlanetVizLazyPayloadAcceptedBuddyIncludesInventory(): void
	{
		$payload = $this->invokeBuildVizPayloadFromGalaxyRow(
			$this->makeLazyVizGalaxyRow(['buddy' => 1]),
			'planet',
			['id' => 7, 'ally_id' => 0]
		);

		$this->assertIsArray($payload);
		$this->assertTrue($payload['shareIntel']);
		$this->assertSame(12, $payload['buildings'][1]);
		$this->assertSame(3, $payload['fleet'][202]);
	}

	public function testGetColonizeSlotStatusPrefersCapWhenBothBlock(): void
	{
		global $USER, $resource;

		Config::setInstance($this->makeColonizeConfig([
			'planets_tech' => 0,
		]), 1);
		$resource = array_replace($resource ?? [], [124 => 'astrophysics_tech']);
		$USER = [
			'universe'          => 1,
			'astrophysics_tech' => 0,
			'factor'            => ['Planets' => 0],
			'PLANETS'           => [
				['planet_type' => 1, 'destruyed' => 0],
			],
		];

		$status = $this->invokeGetColonizeSlotStatus(1);

		$this->assertFalse($status['canColonize']);
		$this->assertSame('cap', $status['colonizeBlockedReason']);
	}

	public function testFillUncolonizedSlotsBlocksWhenColonyCapReached(): void
	{
		global $USER, $resource;

		Config::setInstance($this->makeColonizeConfig([
			'planets_tech' => 0,
		]), 1);
		$resource = array_replace($resource ?? [], [124 => 'astrophysics_tech']);
		$USER = [
			'universe'          => 1,
			'astrophysics_tech' => 8,
			'factor'            => ['Planets' => 0],
			'PLANETS'           => [
				['planet_type' => 1, 'destruyed' => 0],
			],
		];

		$rows = new GalaxyRows();
		$data = [];
		$rows->fillUncolonizedSlots($data, 15, 2, 145, './styles/theme/hive/');

		$this->assertFalse($data[8]['canColonize']);
		$this->assertSame('cap', $data[8]['colonizeBlockedReason']);
	}

	public function testGalaxyPreviewPayloadOmitsMoonAndDefense(): void
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
			'rocket_launcher'=> 12,
			'm_id'           => 501,
			'm_name'         => 'Luna',
			'm_diameter'     => 4200,
			'galaxy'         => 1,
			'system'         => 88,
			'planet'         => 7,
			'der_metal'      => 0,
			'der_crystal'    => 0,
		], true, './styles/theme/hive/', true);
		$payload = $this->decodePayload($json);

		$this->assertNull($payload['moon']);
		$this->assertSame([], (array) $payload['defense']);
		$this->assertSame(10, $payload['buildings'][1]);
		$this->assertJsContractValidJson($json);
	}

	public function testResolveVizJsonRefForUncolonizedSlot(): void
	{
		$rows = new GalaxyRows();
		$payload = $rows->resolveVizJsonRef('slot:2:145:8', './styles/theme/hive/');

		$this->assertIsArray($payload);
		$this->assertSame('unknown', $payload['vizState']);
		$this->assertFalse($payload['shareIntel']);
		$this->assertSame(2, $payload['galaxy']);
		$this->assertSame(145, $payload['system']);
		$this->assertSame(8, $payload['planet']);
	}
}
