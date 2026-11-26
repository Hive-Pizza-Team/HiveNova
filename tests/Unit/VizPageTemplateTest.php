<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class VizPageTemplateTest extends TestCase
{
	public function testMapPageSetsABrowserTitle(): void
	{
		$tpl = (string) file_get_contents(ROOT_PATH . 'styles/templates/game/page.viz.default.tpl');
		$this->assertStringContainsString('{block name="title" prepend}{$LNG.lm_viz}{/block}', $tpl);
	}
}
