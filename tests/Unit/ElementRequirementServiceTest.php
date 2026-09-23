<?php

declare(strict_types=1);

use HiveNova\Core\ElementRequirementService;
use HiveNova\Core\ShipyardPageModeService;
use PHPUnit\Framework\TestCase;

class ElementRequirementServiceTest extends TestCase
{
	private ElementRequirementService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->service = new ElementRequirementService();
	}

	public function test_research_href_uses_existing_t_fragment(): void
	{
		$this->assertSame('research', $this->service->pageForElement(115));
		$this->assertSame('t115', $this->service->fragmentId(115));
		$this->assertSame('game.php?page=research#t115', $this->service->hrefForElement(115));
		$this->assertSame('game.php?page=research#t113', $this->service->hrefForElement(113));
	}

	public function test_building_href_uses_g_fragment(): void
	{
		$this->assertSame('buildings', $this->service->pageForElement(21));
		$this->assertSame('g21', $this->service->fragmentId(21));
		$this->assertSame('game.php?page=buildings#g21', $this->service->hrefForElement(21));
	}

	public function test_small_cargo_href_opens_shipyard_fleet_tab(): void
	{
		$shipId = SHIP_SMALL_CARGO;
		$this->assertSame('shipyard', $this->service->pageForElement($shipId));
		$this->assertSame('s' . $shipId, $this->service->fragmentId($shipId));
		$this->assertSame(
			'game.php?page=shipyard&mode=' . ShipyardPageModeService::MODE_FLEET . '#s' . $shipId,
			$this->service->hrefForElement($shipId)
		);
	}

	public function test_defense_href_opens_shipyard_defense_tab(): void
	{
		$this->assertSame('shipyard', $this->service->pageForElement(402));
		$this->assertSame('s402', $this->service->fragmentId(402));
		$this->assertSame(
			'game.php?page=shipyard&mode=' . ShipyardPageModeService::MODE_DEFENSE . '#s402',
			$this->service->hrefForElement(402)
		);
	}

	public function test_officer_href_uses_o_fragment(): void
	{
		$this->assertSame('officier', $this->service->pageForElement(603));
		$this->assertSame('o603', $this->service->fragmentId(603));
		$this->assertSame('game.php?page=officier#o603', $this->service->hrefForElement(603));
	}

	public function test_resources_have_no_panel_href(): void
	{
		$this->assertNull($this->service->pageForElement(RESOURCE_METAL));
		$this->assertNull($this->service->fragmentId(RESOURCE_METAL));
		$this->assertNull($this->service->hrefForElement(RESOURCE_METAL));
	}

	public function test_list_for_element_marks_unmet_research_and_building_links(): void
	{
		$rows = $this->service->listForElement(
			SHIP_SMALL_CARGO,
			['combustion_tech' => 0],
			['hangar' => 1],
			[
				SHIP_SMALL_CARGO => [
					21 => 2,
					115 => 2,
				],
			],
			[
				21 => 'hangar',
				115 => 'combustion_tech',
			],
			[
				21 => 'Shipyard',
				115 => 'Combustion Engine',
			]
		);

		$this->assertCount(2, $rows);

		$byId = [];
		foreach ($rows as $row) {
			$byId[$row['id']] = $row;
		}

		$this->assertSame('Shipyard', $byId[21]['name']);
		$this->assertSame(2, $byId[21]['count']);
		$this->assertSame(1, $byId[21]['own']);
		$this->assertFalse($byId[21]['met']);
		$this->assertSame('game.php?page=buildings#g21', $byId[21]['href']);

		$this->assertSame('Combustion Engine', $byId[115]['name']);
		$this->assertSame(2, $byId[115]['count']);
		$this->assertSame(0, $byId[115]['own']);
		$this->assertFalse($byId[115]['met']);
		$this->assertSame('game.php?page=research#t115', $byId[115]['href']);
	}

	public function test_list_for_element_marks_met_requirements(): void
	{
		$rows = $this->service->listForElement(
			21,
			['energy_tech' => 3],
			['robotic_factory' => 2],
			[21 => [14 => 2]],
			[14 => 'robotic_factory'],
			[14 => 'Gigafactory']
		);

		$this->assertCount(1, $rows);
		$this->assertTrue($rows[0]['met']);
		$this->assertSame(2, $rows[0]['own']);
		$this->assertSame('game.php?page=buildings#g14', $rows[0]['href']);
	}

	public function test_list_for_element_is_empty_without_requirements(): void
	{
		$this->assertSame([], $this->service->listForElement(1, [], [], [], [], []));
	}

	public function test_locked_shipyard_lists_missing_energy_technology(): void
	{
		$rows = $this->service->listForElement(
			ElementRequirementService::SHIPYARD,
			['energy_tech' => 0],
			['robotic_factory' => 0],
			[ElementRequirementService::SHIPYARD => [14 => 2]],
			[
				14 => 'robotic_factory',
				ElementRequirementService::ENERGY_TECH => 'energy_tech',
			],
			[
				14 => 'Gigafactory',
				ElementRequirementService::ENERGY_TECH => 'Energy Technology',
			]
		);

		$byId = [];
		foreach ($rows as $row) {
			$byId[$row['id']] = $row;
		}

		$this->assertArrayHasKey(14, $byId);
		$this->assertFalse($byId[14]['met']);
		$this->assertArrayHasKey(ElementRequirementService::ENERGY_TECH, $byId);
		$energy = $byId[ElementRequirementService::ENERGY_TECH];
		$this->assertSame('Energy Technology', $energy['name']);
		$this->assertSame(1, $energy['count']);
		$this->assertSame(0, $energy['own']);
		$this->assertFalse($energy['met']);
		$this->assertSame('game.php?page=research#t113', $energy['href']);
	}

	public function test_shipyard_does_not_duplicate_an_existing_energy_requirement(): void
	{
		$rows = $this->service->listForElement(
			ElementRequirementService::SHIPYARD,
			['energy_tech' => 0],
			['robotic_factory' => 0],
			[ElementRequirementService::SHIPYARD => [14 => 2, ElementRequirementService::ENERGY_TECH => 1]],
			[
				14 => 'robotic_factory',
				ElementRequirementService::ENERGY_TECH => 'energy_tech',
			],
			[
				14 => 'Gigafactory',
				ElementRequirementService::ENERGY_TECH => 'Energy Technology',
			]
		);

		$energyRows = array_values(array_filter(
			$rows,
			static fn (array $row): bool => $row['id'] === ElementRequirementService::ENERGY_TECH
		));
		$this->assertCount(1, $energyRows);
		$this->assertFalse($energyRows[0]['met']);
		$this->assertSame('game.php?page=research#t113', $energyRows[0]['href']);
	}

	public function test_open_shipyard_does_not_invent_an_energy_requirement(): void
	{
		$rows = $this->service->listForElement(
			ElementRequirementService::SHIPYARD,
			['energy_tech' => 0],
			['robotic_factory' => 2],
			[ElementRequirementService::SHIPYARD => [14 => 2]],
			[
				14 => 'robotic_factory',
				ElementRequirementService::ENERGY_TECH => 'energy_tech',
			],
			[14 => 'Gigafactory', ElementRequirementService::ENERGY_TECH => 'Energy Technology']
		);

		$this->assertCount(1, $rows);
		$this->assertSame(14, $rows[0]['id']);
		$this->assertTrue($rows[0]['met']);
	}

	public function test_locked_shipyard_hides_energy_once_it_is_researched(): void
	{
		$rows = $this->service->listForElement(
			ElementRequirementService::SHIPYARD,
			['energy_tech' => 1],
			['robotic_factory' => 1],
			[ElementRequirementService::SHIPYARD => [14 => 2]],
			[
				14 => 'robotic_factory',
				ElementRequirementService::ENERGY_TECH => 'energy_tech',
			],
			[14 => 'Gigafactory', ElementRequirementService::ENERGY_TECH => 'Energy Technology']
		);

		$ids = array_column($rows, 'id');
		$this->assertSame([14], $ids);
		$this->assertFalse($rows[0]['met']);
	}

	public function test_unmet_requirement_links_keep_clickable_research_href(): void
	{
		$html = $this->service->unmetRequirementLinksHtml([
			[
				'id' => ElementRequirementService::ENERGY_TECH,
				'name' => 'Energy "Technology"',
				'count' => 1,
				'own' => 0,
				'met' => false,
				'href' => 'game.php?page=research#t113',
			],
			[
				'id' => 14,
				'name' => 'Gigafactory',
				'count' => 2,
				'own' => 2,
				'met' => true,
				'href' => 'game.php?page=buildings#g14',
			],
		], 'Level ');

		$this->assertStringContainsString('class="requirement-link requirement-link--unmet"', $html);
		$this->assertStringContainsString('href="game.php?page=research#t113"', $html);
		$this->assertStringContainsString('Energy &quot;Technology&quot;', $html);
		$this->assertStringContainsString('Level 0/1', $html);
		$this->assertStringNotContainsString('Gigafactory', $html);
	}

	public function test_unmet_requirement_links_are_empty_when_every_requirement_is_met(): void
	{
		$this->assertSame('', $this->service->unmetRequirementLinksHtml([
			[
				'id' => 14,
				'name' => 'Gigafactory',
				'count' => 2,
				'own' => 2,
				'met' => true,
				'href' => 'game.php?page=buildings#g14',
			],
		]));
	}

	public function test_owned_level_prefers_planet_column_when_present(): void
	{
		$own = $this->service->ownedLevel(
			21,
			['hangar' => 9],
			['hangar' => 2],
			[21 => 'hangar']
		);

		$this->assertSame(2, $own);
	}
}
