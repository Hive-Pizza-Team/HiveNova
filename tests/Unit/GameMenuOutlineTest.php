<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GameMenuOutlineTest extends TestCase
{
	private string $nav;

	protected function setUp(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$this->assertNotFalse($nav);
		$this->nav = $nav;
	}

	public function test_single_shipyard_entry_covers_fleet_and_defense(): void
	{
		$this->assertSame(1, preg_match_all('/page=shipyard/', $this->nav));
		$this->assertStringContainsString('lm_shipshard', $this->nav);
		$this->assertStringNotContainsString('lm_defenses', $this->nav);
		$this->assertStringContainsString('navPage == \'shipyard\'', $this->nav);
		$this->assertStringNotContainsString('navMode == \'defense\'', $this->nav);
	}

	public function test_map_and_universe_feed_sit_in_advanced_section(): void
	{
		$advanced = strpos($this->nav, 'lm_menu_section_empire');
		$admin = strpos($this->nav, 'lm_menu_section_account');
		$viz = strpos($this->nav, 'page=viz');
		$feed = strpos($this->nav, 'page=eventFirehose');

		$this->assertNotFalse($advanced);
		$this->assertNotFalse($admin);
		$this->assertNotFalse($viz);
		$this->assertNotFalse($feed);
		$this->assertGreaterThan($advanced, $viz);
		$this->assertGreaterThan($advanced, $feed);
		$this->assertLessThan($admin, $viz);
		$this->assertLessThan($admin, $feed);
	}

	public function test_resources_market_and_ship_merchant_sit_in_advanced_section(): void
	{
		$basics = strpos($this->nav, 'lm_menu_section_overview');
		$advanced = strpos($this->nav, 'lm_menu_section_empire');
		$admin = strpos($this->nav, 'lm_menu_section_account');
		$resources = strpos($this->nav, 'page=resources');
		$market = strpos($this->nav, 'page=trader');
		$shipMerchant = strpos($this->nav, 'page=fleetDealer');

		$this->assertNotFalse($basics);
		$this->assertNotFalse($advanced);
		$this->assertNotFalse($admin);
		$this->assertNotFalse($resources);
		$this->assertNotFalse($market);
		$this->assertNotFalse($shipMerchant);
		$this->assertGreaterThan($advanced, $resources);
		$this->assertGreaterThan($advanced, $market);
		$this->assertGreaterThan($advanced, $shipMerchant);
		$this->assertLessThan($admin, $resources);
		$this->assertLessThan($admin, $market);
		$this->assertLessThan($admin, $shipMerchant);
		$this->assertGreaterThan($resources, $market);
		$this->assertGreaterThan($market, $shipMerchant);
	}

	public function test_advanced_section_follows_product_order(): void
	{
		$advanced = strpos($this->nav, 'lm_menu_section_empire');
		$admin = strpos($this->nav, 'lm_menu_section_account');
		$order = [
			'page=resources',
			'page=battleSimulator',
			'page=statistics',
			'page=records',
			'page=battleHall',
			'page=achievements',
			'page=viz',
			'page=eventFirehose',
			'page=trader',
			'page=fleetDealer',
			'page=alliance',
		];

		$this->assertNotFalse($advanced);
		$this->assertNotFalse($admin);
		$this->assertStringContainsString('Advanced product order applied', $this->nav);
		$this->assertStringNotContainsString('TODO(nav-ia)', $this->nav);

		$previous = $advanced;
		foreach ($order as $needle) {
			$pos = strpos($this->nav, $needle);
			$this->assertNotFalse($pos, $needle . ' missing from nav');
			$this->assertGreaterThan($previous, $pos, $needle . ' is out of product order');
			$this->assertLessThan($admin, $pos, $needle . ' left Advanced');
			$previous = $pos;
		}
	}

	public function test_tech_tree_sits_with_research_and_shipyard_in_basics(): void
	{
		$basics = strpos($this->nav, 'lm_menu_section_overview');
		$advanced = strpos($this->nav, 'lm_menu_section_empire');
		$shipyard = strpos($this->nav, 'page=shipyard');
		$research = strpos($this->nav, 'page=research');
		$techtree = strpos($this->nav, 'page=techtree');
		$fleet = strpos($this->nav, 'page=fleetTable');

		$this->assertNotFalse($basics);
		$this->assertNotFalse($advanced);
		$this->assertNotFalse($shipyard);
		$this->assertNotFalse($research);
		$this->assertNotFalse($techtree);
		$this->assertNotFalse($fleet);
		$this->assertGreaterThan($basics, $shipyard);
		$this->assertGreaterThan($shipyard, $research);
		$this->assertGreaterThan($research, $techtree);
		$this->assertGreaterThan($techtree, $fleet);
		$this->assertLessThan($advanced, $techtree);
		$this->assertSame(1, preg_match_all('/page=techtree/', $this->nav));
	}

	public function test_shipyard_page_exposes_mode_tabs(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.shipyard.default.tpl');
		$this->assertNotFalse($tpl);
		$this->assertStringContainsString('shipyard-mode-tabs', $tpl);
		$this->assertStringContainsString('page=shipyard&amp;mode={$tab.mode}', $tpl);
		$this->assertStringContainsString('bd_shipyard_tab_ships', $tpl);
		$this->assertStringContainsString('lm_defenses', $tpl);
	}

	public function test_full_layout_includes_techtree_nudge(): void
	{
		$layout = file_get_contents(__DIR__ . '/../../styles/templates/game/layout.full.tpl');
		$nudge = file_get_contents(__DIR__ . '/../../styles/templates/game/shared.techtree.nudge.tpl');
		$this->assertNotFalse($layout);
		$this->assertNotFalse($nudge);
		$this->assertStringContainsString('shared.techtree.nudge.tpl', $layout);
		$this->assertStringContainsString('data-techtree-nudge', $nudge);
		$this->assertStringContainsString('data-techtree-nudge-dismiss', $nudge);
		$this->assertStringContainsString('role="status"', $nudge);
		$this->assertStringContainsString('page=techtree', $nudge);
	}
}
