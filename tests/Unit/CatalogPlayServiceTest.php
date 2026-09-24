<?php

use HiveNova\Core\CatalogPlayService;
use PHPUnit\Framework\TestCase;

class CatalogPlayServiceTest extends TestCase
{
	public function testTechtreeSkipsMissingRequirementsAndReportsOwnLevels(): void
	{
		$lng = ['tech' => [1 => 'Metal Mine', 14 => 'Robot Factory']];
		$tree = CatalogPlayService::techtree(
			['robotic_factory' => 1],
			['metal_mine' => 4],
			$lng,
			[14 => [1 => 5], 99 => 'nope'],
			['build' => [14, 99], 'tech' => []],
			[1 => 'metal_mine', 14 => 'robotic_factory']
		);
		$this->assertCount(1, $tree);
		$this->assertSame(14, $tree[0]['id']);
		$this->assertSame('Robot Factory', $tree[0]['name']);
		$this->assertFalse($tree[0]['requirements'][0]['ok']);
		$this->assertSame(4, $tree[0]['requirements'][0]['own']);
		$this->assertSame(5, $tree[0]['requirements'][0]['need']);
	}

	public function testOfficersAndEmptyColumn(): void
	{
		$items = CatalogPlayService::officers(
			['commander' => 3],
			[],
			['tech' => [601 => 'Commander']],
			['officier' => [601, 999]],
			[601 => 'commander']
		);
		$this->assertSame(3, $items[0]['level']);
		$this->assertSame(0, $items[1]['level']);
	}

	public function testProductionSlidersAndUpdates(): void
	{
		$this->assertSame(0, CatalogPlayService::clampFactor(-4));
		$this->assertSame(10, CatalogPlayService::clampFactor(99));
		$planet = ['metal_mine' => 8, 'metal_mine_porcent' => 7];
		$sliders = CatalogPlayService::productionSliders($planet, ['tech' => [1 => 'Metal']], [1 => 'metal_mine', 2 => 'crystal_mine']);
		$this->assertCount(1, $sliders);
		$this->assertSame(7, $sliders[0]['factor']);
		$updates = CatalogPlayService::productionUpdates([1 => 12, 9 => 4, 2 => 3], [1 => 'metal_mine']);
		$this->assertSame(['metal_mine_porcent' => 10], $updates);
		$applied = CatalogPlayService::applyProductionUpdates($planet, $updates);
		$this->assertSame(10, $planet['metal_mine_porcent']);
		$this->assertSame(['metal_mine_porcent = :metal_mine_porcent'], $applied['set']);
	}

	public function testTraderMissilesPhalanxAndAlliance(): void
	{
		$rates = CatalogPlayService::traderRates();
		$this->assertSame(2.0, $rates[RESOURCE_METAL][RESOURCE_CRYSTAL]);
		$missiles = CatalogPlayService::missiles(
			['interceptor_misil' => 2, 'interplanetary_misil' => 5, 'silo' => 3],
			[]
		);
		$this->assertSame(2, $missiles['interceptor']);
		$this->assertSame(5, $missiles['interplanetary']);
		$this->assertSame(0, CatalogPlayService::phalanxRange(0));
		$this->assertSame(1, CatalogPlayService::phalanxRange(1));
		$this->assertSame(8, CatalogPlayService::phalanxRange(3));
		$phalanx = CatalogPlayService::phalanx(['system' => 10, 'galaxy' => 2, 'sensor_phalanx' => 3], [42 => 'sensor_phalanx']);
		$this->assertSame(2, $phalanx['systemMin']);
		$this->assertSame(18, $phalanx['systemMax']);
		$ally = CatalogPlayService::allianceSummary(['ally_id' => 4, 'ally_name' => 'Hive']);
		$this->assertTrue($ally['member']);
		$this->assertFalse(CatalogPlayService::allianceSummary([])['member']);
	}

	public function testMappersAndChrome(): void
	{
		$buddy = CatalogPlayService::mapBuddy([
			'id' => 9, 'buddyid' => 3, 'username' => 'pal', 'galaxy' => 1, 'system' => 2, 'planet' => 3,
			'ally_name' => 'A', 'text' => 'hi',
		], 9);
		$this->assertTrue($buddy['pending']);
		$this->assertTrue($buddy['self']);
		$note = CatalogPlayService::mapNote(['id' => 1, 'title' => 't', 'text' => 'x', 'priority' => 2, 'time' => 8]);
		$this->assertSame('t', $note['title']);
		$stat = CatalogPlayService::mapStat(['rank' => 4, 'points' => 10, 'username' => 'u', 'id_owner' => 2]);
		$this->assertSame(4, $stat['rank']);
		$this->assertSame(2, $stat['userId']);
		$this->assertSame('Fleet', CatalogPlayService::mapAcs(['id' => 7, 'name' => 'Fleet'])['name']);
		$bodies = CatalogPlayService::empireBodies([[
			'id' => 1,
			'name' => 'Home',
			'image' => 'wasserplanet02',
			'field_current' => 3,
			'field_max' => 10,
			'metal' => 50,
			'metal_perhour' => 10,
			'planet_type' => 1,
		]], (object) [
			'resource_multiplier' => 2,
			'metal_basic_income' => 20,
			'crystal_basic_income' => 10,
			'deuterium_basic_income' => 0,
		]);
		$this->assertSame(1, $bodies[0]['id']);
		$this->assertSame(50, $bodies[0]['metalPerHour']);
		$this->assertSame('techtree', CatalogPlayService::sanitizeKind('!!!'));
		$this->assertSame('officers', CatalogPlayService::sanitizeKind('Officers'));
		$labels = CatalogPlayService::chromeLabels(['lm_fleet' => 'Armada']);
		$this->assertSame('Armada', $labels['lm_fleet']);
		$this->assertSame('lm_overview', $labels['lm_overview']);
		$this->assertSame('lm_support', $labels['lm_support']);
		$this->assertSame('lm_fleet', CatalogPlayService::chromeLabels(null)['lm_fleet']);
		$this->assertSame('tr_exchange', $labels['tr_exchange']);
	}

	public function testTraderExchangesPlanetResourcesAndPizzabits(): void
	{
		$resource = [
			RESOURCE_METAL => 'metal',
			RESOURCE_CRYSTAL => 'crystal',
			RESOURCE_DEUTERIUM => 'deuterium',
			RESOURCE_DARKMATTER => 'darkmatter',
		];
		$user = ['darkmatter' => 4000];
		$planet = ['metal' => 1000.0, 'crystal' => 10.0, 'deuterium' => 5.0];
		$payload = CatalogPlayService::traderPayload($user, $planet, ['tech' => [901 => 'Metal']], $resource, 2500);
		$this->assertTrue($payload['canCall']);
		$this->assertSame(1000.0, $payload['items'][0]['amount']);

		$denied = CatalogPlayService::trade($user, $planet, RESOURCE_METAL, [RESOURCE_CRYSTAL => 10], $resource, 5000);
		$this->assertFalse($denied['ok']);
		$this->assertSame('pizzabits', $denied['reason']);
		$this->assertSame(4000, $user['darkmatter']);
		$this->assertSame(1000.0, $planet['metal']);

		$short = CatalogPlayService::trade($user, $planet, RESOURCE_METAL, [RESOURCE_CRYSTAL => 900], $resource, 2500);
		$this->assertSame('short', $short['reason']);
		$this->assertSame(1000.0, $planet['metal']);

		$empty = CatalogPlayService::trade($user, $planet, RESOURCE_METAL, [], $resource, 2500);
		$this->assertSame('empty', $empty['reason']);

		$bad = CatalogPlayService::trade($user, $planet, 911, [RESOURCE_CRYSTAL => 1], $resource, 2500);
		$this->assertSame('invalid', $bad['reason']);

		$result = CatalogPlayService::trade(
			$user,
			$planet,
			RESOURCE_METAL,
			[RESOURCE_CRYSTAL => 100, RESOURCE_DEUTERIUM => 50],
			$resource,
			2500
		);
		$this->assertTrue($result['ok']);
		$this->assertSame(400.0, $result['spent']);
		$this->assertSame(600.0, $planet['metal']);
		$this->assertSame(110.0, $planet['crystal']);
		$this->assertSame(55.0, $planet['deuterium']);
		$this->assertSame(1500, $user['darkmatter']);
		$this->assertSame(1500, $result['pizzabits']);
		$this->assertSame(
			[RESOURCE_CRYSTAL => 100, 911 => 9],
			CatalogPlayService::parseTradeWant([
				(string) RESOURCE_CRYSTAL => '100.4',
				RESOURCE_DEUTERIUM => -3,
				911 => 9,
			])
		);
	}
}
