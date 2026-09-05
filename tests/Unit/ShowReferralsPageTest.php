<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ShowReferralsPageTest extends TestCase
{
	public function test_page_is_in_main_menu_not_bottom_nav(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$bottom = file_get_contents(__DIR__ . '/../../styles/templates/game/main.bottomnav.tpl');
		$this->assertStringContainsString('game.php?page=referrals', $nav);
		$this->assertStringContainsString('lm_referrals', $nav);
		$this->assertStringContainsString('showReferralDashboard', $nav);
		$this->assertStringNotContainsString('page=referrals', $bottom);
		$this->assertStringNotContainsString('lm_referrals', $bottom);
	}

	public function test_game_template_has_no_account_editor_links(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.referrals.default.tpl');
		$this->assertStringNotContainsString('accounteditor', $tpl);
		$this->assertStringNotContainsString('Hauptframe', $tpl);
	}
}
