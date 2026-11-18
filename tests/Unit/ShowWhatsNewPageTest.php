<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ShowWhatsNewPageTest extends TestCase
{
	public function test_menu_does_not_contain_whats_new_item(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$this->assertNotFalse($nav);
		$this->assertDoesNotMatchRegularExpression(
			'/<li>\s*<a href="game\.php\?page=whatsNew"/',
			$nav
		);
		$this->assertStringNotContainsString('lm_whats_new', $nav);
		$this->assertStringNotContainsString('whatsnew-badge', $nav);
		$this->assertStringNotContainsString('whatsNewUnseen', $nav);
	}

	public function test_disclamer_and_settings_link_to_whats_new(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$settings = file_get_contents(__DIR__ . '/../../styles/templates/game/page.settings.default.tpl');
		$this->assertNotFalse($nav);
		$this->assertNotFalse($settings);
		$this->assertStringContainsString('disclamer-whats-new', $nav);
		$this->assertStringContainsString('whats_new_link', $nav);
		$this->assertStringContainsString('game.php?page=whatsNew', $nav);
		$this->assertStringContainsString('whats_new_settings_label', $settings);
		$this->assertStringContainsString('whats_new_settings_link', $settings);
		$this->assertStringContainsString('href="game.php?page=whatsNew"', $settings);
	}

	public function test_layout_renders_accessible_dialog_when_modal_assigned(): void
	{
		$layout = file_get_contents(__DIR__ . '/../../styles/templates/game/layout.full.tpl');
		$this->assertNotFalse($layout);
		$this->assertStringContainsString('{if !empty($whatsNewModal)}', $layout);
		$this->assertStringContainsString('id="whats-new-dialog"', $layout);
		$this->assertStringContainsString('role="dialog"', $layout);
		$this->assertStringContainsString('aria-modal="true"', $layout);
		$this->assertStringContainsString('aria-labelledby="whats-new-dialog-title"', $layout);
		$this->assertStringContainsString("escape:'html'", $layout);
		$this->assertStringContainsString('scripts/game/whats-new-modal.js?v={$REV}', $layout);
		$this->assertStringContainsString('mode=dismiss', $layout);
	}

	public function test_topnav_and_bottomnav_have_no_whats_new_markers(): void
	{
		$top = file_get_contents(__DIR__ . '/../../styles/templates/game/main.topnav.tpl');
		$bottom = file_get_contents(__DIR__ . '/../../styles/templates/game/main.bottomnav.tpl');
		$this->assertNotFalse($top);
		$this->assertNotFalse($bottom);
		foreach ([$top, $bottom] as $tpl) {
			$this->assertStringNotContainsString('whatsNew', $tpl);
			$this->assertStringNotContainsString('menu-notify-dot', $tpl);
			$this->assertStringNotContainsString('hamburger--notify', $tpl);
			$this->assertStringNotContainsString('bottom-nav-more--notify', $tpl);
		}
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

	public function test_navigation_matches_develop_except_disclamer_link(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$this->assertNotFalse($nav);
		$develop = shell_exec('git -C ' . escapeshellarg(__DIR__ . '/../..') . ' show origin/develop:styles/templates/game/main.navigation.tpl');
		$this->assertIsString($develop);
		$developLines = preg_split("/\r\n|\n|\r/", rtrim((string) $develop)) ?: [];
		$navLines = preg_split("/\r\n|\n|\r/", rtrim($nav)) ?: [];
		$this->assertCount(count($developLines), $navLines);
		foreach ($developLines as $i => $line) {
			if (str_contains($navLines[$i], 'disclamer-whats-new') || str_contains($line, 'HiveNova engine')) {
				continue;
			}
			$this->assertSame($line, $navLines[$i], 'line ' . ($i + 1));
		}
		$this->assertStringContainsString('disclamer-whats-new', $nav);
	}
}
