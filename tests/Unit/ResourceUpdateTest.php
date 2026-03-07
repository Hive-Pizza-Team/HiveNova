<?php

use PHPUnit\Framework\TestCase;

class ResourceUpdateTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Fixtures
	// -----------------------------------------------------------------------

	private function makeConfig(array $overrides = []): Config
	{
		$data = array_merge([
			'uni'                    => 1,
			'metal_basic_income'     => 0,
			'crystal_basic_income'   => 0,
			'deuterium_basic_income' => 0,
			'energy_basic_income'    => 0,
			'resource_multiplier'    => 1,
			'storage_multiplier'     => 1,
			'energySpeed'            => 1,
			'max_overflow'           => 2,
		], $overrides);
		return new Config($data);
	}

	private function makeResource(): array
	{
		return [
			901 => 'metal',
			902 => 'crystal',
			903 => 'deuterium',
			911 => 'energy',
			921 => 'darkmatter',
			22  => 'solar_plant',
			23  => 'fusion_reactor',
			24  => 'solar_satellite',
			31  => 'intergalactic_research',
			113 => 'energy_tech',
			123 => 'intergalactic_research',
			131 => 'plasma_tech',
			132 => 'graviton_tech',
			133 => 'laser_tech',
		];
	}

	private function makeReslist(): array
	{
		return [
			'prod'     => [],
			'storage'  => [],
			'resstype' => [1 => [901, 902, 903], 2 => [911]],
			'one'      => [],
		];
	}

	private function makeUser(array $overrides = []): array
	{
		return array_merge([
			'id'                        => 1,
			'universe'                  => 1,
			'urlaubs_modus'             => 0,
			'onlinetime'                => PHP_INT_MAX,
			'hof'                       => 0,
			'darkmatter'                => 0,
			'b_tech'                    => 0,
			'b_tech_id'                 => 0,
			'b_tech_planet'             => 0,
			'b_tech_queue'              => '',
			'factor'                    => [
				'Resource'        => 0,
				'Energy'          => 0,
				'ResourceStorage' => 0,
			],
			'plasma_tech'               => 0,
			'graviton_tech'             => 0,
			'laser_tech'                => 0,
			'energy_tech'               => 0,
			'intergalactic_research'    => 0,
		], $overrides);
	}

	private function makePlanet(array $overrides = []): array
	{
		return array_merge([
			'id'                => 1,
			'planet_type'       => 1,
			'metal'             => 0,
			'crystal'           => 0,
			'deuterium'         => 0,
			'energy'            => 0,
			'energy_used'       => 0,
			'metal_perhour'     => 0,
			'crystal_perhour'   => 0,
			'deuterium_perhour' => 0,
			'metal_max'         => 100000,
			'crystal_max'       => 100000,
			'deuterium_max'     => 100000,
			'last_update'       => 1000000,
			'eco_hash'          => '',
			'b_building'        => 0,
			'b_building_id'     => '',
			'b_hangar_id'       => '',
			'b_hangar'          => 0,
			'field_current'     => 0,
			'temp_max'          => 30,
			'solar_plant'       => 0,
			'solar_plant_porcent'         => 100,
			'fusion_reactor'              => 0,
			'fusion_reactor_porcent'      => 100,
			'solar_satellite'             => 0,
			'solar_satellite_porcent'     => 100,
		], $overrides);
	}

	/**
	 * Build a ResourceUpdate with pre-computed hash so UpdateResource skips
	 * ReBuildCache and exercises only ExecCalc.
	 *
	 * We call CalcResource() at t=last_update (ProductionTime=0, no-op) solely
	 * to let the class initialise $this->config via Config::get().  After that
	 * we stamp the matching eco_hash so the subsequent UpdateResource call
	 * with a future timestamp will bypass ReBuildCache and go straight to
	 * ExecCalc with the pre-set metal_perhour / crystal_perhour values.
	 */
	private function makeEcoWithMatchingHash(array $user, array &$planet, array $resource, array $reslist, Config $config): ResourceUpdate
	{
		Config::setInstance($config, 1);

		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);

		// Zero-duration CalcResource call: initialises $this->config without
		// changing any planet values (ProductionTime = 0).
		$eco->CalcResource($user, $planet, false, $planet['last_update'], true);

		// Pre-compute hash while config is live; stamp it on the planet so
		// the next UpdateResource call finds a matching hash and skips
		// ReBuildCache.
		$planet['eco_hash'] = $eco->CreateHash();
		$eco->setData($user, $planet);

		return $eco;
	}

	protected function setUp(): void
	{
		// Reset the Config singleton instances between tests
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	public function testMetalAccumulatesOverTime(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet(['metal_perhour' => 3600]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);

		// 3 600 seconds of production at 3 600 metal/h => 3 600 metal
		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		$this->assertEquals(3600.0, $updated['metal']);
	}

	public function testResourceCappedAtStorageMax(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		// max_overflow = 2, so cap = metal_max * 2 = 200 000
		$config = $this->makeConfig(['max_overflow' => 2]);
		$user   = $this->makeUser();
		$planet = $this->makePlanet([
			'metal'         => 195000,
			'metal_max'     => 100000,
			'metal_perhour' => 36000,   // would overshoot cap
		]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);

		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		$this->assertEquals(200000.0, $updated['metal']);
	}

	public function testNegativeProductionDepletes(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet([
			'deuterium'         => 1000,
			'deuterium_perhour' => -3600,   // loses 3600/h
		]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);

		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		// 1000 - 3600 < 0, so deuterium is clamped to 0
		$this->assertEquals(0.0, $updated['deuterium']);
	}

	public function testVacationModeSkipsCalculation(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		Config::setInstance($this->makeConfig(), 1);

		$user   = $this->makeUser(['urlaubs_modus' => 1, 'metal_perhour' => 9999]);
		$planet = $this->makePlanet(['metal' => 500, 'metal_perhour' => 9999]);

		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);

		$result = $eco->CalcResource($user, $planet, false, $planet['last_update'] + 3600);

		[, $returned] = $result;
		$this->assertEquals(500, $returned['metal'], 'Vacation mode must leave metal unchanged');
	}

	public function testHashChangesWhenBuildingLevelChanges(): void
	{
		$resource = $this->makeResource();
		// Include solar_plant (22) in the production list so its level is hashed
		$reslist = array_merge($this->makeReslist(), ['prod' => [22]]);
		$config  = $this->makeConfig();
		Config::setInstance($config, 1);

		$user    = $this->makeUser();
		$planet1 = $this->makePlanet(['solar_plant' => 0]);
		$planet2 = $this->makePlanet(['solar_plant' => 5]);

		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);

		$eco->setData($user, $planet1);
		$hash1 = $eco->CreateHash();

		$eco->setData($user, $planet2);
		$hash2 = $eco->CreateHash();

		$this->assertNotEquals($hash1, $hash2, 'Hash must differ when a building level changes');
	}
}
