<?php

use HiveNova\Mission\CombatReportMessageBuilder;
use PHPUnit\Framework\TestCase;

class CombatReportMessageBuilderTest extends TestCase
{
	public function testNewReportLinksStayInTheSameTab(): void
	{
		$html = CombatReportMessageBuilder::template();

		$this->assertStringContainsString('href="game.php?page=raport&raport=%s"', $html);
		$this->assertStringNotContainsString('target="_blank"', $html);
		$this->assertStringNotContainsString("target='_blank'", $html);
	}

	public function testSameTabHtmlStripsBlankTargetOnStoredRaportLinks(): void
	{
		$stored = '<a href="game.php?page=raport&raport=abc" target="_blank">CR</a>'
			.' <a href="https://example.test/rules" target="_blank">Rules</a>';

		$html = CombatReportMessageBuilder::sameTabHtml($stored);

		$this->assertSame(
			'<a href="game.php?page=raport&raport=abc">CR</a>'
			.' <a href="https://example.test/rules" target="_blank">Rules</a>',
			$html
		);
	}

	public function testSameTabHtmlStripsBlankTargetOnLegacyCombatReportPhpLinks(): void
	{
		$stored = '<a href="CombatReport.php?raport=xyz" target="_blank"><span>CR</span></a>';

		$this->assertSame(
			'<a href="CombatReport.php?raport=xyz"><span>CR</span></a>',
			CombatReportMessageBuilder::sameTabHtml($stored)
		);
	}

	public function testSameTabHtmlHandlesTargetBeforeHref(): void
	{
		$stored = '<a target="_blank" href="game.php?page=raport&raport=abc">CR</a>';

		$this->assertSame(
			'<a href="game.php?page=raport&raport=abc">CR</a>',
			CombatReportMessageBuilder::sameTabHtml($stored)
		);
	}

	public function testExpeditionAndHallOfFameLinksStayInTheSameTab(): void
	{
		$root = dirname(__DIR__, 2);
		$expedition = file_get_contents($root . '/includes/classes/missions/MissionCaseExpedition.php');
		$hall = file_get_contents($root . '/styles/templates/game/page.battleHall.default.tpl');

		$this->assertNotFalse($expedition);
		$this->assertNotFalse($hall);
		$this->assertStringContainsString('href="CombatReport.php?raport=%s"', $expedition);
		$this->assertDoesNotMatchRegularExpression('/CombatReport\.php\?raport=%s"\s+target="/', $expedition);
		$this->assertStringContainsString('page=raport&amp;mode=battlehall', $hall);
		$this->assertDoesNotMatchRegularExpression('/page=raport[^>]+\starget="/', $hall);
	}
}
