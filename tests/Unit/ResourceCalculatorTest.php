<?php

use HiveNova\Core\Config;
use HiveNova\Core\ResourceCalculator;
use PHPUnit\Framework\TestCase;

class ResourceCalculatorTest extends TestCase
{
	private function makeConfig(array $overrides = []): Config
	{
		return new Config(array_merge([
			'uni'                    => 1,
			'metal_basic_income'     => 20,
			'crystal_basic_income'   => 10,
			'deuterium_basic_income' => 0,
			'energy_basic_income'    => 0,
			'resource_multiplier'    => 1,
			'storage_multiplier'     => 1,
			'energySpeed'            => 1,
			'max_overflow'           => 2,
		], $overrides));
	}

	private function makeResource(): array
	{
		return [
			1   => 'metal_mine',
			2   => 'crystal_mine',
			3   => 'deuterium_sintetizer',
			4   => 'solar_plant',
			12  => 'fusion_plant',
			212 => 'solar_satelit',
			901 => 'metal',
			902 => 'crystal',
			903 => 'deuterium',
			911 => 'energy',
			113 => 'energy_tech',
			131 => 'plasma_tech_metal',
			132 => 'plasma_tech_crystal',
			133 => 'plasma_tech_deuterium',
		];
	}

	private function makeReslist(array $prod): array
	{
		return [
			'prod'     => $prod,
			'storage'  => [],
			'resstype' => [1 => [901, 902, 903], 2 => [911]],
		];
	}

	private function makeUser(array $overrides = []): array
	{
		return array_merge([
			'id'                    => 1,
			'universe'              => 1,
			'factor'                => [
				'Resource'        => 0,
				'Energy'          => 0,
				'ResourceStorage' => 0,
			],
			'energy_tech'           => 0,
			'plasma_tech_metal'     => 0,
			'plasma_tech_crystal'   => 0,
			'plasma_tech_deuterium' => 0,
		], $overrides);
	}

	private function makePlanet(array $overrides = []): array
	{
		return array_merge([
			'planet_type'                    => 1,
			'planet'                         => 8,
			'temp_max'                       => 40,
			'metal'                          => 0,
			'crystal'                        => 0,
			'deuterium'                      => 0,
			'metal_mine'                     => 1,
			'metal_mine_porcent'             => 10,
			'crystal_mine'                   => 1,
			'crystal_mine_porcent'           => 10,
			'deuterium_sintetizer'           => 1,
			'deuterium_sintetizer_porcent'   => 10,
			'solar_plant'                    => 1,
			'solar_plant_porcent'            => 10,
			'fusion_plant'                   => 0,
			'fusion_plant_porcent'           => 10,
			'solar_satelit'                  => 1,
			'solar_satelit_porcent'          => 10,
		], $overrides);
	}

	private function calculator(array $user, array $planet, array $resource, array $reslist, Config $config): ResourceCalculator
	{
		return new ResourceCalculator($user, $planet, $config, $resource, $reslist, 3600);
	}

	public function testSlotEightBoostsMetalPlusBeforeOfficer(): void
	{
		$GLOBALS['ProdGrid'] = [
			1 => ['production' => [901 => '1000', 911 => '-100']],
			4 => ['production' => [911 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([1, 4]);
		$config   = $this->makeConfig();
		$user     = $this->makeUser();

		$slot8 = $this->calculator($user, $this->makePlanet(['planet' => 8]), $resource, $reslist, $config);
		$slot8->reBuildCache();
		$slot4 = $this->calculator($user, $this->makePlanet(['planet' => 4]), $resource, $reslist, $config);
		$slot4->reBuildCache();

		$this->assertEqualsWithDelta(1350.0, $slot8->getPlanet()['metal_perhour'], 0.001);
		$this->assertEqualsWithDelta(1000.0, $slot4->getPlanet()['metal_perhour'], 0.001);
	}

	public function testOfficerAppliesAfterSlotBonus(): void
	{
		$GLOBALS['ProdGrid'] = [
			1 => ['production' => [901 => '1000', 911 => '-100']],
			4 => ['production' => [911 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([1, 4]);
		$config   = $this->makeConfig();
		$user     = $this->makeUser(['factor' => [
			'Resource' => 0.10,
			'Energy' => 0,
			'ResourceStorage' => 0,
		]]);
		$calc = $this->calculator($user, $this->makePlanet(['planet' => 8]), $resource, $reslist, $config);
		$calc->reBuildCache();

		// 1000 * 1.35 * (1 + 0.10) = 1485
		$this->assertEqualsWithDelta(1485.0, $calc->getPlanet()['metal_perhour'], 0.001);
	}

	public function testUraniumIgnoresSlotWhenTemperatureMatches(): void
	{
		$GLOBALS['ProdGrid'] = [
			3 => ['production' => [
				903 => '(10 * $BuildLevel * pow(1.1, $BuildLevel) * (-0.002 * $BuildTemp + 1.28) * (0.1 * $BuildLevelFactor))',
				911 => '-30',
			]],
			4 => ['production' => [911 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([3, 4]);
		$config   = $this->makeConfig();
		$user     = $this->makeUser();
		$temp     = 40;

		$inner = $this->calculator($user, $this->makePlanet(['planet' => 1, 'temp_max' => $temp]), $resource, $reslist, $config);
		$inner->reBuildCache();
		$outer = $this->calculator($user, $this->makePlanet(['planet' => 15, 'temp_max' => $temp]), $resource, $reslist, $config);
		$outer->reBuildCache();

		$this->assertEqualsWithDelta(
			$inner->getPlanet()['deuterium_perhour'],
			$outer->getPlanet()['deuterium_perhour'],
			0.0001
		);
	}

	public function testDeuteriumFollowsTwoMoonsTemperatureKernel(): void
	{
		$GLOBALS['ProdGrid'] = [
			3 => ['production' => [
				903 => '(10 * $BuildLevel * pow(1.1, $BuildLevel) * (-0.002 * $BuildTemp + 1.28) * (0.1 * $BuildLevelFactor))',
				911 => '-30',
			]],
			4 => ['production' => [911 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([3, 4]);
		$config   = $this->makeConfig();
		$user     = $this->makeUser();

		$hot = $this->calculator($user, $this->makePlanet(['planet' => 8, 'temp_max' => 240]), $resource, $reslist, $config);
		$hot->reBuildCache();
		$cold = $this->calculator($user, $this->makePlanet(['planet' => 8, 'temp_max' => -110]), $resource, $reslist, $config);
		$cold->reBuildCache();

		$level = 1;
		$factor = 10;
		$hotExpected = 10 * $level * (1.1 ** $level) * (-0.002 * 240 + 1.28) * (0.1 * $factor);
		$coldExpected = 10 * $level * (1.1 ** $level) * (-0.002 * -110 + 1.28) * (0.1 * $factor);

		$this->assertEqualsWithDelta($hotExpected, $hot->getPlanet()['deuterium_perhour'], 0.001);
		$this->assertEqualsWithDelta($coldExpected, $cold->getPlanet()['deuterium_perhour'], 0.001);
	}

	public function testSatelliteEnergyFollowsTemperatureNotSlot(): void
	{
		$GLOBALS['ProdGrid'] = [
			212 => ['production' => [
				911 => '(($BuildTemp + 160) / 6) * (0.1 * $BuildLevelFactor) * $BuildLevel',
			]],
			1 => ['production' => [901 => '1000', 911 => '-1']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([212, 1]);
		$config   = $this->makeConfig();
		$user     = $this->makeUser();

		$hotSlot1 = $this->calculator($user, $this->makePlanet(['planet' => 1, 'temp_max' => 240, 'solar_satelit' => 1]), $resource, $reslist, $config);
		$hotSlot1->reBuildCache();
		$hotSlot15 = $this->calculator($user, $this->makePlanet(['planet' => 15, 'temp_max' => 240, 'solar_satelit' => 1]), $resource, $reslist, $config);
		$hotSlot15->reBuildCache();
		$cold = $this->calculator($user, $this->makePlanet(['planet' => 1, 'temp_max' => -110, 'solar_satelit' => 1]), $resource, $reslist, $config);
		$cold->reBuildCache();

		$hotEnergy = round((240 + 160) / 6);
		$coldEnergy = round((-110 + 160) / 6);

		$this->assertEqualsWithDelta($hotEnergy, $hotSlot1->getPlanet()['energy'], 0.001);
		$this->assertEqualsWithDelta($hotEnergy, $hotSlot15->getPlanet()['energy'], 0.001);
		$this->assertEqualsWithDelta($coldEnergy, $cold->getPlanet()['energy'], 0.001);
	}

	public function testEnergyShortageScalesBoostedPlus(): void
	{
		$GLOBALS['ProdGrid'] = [
			1 => ['production' => [901 => '1000', 911 => '-200']],
			4 => ['production' => [911 => '100']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([1, 4]);
		$config   = $this->makeConfig();
		$calc = $this->calculator($this->makeUser(), $this->makePlanet(['planet' => 8]), $resource, $reslist, $config);
		$calc->reBuildCache();

		// prodLevel = 100/200 = 0.5; 1000 * 1.35 * 0.5 = 675
		$this->assertEqualsWithDelta(675.0, $calc->getPlanet()['metal_perhour'], 0.001);
	}

	public function testZeroEnergyUseZerosPerHour(): void
	{
		$GLOBALS['ProdGrid'] = [
			1 => ['production' => [901 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([1]);
		$config   = $this->makeConfig();
		$calc = $this->calculator($this->makeUser(), $this->makePlanet(['planet' => 8]), $resource, $reslist, $config);
		$calc->reBuildCache();

		$this->assertEquals(0, $calc->getPlanet()['metal_perhour']);
	}

	public function testMoonDoesNotGetSlotMetalBonus(): void
	{
		$GLOBALS['ProdGrid'] = [
			1 => ['production' => [901 => '1000', 911 => '-100']],
			4 => ['production' => [911 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([1, 4]);
		$config   = $this->makeConfig();
		$calc = $this->calculator(
			$this->makeUser(),
			$this->makePlanet(['planet' => 8, 'planet_type' => 3]),
			$resource,
			$reslist,
			$config
		);
		$calc->reBuildCache();

		$this->assertEqualsWithDelta(1000.0, $calc->getPlanet()['metal_perhour'], 0.001);
	}

	public function testExecCalcDoesNotMultiplyBasicIncomeBySlot(): void
	{
		$GLOBALS['ProdGrid'] = [
			1 => ['production' => [901 => '0', 911 => '-100']],
			4 => ['production' => [911 => '1000']],
			22 => ['storage' => [901 => '100000', 902 => '100000', 903 => '100000']],
		];
		$resource = $this->makeResource() + [22 => 'metal_store'];
		$reslist  = $this->makeReslist([1, 4]);
		$reslist['storage'] = [22];
		$config   = $this->makeConfig(['metal_basic_income' => 20]);
		$planet   = $this->makePlanet(['planet' => 8, 'metal' => 0, 'metal_store' => 1]);
		$calc     = $this->calculator($this->makeUser(), $planet, $resource, $reslist, $config);
		$calc->reBuildCache();
		$calc->execCalc();

		$this->assertEqualsWithDelta(20.0, $calc->getPlanet()['metal'], 0.001);
	}

	public function testSlotThreeBoostsSilicon(): void
	{
		$GLOBALS['ProdGrid'] = [
			2 => ['production' => [902 => '1000', 911 => '-100']],
			4 => ['production' => [911 => '1000']],
		];
		$resource = $this->makeResource();
		$reslist  = $this->makeReslist([2, 4]);
		$config   = $this->makeConfig();
		$calc = $this->calculator($this->makeUser(), $this->makePlanet(['planet' => 3]), $resource, $reslist, $config);
		$calc->reBuildCache();

		$this->assertEqualsWithDelta(1200.0, $calc->getPlanet()['crystal_perhour'], 0.001);
	}
}
