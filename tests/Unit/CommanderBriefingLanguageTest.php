<?php

declare(strict_types=1);

use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\Language;
use PHPUnit\Framework\TestCase;

class CommanderBriefingLanguageTest extends TestCase
{
	public function testIngameDefinesStanceLabelsUsedOnOverview(): void
	{
		$lng = new Language('en');
		$lng->includeData(['INGAME']);

		$this->assertSame('Cautious', $lng['cm_stance_cautious']);
		$this->assertSame('Balanced', $lng['cm_stance_balanced']);
		$this->assertSame('Aggressive', $lng['cm_stance_aggressive']);
	}

	public function testBriefingLooksUpIngameStanceKeys(): void
	{
		$tpl = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/partials/commander.briefing.tpl');

		$this->assertStringNotContainsString('fl_stance_', $tpl);
		$this->assertStringContainsString('cm_stance_{$commanderBriefing.directive.recommended_stance}', $tpl);
		$this->assertStringContainsString('cm_stance_{$expe.stance}', $tpl);
	}

	public function testClaimPostsAsAjaxSoEcoSaveDoesNotRun(): void
	{
		$js = (string) file_get_contents(ROOT_PATH . 'scripts/game/commander-briefing.js');
		$this->assertStringContainsString("page=commanderAjax&mode=' + mode + '&ajax=1'", $js);
		$this->assertStringContainsString("btn.addClass('is-pending')", $js);
		$this->assertStringContainsString("btn.attr('data-key')", $js);
	}

	public function testTradeCopyStatesThresholdForeignAndQuota(): void
	{
		$lng = new Language('en');
		$lng->includeData(['INGAME']);

		$desc = (string) $lng['cm_dir_trade_desc'];
		$suggest = (string) $lng['cm_suggest_trade'];
		$need = (string) DirectiveCatalog::get(DirectiveCatalog::TRADE)['targets']['trade_run'];

		$this->assertMatchesRegularExpression('/10[,\s]?000/', $desc);
		$this->assertStringContainsString($need, $desc);
		$this->assertMatchesRegularExpression('/another player/i', $desc);
		$this->assertMatchesRegularExpression('/arrival/i', $desc);
		$this->assertMatchesRegularExpression('/own-planet/i', $desc);
		$this->assertMatchesRegularExpression('/10[,\s]?000/', $suggest);
		$this->assertMatchesRegularExpression('/another player/i', $suggest);
	}

	public function testPickerShowsTargetCountsAndLockedCardUsesPhpBars(): void
	{
		$tpl = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/partials/commander.briefing.tpl');

		$this->assertStringContainsString('commander-briefing__select-targets', $tpl);
		$this->assertStringContainsString('foreach $option.targets as $counter => $need', $tpl);
		$this->assertStringContainsString('foreach $commanderBriefing.directive.bars as $bar', $tpl);
		$this->assertStringContainsString('width: {$bar.pct}%', $tpl);
		$this->assertStringNotContainsString('$have/$need*100', $tpl);
	}
}
