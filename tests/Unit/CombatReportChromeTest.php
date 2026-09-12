<?php

use HiveNova\Mission\CombatReportChrome;
use PHPUnit\Framework\TestCase;

class CombatReportChromeTest extends TestCase
{
	public function testLoggedInCommandersGetGameMenus(): void
	{
		$this->assertTrue(CombatReportChrome::showGameMenus(['id' => 42]));
		$this->assertTrue(CombatReportChrome::showGameMenus(['id' => '7']));
	}

	public function testGuestsDoNotGetGameMenus(): void
	{
		$this->assertFalse(CombatReportChrome::showGameMenus([]));
		$this->assertFalse(CombatReportChrome::showGameMenus(['id' => 0]));
		$this->assertFalse(CombatReportChrome::showGameMenus(['id' => '0']));
	}

	public function testRaportPageAppliesChromeForBothViews(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/game/ShowRaportPage.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('CombatReportChrome::showGameMenus', $src);
		$this->assertSame(2, substr_count($src, 'applyCombatReportChrome($USER)'));
		$this->assertSame(1, substr_count($src, "setWindow('popup')"));
	}
}
