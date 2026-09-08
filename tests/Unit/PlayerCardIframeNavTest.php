<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PlayerCardIframeNavTest extends TestCase
{
	public function testHomeplanetAndAllianceLinksBreakOutOfFancybox(): void
	{
		$tpl = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/page.playerCard.default.tpl');

		$this->assertStringNotContainsString('parent.location', $tpl);
		$this->assertMatchesRegularExpression(
			'/<a href="game\.php\?page=galaxy&amp;galaxy=\{\$galaxy\}&amp;system=\{\$system\}" target="_top">/',
			$tpl
		);
		$this->assertMatchesRegularExpression(
			'/<a href="game\.php\?page=alliance&amp;mode=info&amp;id=\{\$allyid\}" target="_top">/',
			$tpl
		);
	}

	public function testAllianceApplyHomeplanetLinkBreaksOutOfFancybox(): void
	{
		$tpl = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/page.alliance.admin.detailApply.tpl');

		$this->assertStringNotContainsString('parent.location', $tpl);
		$this->assertMatchesRegularExpression(
			'/<a href="game\.php\?page=galaxy&amp;galaxy=\{\$applyDetail\.galaxy\}&amp;system=\{\$applyDetail\.system\}" target="_top">/',
			$tpl
		);
	}
}
