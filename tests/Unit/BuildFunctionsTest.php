<?php

use HiveNova\Core\Config;
use HiveNova\Core\BuildFunctions;

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

	// -----------------------------------------------------------------------
	// getBonusList
	// -----------------------------------------------------------------------

	public function testGetBonusListReturnsArray(): void
	{
		$list = BuildFunctions::getBonusList();
		$this->assertIsArray($list);
		$this->assertNotEmpty($list);
	}

	public function testGetBonusListContainsKnownKeys(): void
	{
		$list = BuildFunctions::getBonusList();
		$this->assertContains('Attack', $list);
		$this->assertContains('FlyTime', $list);
		$this->assertContains('FleetSlots', $list);
		$this->assertContains('Resource', $list);
		$this->assertContains('Energy', $list);
	}

	public function testGetBonusListHasExpectedCount(): void
	{
		$this->assertCount(18, BuildFunctions::getBonusList());
	}

	// -----------------------------------------------------------------------
	// getRestPrice
	// -----------------------------------------------------------------------

	public function testGetRestPriceReturnsZerosWhenPlanetHasEnoughResources(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		// metal_mine => 1 so getElementPrice can compute the level-1 cost
		$planet = $this->makePlanet(['metal_mine' => 1, 'metal' => 100000, 'crystal' => 100000]);

		// Metal mine level 1 costs 60 metal + 15 crystal → planet has plenty
		$rest = BuildFunctions::getRestPrice($user, $planet, 1);

		$this->assertSame(0.0, (float) $rest[901], 'No metal shortfall when planet is rich');
		$this->assertSame(0.0, (float) $rest[902], 'No crystal shortfall when planet is rich');
	}

	public function testGetRestPriceReturnsShortfallWhenPlanetLacksResources(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		// metal_mine => 1 so getElementPrice can resolve level; metal/crystal = 0
		$planet = $this->makePlanet(['metal_mine' => 1, 'metal' => 0, 'crystal' => 0]);

		// Metal mine level 1 costs 60 metal + 15 crystal → planet has none
		$rest = BuildFunctions::getRestPrice($user, $planet, 1);

		$this->assertGreaterThan(0, $rest[901], 'Shortfall in metal expected');
		$this->assertGreaterThan(0, $rest[902], 'Shortfall in crystal expected');
	}

	public function testGetRestPriceWithPrecomputedPrice(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet(['metal' => 500, 'crystal' => 5]);

		// Supply element price directly: need 200 metal, 100 crystal
		$rest = BuildFunctions::getRestPrice($user, $planet, 1, [901 => 200, 902 => 100]);

		$this->assertSame(0.0, (float) $rest[901], 'Planet has 500 metal, price 200 → no shortfall');
		$this->assertSame(95.0, (float) $rest[902], 'Planet has 5 crystal, price 100 → shortfall 95');
	}

	// -----------------------------------------------------------------------
	// isElementBuyable
	// -----------------------------------------------------------------------

	public function testIsElementBuyableReturnsTrueWhenAffordable(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet(['metal_mine' => 1, 'metal' => 100000, 'crystal' => 100000]);

		$this->assertTrue(BuildFunctions::isElementBuyable($user, $planet, 1));
	}

	public function testIsElementBuyableReturnsFalseWhenNotAffordable(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet(['metal_mine' => 1, 'metal' => 0, 'crystal' => 0]);

		$this->assertFalse(BuildFunctions::isElementBuyable($user, $planet, 1));
	}

	public function testIsElementBuyableReturnsFalseWhenOnlyOneResourceMissing(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		// Metal mine costs 60 metal + 15 crystal at level 1
		// Planet has metal but no crystal
		$planet = $this->makePlanet(['metal_mine' => 1, 'metal' => 1000, 'crystal' => 0]);

		$this->assertFalse(BuildFunctions::isElementBuyable($user, $planet, 1));
	}

	// -----------------------------------------------------------------------
	// getMaxConstructibleElements
	// -----------------------------------------------------------------------

	public function testGetMaxConstructibleElementsLimitedBySmallestResource(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		// Pre-supply price array for LF: 3000 metal, 1000 crystal per unit
		// Planet: 12000 metal → 4 LF; 2000 crystal → 2 LF; min = 2
		$planet = $this->makePlanet(['metal' => 12000, 'crystal' => 2000]);

		$max = BuildFunctions::getMaxConstructibleElements(
			$user, $planet, 202, [901 => 3000, 902 => 1000]
		);

		$this->assertEquals(2, $max);
	}

	public function testGetMaxConstructibleElementsEqualLimits(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		// 9000 metal / 3000 = 3, 3000 crystal / 1000 = 3 → both limits equal 3
		$planet = $this->makePlanet(['metal' => 9000, 'crystal' => 3000]);

		$max = BuildFunctions::getMaxConstructibleElements(
			$user, $planet, 202, [901 => 3000, 902 => 1000]
		);

		$this->assertEquals(3, $max);
	}

	public function testGetMaxConstructibleElementsZeroWhenCannotAffordOne(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet(['metal' => 0, 'crystal' => 0]);

		$max = BuildFunctions::getMaxConstructibleElements(
			$user, $planet, 202, [901 => 3000, 902 => 1000]
		);

		$this->assertEquals(0, $max);
	}

	public function testGetMaxConstructibleElementsCapsOnePerPlanetBuildings(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet(['metal' => 1000000, 'crystal' => 1000000]);

		$max = BuildFunctions::getMaxConstructibleElements(
			$user, $planet, 31, [901 => 1000, 902 => 500]
		);

		$this->assertEquals(1, $max, 'Elements in reslist one are capped at 1');
	}

	public function testGetMaxConstructibleElementsUsesUserResourceWhenNotOnPlanet(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$GLOBALS['resource'][921] = 'darkmatter';
		$user   = $this->makeUser(['darkmatter' => 5000]);
		$planet = $this->makePlanet();

		$max = BuildFunctions::getMaxConstructibleElements(
			$user, $planet, 601, [921 => 1000]
		);

		$this->assertEquals(5, $max);
	}

	public function testGetMaxConstructibleElementsThrowsForUnknownResource(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet();

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Unknown Ressource 921 at element 202.');

		BuildFunctions::getMaxConstructibleElements($user, $planet, 202, [921 => 1000]);
	}

	// -----------------------------------------------------------------------
	// getMaxConstructibleRockets
	// -----------------------------------------------------------------------

	public function testGetMaxConstructibleRocketsEmptyWhenSiloHasNoCapacity(): void
	{
		Config::setInstance($this->makeConfig(['silo_factor' => 1]), 1);
		$GLOBALS['resource'][44]  = 'missile_silo';
		$GLOBALS['resource'][502] = 'interceptor_missile';
		$GLOBALS['resource'][503] = 'interplanetary_missile';

		$user   = $this->makeUser();
		$planet = $this->makePlanet([
			'missile_silo'            => 0,
			'interceptor_missile'     => 0,
			'interplanetary_missile'  => 0,
		]);

		$max = BuildFunctions::getMaxConstructibleRockets($user, $planet);

		$this->assertEquals(0, $max[502]);
		$this->assertEquals(0, $max[503]);
	}

	public function testGetMaxConstructibleRocketsScalesWithSiloLevel(): void
	{
		Config::setInstance($this->makeConfig(['silo_factor' => 2]), 1);
		$GLOBALS['resource'][44]  = 'missile_silo';
		$GLOBALS['resource'][502] = 'interceptor_missile';
		$GLOBALS['resource'][503] = 'interplanetary_missile';

		$user   = $this->makeUser();
		$planet = $this->makePlanet([
			'missile_silo'            => 3,
			'interceptor_missile'     => 0,
			'interplanetary_missile'  => 0,
		]);

		// 3 silo × 10 × silo_factor 2 = 60 ABM slots; IPM costs 2 slots each
		$max = BuildFunctions::getMaxConstructibleRockets($user, $planet);

		$this->assertEquals(60, $max[502]);
		$this->assertEquals(30, $max[503]);
	}

	public function testGetMaxConstructibleRocketsSubtractsExistingMissiles(): void
	{
		Config::setInstance($this->makeConfig(['silo_factor' => 1]), 1);
		$GLOBALS['resource'][44]  = 'missile_silo';
		$GLOBALS['resource'][502] = 'interceptor_missile';
		$GLOBALS['resource'][503] = 'interplanetary_missile';

		$user   = $this->makeUser();
		$planet = $this->makePlanet([
			'missile_silo'            => 2,
			'interceptor_missile'     => 5,
			'interplanetary_missile'  => 3,
		]);

		// Capacity 20; used = 5 + (2×3) = 11 → 9 ABM slots left
		$max = BuildFunctions::getMaxConstructibleRockets($user, $planet);

		$this->assertEquals(9, $max[502]);
		$this->assertEquals(4, $max[503]);
	}

	public function testGetMaxConstructibleRocketsIncludesHangarQueue(): void
	{
		Config::setInstance($this->makeConfig(['silo_factor' => 1]), 1);
		$GLOBALS['resource'][44]  = 'missile_silo';
		$GLOBALS['resource'][502] = 'interceptor_missile';
		$GLOBALS['resource'][503] = 'interplanetary_missile';

		$user   = $this->makeUser();
		$planet = $this->makePlanet([
			'missile_silo'            => 1,
			'interceptor_missile'     => 0,
			'interplanetary_missile'  => 0,
			'b_hangar_id'             => serialize([[502, 4], [503, 2]]),
		]);

		// Capacity 10; queued uses 4 + (2×2) = 8 → 2 ABM slots left
		$max = BuildFunctions::getMaxConstructibleRockets($user, $planet);

		$this->assertEquals(2, $max[502]);
		$this->assertEquals(1, $max[503]);
	}

	public function testGetMaxConstructibleRocketsAcceptsPrecomputedMissileCounts(): void
	{
		Config::setInstance($this->makeConfig(['silo_factor' => 1]), 1);
		$GLOBALS['resource'][44]  = 'missile_silo';
		$GLOBALS['resource'][502] = 'interceptor_missile';
		$GLOBALS['resource'][503] = 'interplanetary_missile';

		$user   = $this->makeUser();
		$planet = $this->makePlanet(['missile_silo' => 1]);

		$max = BuildFunctions::getMaxConstructibleRockets($user, $planet, [502 => 6, 503 => 1]);

		$this->assertEquals(2, $max[502]);
		$this->assertEquals(1, $max[503]);
	}

	// -----------------------------------------------------------------------
	// getAvalibleBonus
	// -----------------------------------------------------------------------

	public function testGetAvalibleBonusReturnsEmptyWhenNoBonuses(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$GLOBALS['USER'] = $this->makeUser();
		$GLOBALS['resource'][22] = 'solar_plant';
		$GLOBALS['pricelist'][22] = ['cost' => [901 => 0, 902 => 0, 903 => 0]];

		$bonus = BuildFunctions::getAvalibleBonus(22);

		$this->assertSame([], $bonus);
	}

	public function testGetAvalibleBonusScalesByUniverseConfigPower(): void
	{
		Config::setInstance($this->makeConfig([
			'rpg_geologue_power' => 200,
		]), 1);
		$GLOBALS['USER'] = $this->makeUser();
		$GLOBALS['resource'][601] = 'rpg_geologue';
		$GLOBALS['pricelist'][601] = [
			'bonus' => [
				'Resource' => [0.10, 0],
				'Attack'   => [0, 0],
			],
		];

		$bonus = BuildFunctions::getAvalibleBonus(601);

		$this->assertArrayHasKey('Resource', $bonus);
		$this->assertEquals(0.20, $bonus['Resource'][0], 'Bonus scaled by config power (0.10 × 200 / 100)');
		$this->assertArrayNotHasKey('Attack', $bonus, 'Zero bonuses are omitted');
	}

	// -----------------------------------------------------------------------
	// getElementPrice — additional branches
	// -----------------------------------------------------------------------

	public function testElementPriceUsesUserTechLevelWhenNotOnPlanet(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$GLOBALS['pricelist'][115] = [
			'cost'   => [901 => 100, 902 => 200, 903 => 0],
			'factor' => 2,
		];

		$user   = $this->makeUser(['combustion_tech' => 3]);
		$planet = $this->makePlanet();

		$price = BuildFunctions::getElementPrice($user, $planet, 115);

		$this->assertEquals(400, $price[901], 'Level 3 with factor 2: 100 × 2^2');
		$this->assertEquals(800, $price[902]);
	}

	public function testElementPriceMultipliesFleetCostByQuantity(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet();

		$price = BuildFunctions::getElementPrice($user, $planet, 202, false, 4);

		$this->assertEquals(12000, $price[901]);
		$this->assertEquals(4000, $price[902]);
	}

	public function testOfficierPriceMultipliedByUniverseConfigCost(): void
	{
		Config::setInstance($this->makeConfig(['rpg_geologue_cost' => 3]), 1);
		$GLOBALS['resource'][601] = 'rpg_geologue';
		$GLOBALS['resource'][921] = 'darkmatter';
		$GLOBALS['reslist']['ressources'] = [901, 902, 903, 921];
		$GLOBALS['pricelist'][601] = [
			'cost' => [921 => 1000],
		];

		$user   = $this->makeUser(['rpg_geologue' => 2]);
		$planet = $this->makePlanet();

		$price = BuildFunctions::getElementPrice($user, $planet, 601);

		$this->assertEquals(3000, $price[921]);
	}

	public function testElementPriceReturnsEmptyWhenLevelUnknown(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet();

		// Astrophysics (124) is a tech with no level on user or planet
		$this->assertSame([], BuildFunctions::getElementPrice($user, $planet, 124));
	}

	// -----------------------------------------------------------------------
	// getBuildingTime — fleet, defense, missile, research, destroy
	// -----------------------------------------------------------------------

	public function testFleetBuildTimeDecreasesWithHigherHangar(): void
	{
		Config::setInstance($this->makeConfig(['game_speed' => 1]), 1);
		$user     = $this->makeUser();
		$planet0  = $this->makePlanet(['hangar' => 0]);
		$planet5  = $this->makePlanet(['hangar' => 5]);

		$time0 = BuildFunctions::getBuildingTime($user, $planet0, 202, [901 => 3000, 902 => 1000]);
		$time5 = BuildFunctions::getBuildingTime($user, $planet5, 202, [901 => 3000, 902 => 1000]);

		$this->assertGreaterThan(0, $time0);
		$this->assertGreaterThan($time5, $time0, 'Higher hangar level must reduce ship build time');
	}

	public function testDefenseBuildTimeUsesDefensiveTimeFactor(): void
	{
		Config::setInstance($this->makeConfig(['game_speed' => 1]), 1);
		$planet = $this->makePlanet(['hangar' => 0]);
		$baseUser = $this->makeUser();
		$fastUser = $this->makeUser(['factor' => ['DefensiveTime' => 0.5]]);

		$base = BuildFunctions::getBuildingTime($baseUser, $planet, 401, [901 => 2000]);
		$fast = BuildFunctions::getBuildingTime($fastUser, $planet, 401, [901 => 2000]);

		$this->assertGreaterThan($base, $fast, 'DefensiveTime factor reduces build time');
	}

	public function testMissileBuildTimeUsesHangarAndNanite(): void
	{
		Config::setInstance($this->makeConfig(['game_speed' => 1]), 1);
		$user = $this->makeUser();
		$slow = $this->makePlanet(['hangar' => 0, 'nanite_factory' => 0]);
		$fast = $this->makePlanet(['hangar' => 4, 'nanite_factory' => 2]);

		$timeSlow = BuildFunctions::getBuildingTime($user, $slow, 503, [901 => 12500, 902 => 2500, 903 => 10000]);
		$timeFast = BuildFunctions::getBuildingTime($user, $fast, 503, [901 => 12500, 902 => 2500, 903 => 10000]);

		$this->assertGreaterThan($timeFast, $timeSlow);
	}

	public function testResearchBuildTimeUsesLabLevelsFromArray(): void
	{
		Config::setInstance($this->makeConfig([
			'game_speed'        => 1,
			'factor_university' => 0,
		]), 1);
		$GLOBALS['pricelist'][115] = [
			'cost' => [901 => 1000, 902 => 0, 903 => 0],
		];
		$GLOBALS['requirements'][115] = [31 => 1];

		$user = $this->makeUser();
		$lowLab = $this->makePlanet([
			'research_lab'                  => 0,
			'intergalactic_research_inter'  => [1, 1],
		]);
		$highLab = $this->makePlanet([
			'research_lab'                  => 0,
			'intergalactic_research_inter'  => [5, 5],
		]);

		$timeLow  = BuildFunctions::getBuildingTime($user, $lowLab, 115, [901 => 1000]);
		$timeHigh = BuildFunctions::getBuildingTime($user, $highLab, 115, [901 => 1000]);

		$this->assertGreaterThan($timeHigh, $timeLow, 'More qualifying lab levels reduce research time');
	}

	public function testDestroyBuildTimeUses1300Multiplier(): void
	{
		Config::setInstance($this->makeConfig(['game_speed' => 1, 'min_build_time' => 0]), 1);
		$user   = $this->makeUser();
		$planet = $this->makePlanet(['robotic_factory' => 0, 'metal_mine' => 4]);
		$price  = BuildFunctions::getElementPrice($user, $planet, 1);

		$build   = BuildFunctions::getBuildingTime($user, $planet, 1, $price, false);
		$destroy = BuildFunctions::getBuildingTime($user, $planet, 1, $price, true);

		$this->assertLessThan($build, $destroy, 'Destroy uses 1300× vs 3600× multiplier');
	}

	// -----------------------------------------------------------------------
	// getRestPrice — USER resource fallback
	// -----------------------------------------------------------------------

	public function testGetRestPriceUsesUserResourceWhenNotOnPlanet(): void
	{
		Config::setInstance($this->makeConfig(), 1);
		$GLOBALS['resource'][921] = 'darkmatter';
		$user   = $this->makeUser(['darkmatter' => 30]);
		$planet = $this->makePlanet();

		$rest = BuildFunctions::getRestPrice($user, $planet, 601, [921 => 100]);

		$this->assertSame(70.0, (float) $rest[921]);
	}

	// -----------------------------------------------------------------------
	// isTechnologieAccessible — planet-level requirements
	// -----------------------------------------------------------------------

	public function testElementBlockedWhenPlanetBuildingTooLow(): void
	{
		$GLOBALS['requirements'][15] = [14 => 2];
		$GLOBALS['resource'][14] = 'robotic_factory';
		$GLOBALS['resource'][15] = 'nanite_factory';

		$user = $this->makeUser();
		unset($user['robotic_factory']);
		$planet = $this->makePlanet(['robotic_factory' => 1]);

		$this->assertFalse(BuildFunctions::isTechnologieAccessible($user, $planet, 15));
	}

	public function testElementAccessibleWhenPlanetBuildingMeetsRequirement(): void
	{
		$GLOBALS['requirements'][15] = [14 => 2];
		$GLOBALS['resource'][14] = 'robotic_factory';
		$GLOBALS['resource'][15] = 'nanite_factory';

		$user = $this->makeUser();
		unset($user['robotic_factory']);
		$planet = $this->makePlanet(['robotic_factory' => 2]);

		$this->assertTrue(BuildFunctions::isTechnologieAccessible($user, $planet, 15));
	}
}
