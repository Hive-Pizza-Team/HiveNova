<?php

use HiveNova\Core\Config;
use HiveNova\Core\ResourceUpdate;

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
			'game_speed'             => 1,
			'min_build_time'         => 0,
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
			14  => 'robotic_factory',
			15  => 'nanite_factory',
			22  => 'solar_plant',
			23  => 'fusion_reactor',
			24  => 'solar_satellite',
			31  => 'intergalactic_research',
			33  => 'terraformer',
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
			'build'    => [1, 2, 3, 4, 6, 12, 14, 15, 21, 22, 23, 24, 31, 33, 34],
			'fleet'    => [],
			'defense'  => [],
			'missile'  => [],
			'tech'     => [],
			'resstype' => [1 => [901, 902, 903], 2 => [911]],
			'one'      => [],
			'ressources' => [901, 902, 903, 911, 921],
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
				'BuildTime'       => 0,
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
			'name'              => 'PR3',
			'galaxy'            => 4,
			'system'            => 80,
			'planet'            => 13,
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
			'terraformer'       => 0,
			'robotic_factory'   => 0,
			'nanite_factory'    => 0,
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

	public function testCrystalAccumulatesIndependentlyOfMetal(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet([
			'metal_perhour'   => 0,      // no metal production
			'crystal_perhour' => 7200,   // 7200 crystal/h
		]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		$this->assertEquals(0.0, $updated['metal'],   'Metal must stay zero with no production');
		$this->assertEquals(7200.0, $updated['crystal'], '1 h of 7200/h = 7200 crystal');
	}

	public function testProductionAddsToExistingResourceAmount(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet([
			'metal'         => 5000,
			'metal_perhour' => 3600,
		]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		// Started with 5000, produced 3600 in 1 h → 8600
		$this->assertEquals(8600.0, $updated['metal']);
	}

	public function testZeroProductionRateLeavesResourceUnchanged(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet(['metal' => 1234, 'metal_perhour' => 0]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		$this->assertEquals(1234.0, $updated['metal']);
	}

	public function testDeuteriumAccumulatesOverTwoHours(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet(['deuterium_perhour' => 1800]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 7200, true);
		[, $updated] = $eco->getData();

		$this->assertEquals(3600.0, $updated['deuterium'], '2 h × 1800/h = 3600');
	}

	public function testResourceCannotGoBelowZeroWithNegativeProduction(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet([
			'metal'         => 100,
			'metal_perhour' => -7200,  // drains 7200/h but only 100 present
		]);

		$eco = $this->makeEcoWithMatchingHash($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 3600, true);
		[, $updated] = $eco->getData();

		$this->assertGreaterThanOrEqual(0.0, $updated['metal'], 'Metal must not go negative');
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

	// -----------------------------------------------------------------------
	// ReBuildCache path (HASH=false forces ReBuildCache to run)
	// -----------------------------------------------------------------------

	/**
	 * Build an eco object with data set but NO hash stamped, so that calling
	 * UpdateResource(time, false) forces ReBuildCache to execute.
	 */
	private function makeEcoWithForcedRebuild(array $user, array $planet, array $resource, array $reslist, Config $config): ResourceUpdate
	{
		Config::setInstance($config, 1);
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);
		$eco->setData($user, $planet);
		return $eco;
	}

	public function testReBuildCacheRunsWhenHashIsFalse(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet(['metal' => 5000]);

		$eco = $this->makeEcoWithForcedRebuild($user, $planet, $resource, $reslist, $config);

		// HASH=false → ReBuildCache is called unconditionally; with empty prod,
		// per-hour values are reset to 0 but no exception should be thrown
		$eco->UpdateResource($planet['last_update'] + 3600, false);
		[, $updated] = $eco->getData();

		// With prod=[], ReBuildCache sets per-hour to 0, and metal stays at 5000
		// (ExecCalc: 0 production added, pre-existing metal unchanged)
		$this->assertEquals(5000.0, $updated['metal']);
	}

	public function testReBuildCacheSetsPerHourToZeroWithEmptyProdGrid(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();   // prod => []
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet();

		$eco = $this->makeEcoWithForcedRebuild($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 7200, false);
		[, $updated] = $eco->getData();

		// No production buildings → per-hour values should be 0
		$this->assertEquals(0.0, $updated['metal_perhour']);
		$this->assertEquals(0.0, $updated['crystal_perhour']);
		$this->assertEquals(0.0, $updated['deuterium_perhour']);
	}

	public function testReBuildCacheMoonPlanetSkipsMineIncome(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig(['metal_basic_income' => 100]);
		$user     = $this->makeUser();
		// planet_type=3 is a moon; basic income should be zeroed out
		$planet   = $this->makePlanet(['planet_type' => 3]);

		$eco = $this->makeEcoWithForcedRebuild($user, $planet, $resource, $reslist, $config);
		$eco->UpdateResource($planet['last_update'] + 3600, false);
		[, $updated] = $eco->getData();

		// For moons, metal_basic_income is set to 0 in ReBuildCache
		$this->assertEquals(0.0, $updated['metal'], 'Moon must not receive basic metal income');
	}

	public function testUpdateResourceWithHashFalseDoesNotChangePlanetWithNoTime(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet(['metal' => 9999]);

		$eco = $this->makeEcoWithForcedRebuild($user, $planet, $resource, $reslist, $config);

		// Same timestamp → ProductionTime=0 → UpdateResource is a no-op
		$eco->UpdateResource($planet['last_update'], false);
		[, $updated] = $eco->getData();

		$this->assertEquals(9999.0, $updated['metal']);
	}

	public function testGetProdWithoutElementReturnsPrefixedString(): void
	{
		$result = ResourceUpdate::getProd('20 * $BuildLevel', false);
		$this->assertSame('return 20 * $BuildLevel;', $result);
	}

	public function testGetProdEmptyStringWithoutElement(): void
	{
		$result = ResourceUpdate::getProd('0', false);
		$this->assertSame('return 0;', $result);
	}

	public function testCalcResourceWithFalsePlanetDoesNotError(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet();

		Config::setInstance($config, 1);
		$eco = new ResourceUpdate(true, false);
		$eco->setResourceData($resource, $reslist);

		$result = $eco->CalcResource($user, false, false, $planet['last_update'] + 3600);

		$this->assertIsArray($result);
		$this->assertSame($user, $result[0]);
	}

	public function testReturnVarsReturnsArrayWhenNotGlobalMode(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$planet   = $this->makePlanet(['metal' => 42]);

		Config::setInstance($config, 1);
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);
		$eco->setData($user, $planet);

		// CalcResource with explicit $USER/$PLANET sets isGlobalMode=false
		// ReturnVars() should return [USER, PLANET] array
		$result = $eco->CalcResource($user, $planet, false, $planet['last_update']);
		$this->assertIsArray($result);
		$this->assertCount(2, $result);
	}

	/**
	 * Player report (10 Jul 2026): Terraformer queued on PR3 with enough listed
	 * resources (556/200002/400079) and 4119 max energy vs 4000 required, but
	 * SetNextQueueElementOnTop rejected the build as "Impossible to build".
	 *
	 * With matching amounts the queue item must start and deduct silicon/uranium.
	 */
	public function testSetNextQueueStartsTerraformerWithReportedResources(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser(['hof' => 0]);

		$queue = [[33, 3, 0, 1000000, 'build']];
		$planet = $this->makePlanet([
			'terraformer'    => 2,
			'metal'          => 556,
			'crystal'        => 200002,
			'deuterium'      => 400079,
			'energy'         => 4119,
			'b_building'     => 1000000,
			'b_building_id'  => serialize($queue),
		]);

		Config::setInstance($config, 1);
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);
		$eco->setData($user, $planet);

		$this->assertTrue($eco->SetNextQueueElementOnTop());

		[, $updated] = $eco->getData();

		$this->assertNotSame('', $updated['b_building_id'], 'Queue must keep the Terraformer once started');
		$this->assertGreaterThan(1000000, $updated['b_building'], 'Build end time must be set in the future');
		$this->assertEquals(556.0, $updated['metal']);
		$this->assertEquals(2.0, $updated['crystal'], '200002 - 200000 silicon');
		$this->assertEquals(79.0, $updated['deuterium'], '400079 - 400000 uranium');
	}

	/**
	 * Same report scenario but energy production below the Terraformer cost:
	 * metal/silicon/uranium look fine in the base message, yet the queue
	 * item is dropped because isElementBuyable also checks energy (911).
	 * formatNotEnoughResourcesMessage appends the energy shortfall.
	 */
	public function testSetNextQueueDropsTerraformerWhenEnergyTooLow(): void
	{
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist();
		$config   = $this->makeConfig();
		$user     = $this->makeUser(['hof' => 0]);

		$queue = [[33, 3, 0, 1000000, 'build']];
		$planet = $this->makePlanet([
			'terraformer'    => 2,
			'metal'          => 556,
			'crystal'        => 200002,
			'deuterium'      => 400079,
			'energy'         => 3999,
			'b_building'     => 1000000,
			'b_building_id'  => serialize($queue),
		]);

		Config::setInstance($config, 1);
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($resource, $reslist);
		$eco->setData($user, $planet);

		$this->assertTrue($eco->SetNextQueueElementOnTop());

		[, $updated] = $eco->getData();

		$this->assertSame('', $updated['b_building_id'], 'Queue item must be removed when energy blocks the build');
		$this->assertSame(0, $updated['b_building']);
		$this->assertEquals(200002.0, $updated['crystal'], 'Resources must not be deducted on failure');
		$this->assertEquals(400079.0, $updated['deuterium']);
	}

	public function testFormatNotEnoughResourcesMessageIncludesEnergyWhenCostRequiresIt(): void
	{
		$GLOBALS['LNG'] = [
			'sys_notenough_money'        => 'RES %s %d [%d:%d:%d] %s | have %s %s , %s %s and %s %s | need %s %s , %s %s and %s %s',
			'sys_notenough_money_energy' => ' | energy have %s %s need %s %s',
			'tech' => [
				33  => 'Terraformer',
				901 => 'Metal',
				902 => 'Silicon',
				903 => 'Uranium',
				911 => 'Energy',
			],
		];

		$planet = [
			'name'      => 'PR3',
			'id'        => 42,
			'galaxy'    => 4,
			'system'    => 80,
			'planet'    => 13,
			'metal'     => 556,
			'crystal'   => 200002,
			'deuterium' => 400079,
			'energy'    => 3999,
		];
		$cost = [901 => 0, 902 => 200000, 903 => 400000, 911 => 4000];

		$message = ResourceUpdate::formatNotEnoughResourcesMessage($planet, 33, $cost);

		$this->assertStringContainsString('Terraformer', $message);
		$this->assertStringContainsString('energy have', $message);
		$this->assertStringContainsString('Energy', $message);
		$this->assertStringContainsString('3.999', $message);
		$this->assertStringContainsString('4.000', $message);
	}

	public function testFormatNotEnoughResourcesMessageOmitsEnergyWhenNotInCost(): void
	{
		$GLOBALS['LNG'] = [
			'sys_notenough_money'        => 'RES %s %d [%d:%d:%d] %s | have %s %s , %s %s and %s %s | need %s %s , %s %s and %s %s',
			'sys_notenough_money_energy' => ' | energy have %s %s need %s %s',
			'tech' => [
				1   => 'Metal Mine',
				901 => 'Metal',
				902 => 'Silicon',
				903 => 'Uranium',
				911 => 'Energy',
			],
		];

		$planet = [
			'name'      => 'Home',
			'id'        => 1,
			'galaxy'    => 1,
			'system'    => 1,
			'planet'    => 1,
			'metal'     => 10,
			'crystal'   => 10,
			'deuterium' => 10,
			'energy'    => 100,
		];
		$cost = [901 => 60, 902 => 15];

		$message = ResourceUpdate::formatNotEnoughResourcesMessage($planet, 1, $cost);

		$this->assertStringNotContainsString('energy have', $message);
		$this->assertStringNotContainsString('Energy', $message);
	}
}
