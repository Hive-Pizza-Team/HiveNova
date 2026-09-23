<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class EnergyTechnologyCopyTest extends TestCase
{
	private const SHIPYARD_SENTENCE = 'Completing Energy Technology is needed for the Shipyard path, together with the other requirements for the Shipyard.';

	public function testEveryLocaleSaysEnergyTechnologyIsOnTheShipyardPath(): void
	{
		$root = dirname(__DIR__, 2) . '/language';
		$locales = array_values(array_filter(
			scandir($root) ?: [],
			static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($root . '/' . $entry)
		));
		$this->assertNotEmpty($locales);

		foreach ($locales as $locale) {
			$LNG = [];
			include $root . '/' . $locale . '/TECH.php';
			$this->assertArrayHasKey(113, $LNG['shortDescription'], $locale . ' shortDescription');
			$this->assertStringContainsString(self::SHIPYARD_SENTENCE, $LNG['shortDescription'][113], $locale . ' shortDescription');
			$this->assertArrayHasKey(113, $LNG['longDescription'], $locale . ' longDescription');
			$this->assertStringContainsString(self::SHIPYARD_SENTENCE, $LNG['longDescription'][113], $locale . ' longDescription');

			$LNG = [];
			include $root . '/' . $locale . '/INGAME.php';
			$this->assertStringContainsString('Energy Technology', $LNG['bd_shipyard_required'], $locale . ' bd_shipyard_required');
		}
	}

	public function testResearchPanelRendersShortDescription(): void
	{
		$tpl = file_get_contents(dirname(__DIR__, 2) . '/styles/templates/game/page.research.default.tpl');
		$this->assertNotFalse($tpl);
		$this->assertStringContainsString('shortDescription', $tpl);
		$this->assertStringContainsString('element-short-desc', $tpl);
	}
}
