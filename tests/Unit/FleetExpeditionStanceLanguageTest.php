<?php

declare(strict_types=1);

use HiveNova\Core\Language;
use PHPUnit\Framework\TestCase;

class FleetExpeditionStanceLanguageTest extends TestCase
{
	public function testIngameDefinesFleetExpeditionStanceLabels(): void
	{
		$lng = new Language('en');
		$lng->includeData(['INGAME']);

		$this->assertSame('Expedition stance', $lng['fl_expedition_stance']);
		$this->assertSame('Cautious', $lng['cm_stance_cautious']);
		$this->assertSame('Balanced', $lng['cm_stance_balanced']);
		$this->assertSame('Aggressive', $lng['cm_stance_aggressive']);
	}

	public function testFleetTemplatesUseIngameStanceKeys(): void
	{
		$step2 = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/page.fleetStep2.default.tpl');
		$table = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/page.fleetTable.default.tpl');

		$this->assertStringContainsString('{$LNG.fl_expedition_stance}', $step2);
		$this->assertStringContainsString('{$LNG.cm_stance_cautious}', $step2);
		$this->assertStringNotContainsString('fl_stance_', $step2);
		$this->assertStringContainsString('cm_stance_{$FlyingFleetRow.stance}', $table);
		$this->assertStringNotContainsString('fl_stance_', $table);
	}
}
