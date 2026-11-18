<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ShowWhatsNewPageTest extends TestCase
{
	public function test_page_is_in_overview_menu_not_bottom_nav(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$bottom = file_get_contents(__DIR__ . '/../../styles/templates/game/main.bottomnav.tpl');
		$this->assertNotFalse($nav);
		$this->assertNotFalse($bottom);

		$overview = strpos($nav, 'lm_menu_section_overview');
		$empire = strpos($nav, 'lm_menu_section_empire');
		$faq = strpos($nav, 'page=questions');
		$whatsNew = strpos($nav, 'page=whatsNew');

		$this->assertNotFalse($overview);
		$this->assertNotFalse($empire);
		$this->assertNotFalse($faq);
		$this->assertNotFalse($whatsNew);
		$this->assertGreaterThan($faq, $whatsNew);
		$this->assertLessThan($empire, $whatsNew);
		$this->assertStringContainsString('lm_whats_new', $nav);
		$this->assertStringContainsString('whatsnew-badge', $nav);
		$this->assertStringNotContainsString('page=whatsNew', $bottom);
	}

	public function test_mobile_menu_toggles_show_notify_dot(): void
	{
		$top = file_get_contents(__DIR__ . '/../../styles/templates/game/main.topnav.tpl');
		$bottom = file_get_contents(__DIR__ . '/../../styles/templates/game/main.bottomnav.tpl');
		$this->assertNotFalse($top);
		$this->assertNotFalse($bottom);
		$this->assertStringContainsString('menu-notify-dot', $top);
		$this->assertStringContainsString('hamburger--notify', $top);
		$this->assertStringContainsString('menu-notify-dot', $bottom);
		$this->assertStringContainsString('bottom-nav-more--notify', $bottom);
	}

	public function test_template_escapes_items_and_marks_unseen(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.whatsNew.default.tpl');
		$this->assertNotFalse($tpl);
		$this->assertStringContainsString("escape:'html'", $tpl);
		$this->assertStringContainsString('whats-new__release--unseen', $tpl);
		$this->assertStringContainsString('whats_new_new_label', $tpl);
		$this->assertStringContainsString('whats_new_empty', $tpl);
		$this->assertStringNotContainsString('nofilter', $tpl);
		$this->assertStringNotContainsString('Parsedown', $tpl);
	}
}
