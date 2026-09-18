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
