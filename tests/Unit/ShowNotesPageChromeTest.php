<?php

use PHPUnit\Framework\TestCase;

class ShowNotesPageChromeTest extends TestCase
{
	public function testNotesUsesFullGameChromeNotPopup(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/game/ShowNotesPage.php');
		$this->assertNotFalse($src);
		$this->assertStringNotContainsString("setWindow('popup')", $src);
	}

	public function testNotesNavIsInPageLinkNotPopup(): void
	{
		$nav = file_get_contents(dirname(__DIR__, 2) . '/styles/templates/game/main.navigation.tpl');
		$this->assertNotFalse($nav);
		$this->assertStringContainsString('href="game.php?page=notes"', $nav);
		$this->assertStringNotContainsString("OpenPopup('?page=notes'", $nav);
	}
}
