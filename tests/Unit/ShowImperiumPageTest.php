<?php

declare(strict_types=1);

use HiveNova\Page\Game\ShowImperiumPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ShowImperiumPageTest extends TestCase
{
	public function test_planet_eco_needs_persist_when_hangar_queue_present(): void
	{
		$this->assertTrue(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => 0],
			['b_hangar_id' => serialize([[202, 5]]), 'b_building' => 0]
		));
	}

	public function test_planet_eco_needs_persist_when_building_finished(): void
	{
		$this->assertTrue(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => 0],
			['b_hangar_id' => '', 'b_building' => TIMESTAMP - 10]
		));
	}

	public function test_planet_eco_needs_persist_when_research_finished(): void
	{
		$this->assertTrue(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => TIMESTAMP - 5],
			['b_hangar_id' => '', 'b_building' => 0]
		));
	}

	public function test_planet_eco_skips_persist_for_idle_planet(): void
	{
		$this->assertFalse(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => 0],
			['b_hangar_id' => '', 'b_building' => 0]
		));
	}

	public function test_planet_eco_skips_persist_while_building_still_running(): void
	{
		$this->assertFalse(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => TIMESTAMP + 3600],
			['b_hangar_id' => '', 'b_building' => TIMESTAMP + 3600]
		));
	}

	public function test_imperium_planet_select_columns_covers_core_reslist_and_queues(): void
	{
		$resource = [
			1   => 'metal_mine',
			2   => 'crystal_mine',
			202 => 'light_fighter',
			401 => 'rocket_launcher',
			502 => 'interceptor',
			503 => 'interplanetary_missile',
			22  => 'solar_plant',
			33  => 'terraformer',
			41  => 'lunar_base',
		];
		$reslist = [
			'build'   => [1, 2],
			'fleet'   => [202],
			'defense' => [401],
			'missile' => [502, 503],
			'storage' => [22],
			'prod'    => [1, 22],
		];

		$columns = ShowImperiumPage::imperiumPlanetSelectColumns($resource, $reslist);

		foreach ([
			'id', 'name', 'image', 'galaxy', 'system', 'planet', 'planet_type',
			'field_current', 'field_max', 'temp_max',
			'metal', 'crystal', 'deuterium', 'energy',
			'last_update', 'eco_hash',
			'b_hangar_id', 'b_hangar', 'b_building', 'b_building_id',
		] as $required) {
			$this->assertContains($required, $columns, 'Missing core column: ' . $required);
		}

		$this->assertContains('metal_mine', $columns);
		$this->assertContains('light_fighter', $columns);
		$this->assertContains('interceptor', $columns);
		$this->assertContains('metal_mine_porcent', $columns);
		$this->assertContains('solar_plant_porcent', $columns);
		$this->assertContains('terraformer', $columns);
		$this->assertContains('lunar_base', $columns);
		$this->assertSame(count($columns), count(array_unique($columns)));
	}
}
