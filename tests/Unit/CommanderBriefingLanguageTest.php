<?php

declare(strict_types=1);

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
	}
}
