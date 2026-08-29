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

	public function test_select_includes_hangar_target_columns_for_shipyard_persist(): void
	{
		$resource = [
			1   => 'metal_mine',
			202 => 'light_fighter',
			401 => 'rocket_launcher',
			502 => 'interceptor_misil',
			22  => 'solar_plant',
		];
		$reslist = [
			'build'   => [1],
			'fleet'   => [202],
			'defense' => [401],
			'missile' => [502],
			'storage' => [22],
			'prod'    => [1, 22],
		];

		$columns = ShowImperiumPage::imperiumPlanetSelectColumns($resource, $reslist);

		$this->assertContains('metal_mine', $columns);
		$this->assertContains('solar_plant', $columns);
		// Required so ShipyardQueue / SavePlanetToDB can complete hangar jobs.
		$this->assertContains('light_fighter', $columns);
		$this->assertContains('rocket_launcher', $columns);
		$this->assertContains('interceptor_misil', $columns);
	}

	public function test_compact_matrix_values_drops_zeros(): void
	{
		$this->assertSame(
			['10' => 5, '12' => 3],
			ShowImperiumPage::compactMatrixValues(['10' => 5, '11' => 0, '12' => 3])
		);
	}

	public function test_build_matrix_payload_omits_empty_rows_and_zero_cells(): void
	{
		$resource = [
			1   => 'metal_mine',
			202 => 'light_fighter',
			401 => 'rocket_launcher',
			502 => 'interceptor',
			503 => 'interplanetary_missile',
			106 => 'spy_tech',
		];
		$reslist = [
			'build'   => [1],
			'fleet'   => [202],
			'defense' => [401],
			'missile' => [502, 503],
			'tech'    => [106],
		];
		$planets = [
			['id' => 10, 'metal_mine' => 3, 'light_fighter' => 0, 'rocket_launcher' => 0, 'interceptor' => 0, 'interplanetary_missile' => 0],
			['id' => 11, 'metal_mine' => 0, 'light_fighter' => 7, 'rocket_launcher' => 0, 'interceptor' => 0, 'interplanetary_missile' => 0],
		];
		$user = ['spy_tech' => 2];

		$payload = ShowImperiumPage::buildMatrixPayload($planets, $user, $resource, $reslist, [
			1 => 'Metal Mine',
			202 => 'Light Fighter',
			106 => 'Espionage Technology',
		]);

		$this->assertSame(['10', '11'], $payload['planetIds']);
		$this->assertSame(4, $payload['colspan']);
		$this->assertCount(1, $payload['sections']['build']);
		$this->assertSame(['10' => 3], $payload['sections']['build'][0]['values']);
		$this->assertSame(['11' => 7], $payload['sections']['fleet'][0]['values']);
		$this->assertSame([], $payload['sections']['defense']);
		$this->assertSame([], $payload['sections']['missiles']);
		$this->assertCount(1, $payload['sections']['tech']);
		$this->assertSame(2, $payload['sections']['tech'][0]['total']);
	}
}
