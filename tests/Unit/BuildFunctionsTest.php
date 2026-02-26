<?php

use PHPUnit\Framework\TestCase;

class BuildFunctionsTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Fixtures
	// -----------------------------------------------------------------------

	private function makeConfig(array $overrides = []): Config
	{
		$data = array_merge([
			'uni'              => 1,
			'game_speed'       => 1,
			'min_build_time'   => 0,
			'factor_university'=> 0,
		], $overrides);
		return new Config($data);
	}

	private function makeUser(array $overrides = []): array
	{
		return array_merge([
			'id'                     => 1,
			'universe'               => 1,
			'robotic_factory'        => 0,
			'nanite_factory'         => 0,
			'hangar'                 => 0,
			'research_lab'           => 0,
			'intergalactic_research' => 0,
			'combustion_tech'        => 0,
			'impulse_motor_tech'     => 0,
			'hyperspace_motor_tech'  => 0,
			'light_fighter'          => 0,
			'factor'                 => [
				'BuildTime'     => 0,
				'ResearchTime'  => 0,
				'ShipTime'      => 0,
				'DefensiveTime' => 0,
			],
		], $overrides);
	}

	private function makePlanet(array $overrides = []): array
	{
		return array_merge([
			'robotic_factory'         => 0,
			'nanite_factory'          => 0,
			'hangar'                  => 0,
			'research_lab'            => 0,
			'intergalactic_research'  => 0,
			'intergalactic_research_inter' => 0,
			'metal'                   => 100000,
			'crystal'                 => 100000,
			'deuterium'               => 100000,
			'solar_plant'             => 0,
			'light_fighter'           => 0,
		], $overrides);
	}

	protected function setUp(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
	}

	// -----------------------------------------------------------------------
	// isTechnologieAccessible
	// -----------------------------------------------------------------------

	public function testElementWithNoRequirementsIsAlwaysAccessible(): void
	{
		// Element 22 (solar_plant) has no entry in $requirements → always true
		$user   = $this->makeUser();
		$planet = $this->makePlanet();

		$this->assertTrue(BuildFunctions::isTechnologieAccessible($user, $planet, 22));
	}

	public function testElementAccessibleWhenTechLevelMet(): void
	{
		// Light Fighter (202) requires combustion_tech >= 1
		// $requirements[202] = [115 => 1], $resource[115] = 'combustion_tech'
		$user   = $this->makeUser(['combustion_tech' => 1]);
		$planet = $this->makePlanet();

		$this->assertTrue(BuildFunctions::isTechnologieAccessible($user, $planet, 202));
	}

	public function testElementBlockedWhenUserTechTooLow(): void
	{
		// Combustion Drive level 0 < required 1 → false
		$user   = $this->makeUser(['combustion_tech' => 0]);
		$planet = $this->makePlanet();

		$this->assertFalse(BuildFunctions::isTechnologieAccessible($user, $planet, 202));
	}

	public function testElementBlockedWhenMultipleReqsAndOneMissing(): void
	{
		// Bomber (210) requires impulse_motor_tech >= 6 AND hyperspace_motor_tech >= 3
		// Has impulse but not hyperspace → blocked
		$user   = $this->makeUser(['impulse_motor_tech' => 6, 'hyperspace_motor_tech' => 2]);
		$planet = $this->makePlanet();

		$this->assertFalse(BuildFunctions::isTechnologieAccessible($user, $planet, 210));
	}

	public function testElementAccessibleWhenAllMultipleReqsMet(): void
	{
		$user   = $this->makeUser(['impulse_motor_tech' => 6, 'hyperspace_motor_tech' => 3]);
		$planet = $this->makePlanet();

		$this->assertTrue(BuildFunctions::isTechnologieAccessible($user, $planet, 210));
	}

	// -----------------------------------------------------------------------
	// getElementPrice
	// -----------------------------------------------------------------------

	public function testShipPriceReturnsBaseCostFromPricelist(): void
	{
		// Light Fighter (202) is in $reslist['fleet']; forLevel = null so elementLevel
		// stays null and cost is returned as base (no factor multiplication since factor=0).
		Config::setInstance($this->makeConfig(), 1);
		$GLOBALS['pricelist'] = $GLOBALS['pricelist'] ?? [];
		$GLOBALS['reslist']   = $GLOBALS['reslist'] ?? [];
		$GLOBALS['resource']  = $GLOBALS['resource'] ?? [];

		$user   = $this->makeUser();
		$planet = $this->makePlanet(['light_fighter' => 5]);

		// Fleet elements use $forLevel; passing null means getElementPrice returns []
		// when there's no planet/user entry that maps to $resource[$Element].
		// Pass forLevel = 1 to get a concrete price:
		$price = BuildFunctions::getElementPrice($user, $planet, 202, false, 1);

		$this->assertEquals(3000, $price[901]);
		$this->assertEquals(1000, $price[902]);
	}

	public function testDestroyPriceIsHalfNormalCost(): void
	{
		Config::setInstance($this->makeConfig(), 1);

		$user   = $this->makeUser();
		$planet = $this->makePlanet(['metal_mine' => 4]);

		// Building element 1 (metal mine) at level 4; factor = 1.5
		$normal  = BuildFunctions::getElementPrice($user, $planet, 1);
		$destroy = BuildFunctions::getElementPrice($user, $planet, 1, true);

		$this->assertNotEmpty($normal, 'Price must be non-empty for a known element');
		foreach ($normal as $resType => $amount) {
			if ($amount > 0) {
				$this->assertEquals($amount / 2, $destroy[$resType]);
			}
		}
	}

	// -----------------------------------------------------------------------
	// getBuildingTime
	// -----------------------------------------------------------------------

	public function testBuildingTimeDecreasesWithHigherRoboticFactory(): void
	{
		Config::setInstance($this->makeConfig(['game_speed' => 1]), 1);

		$user     = $this->makeUser();
		// Metal mine at level 1 so getElementPrice returns a non-zero cost
		$planet0  = $this->makePlanet(['robotic_factory' => 0, 'metal_mine' => 1]);
		$planet5  = $this->makePlanet(['robotic_factory' => 5, 'metal_mine' => 1]);

		$time0 = BuildFunctions::getBuildingTime($user, $planet0, 1);
		$time5 = BuildFunctions::getBuildingTime($user, $planet5, 1);

		$this->assertGreaterThan(0, $time0, 'Build time must be positive');
		$this->assertGreaterThan($time5, $time0, 'Higher robotic factory level must reduce build time');
	}

	public function testMinBuildTimeIsEnforced(): void
	{
		Config::setInstance($this->makeConfig(['game_speed' => 1, 'min_build_time' => 9999]), 1);

		$user   = $this->makeUser();
		$planet = $this->makePlanet(['robotic_factory' => 100, 'metal_mine' => 1]);

		// Even with extreme robotic level, result must be >= min_build_time
		$time = BuildFunctions::getBuildingTime($user, $planet, 1);

		$this->assertGreaterThanOrEqual(9999, $time);
	}
}
