<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MarketplaceRatioInputsTest extends TestCase
{
	public function testRatioInputNamesMatchJavascriptSelectors(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.marketPlace.default.tpl');
		$js = file_get_contents(__DIR__ . '/../../scripts/game/marketplace.js');
		$this->assertIsString($tpl);
		$this->assertIsString($js);

		foreach (['ratio-metal', 'ratio-crystal', 'ratio-deuterium'] as $name) {
			$this->assertStringContainsString('name="' . $name . '"', $tpl, "template missing input {$name}");
			$this->assertStringContainsString("input[name={$name}]", $js, "javascript missing selector {$name}");
		}

		$this->assertStringNotContainsString('ratio-cristal', $tpl);
		$this->assertStringNotContainsString('ratio-cristal', $js);
	}

	public function testResourceMarketHeaderRowUsesMatchingThTags(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.marketPlace.default.tpl');
		$this->assertIsString($tpl);
		$this->assertStringNotContainsString('<td></th>', $tpl);
	}
}
