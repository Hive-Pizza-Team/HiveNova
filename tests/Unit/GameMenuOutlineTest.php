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

	public function test_shipyard_page_exposes_mode_tabs(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.shipyard.default.tpl');
		$this->assertNotFalse($tpl);
		$this->assertStringContainsString('shipyard-mode-tabs', $tpl);
		$this->assertStringContainsString('page=shipyard&amp;mode={$tab.mode}', $tpl);
		$this->assertStringContainsString('bd_shipyard_tab_ships', $tpl);
		$this->assertStringContainsString('lm_defenses', $tpl);
	}
}
