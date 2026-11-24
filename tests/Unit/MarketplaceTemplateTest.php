<?php

declare(strict_types=1);

use HiveNova\Core\Language;
use HiveNova\Core\Template;
use PHPUnit\Framework\TestCase;

/**
 * Renders page.marketPlace.default.tpl with sample offers and checks the
 * player-facing rules: own offers get Cancel (never Buy), blocked offers get a
 * disabled Buy with a reason, empty states, and theme-aware images.
 */
class MarketplaceTemplateTest extends TestCase
{
	private static function tplPath(string $name): string
	{
		return ROOT_PATH . 'styles/templates/game/' . $name;
	}

	private function render(array $vars): string
	{
		$cwd = getcwd();
		chdir(ROOT_PATH);
		try {
			$lng = new Language('en');
			$lng->includeData(['L18N', 'INGAME', 'TECH']);
		} finally {
			chdir($cwd);
		}

		$smarty = new Template();
		$smarty->setCaching(\Smarty::CACHING_OFF);
		$smarty->setForceCompile(true);
		$dir = sys_get_temp_dir() . '/hivenova-market-tpl-' . getmypid() . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		$smarty->setCompileDir($dir);
		$smarty->setTemplateDir(ROOT_PATH . 'styles/templates/game/');
		$smarty->assign($vars + [
			'LNG' => $lng,
			'dpath' => './styles/theme/hive/',
			'tab' => 'resources',
			'payWith' => 2,
			'payKey' => 'hc',
			'csrf' => str_repeat('c', 32),
			'flash' => null,
			'myOfferCount' => 0,
			'resourceOffers' => [],
			'fleetOffers' => [],
			'resourceHistory' => [],
			'fleetHistory' => [],
			'refRatio' => [4, 2, 1],
			'rulesText' => 'Fair-play rules: sample',
			'resImages' => [901 => 'metal', 902 => 'crystal', 903 => 'deuterium'],
			'tradeZone' => ['galaxy' => 1, 'system' => 2, 'planet' => 17],
			'sellUrl' => 'game.php?page=fleetTable&galaxy=1&system=2&planet=17&planettype=1&target_mission=16',
		]);
		$prev = error_reporting(error_reporting() & ~E_DEPRECATED);
		try {
			return $smarty->fetch('page.marketPlace.default.tpl');
		} finally {
			error_reporting($prev);
		}
	}

	private function offer(int $id, bool $own, string $blockHc = ''): array
	{
		return [
			'id' => $id, 'type' => 0, 'own' => $own, 'username' => 'P' . $id, 'diploClass' => '',
			'get' => [901 => 0, 902 => 60000, 903 => 0], 'ships' => [],
			'wantedType' => 1, 'wantedTech' => 901, 'wantedName' => 'Metal', 'wantedAmount' => 100000,
			'ratio' => 1.2, 'rating' => 'good', 'end' => 3600, 'delivery' => 600,
			'getLabel' => '60.000 Crystal', 'payLabel' => '100.000 Metal',
			'pay' => [
				'lc' => ['ships' => '20 LC', 'fuel' => 400, 'duration' => 0, 'block' => ''],
				'hc' => ['ships' => '4 HC', 'fuel' => 200, 'duration' => 0, 'block' => $blockHc],
			],
		];
	}

	/** @return list<string> */
	private function rowsHtml(string $html): array
	{
		preg_match_all('/<tr class="market-offer.*?<\/tr>/s', $html, $m);
		return $m[0];
	}

	public function testOwnOfferShowsCancelNotBuy(): void
	{
		$html = $this->render(['resourceOffers' => [$this->offer(5, true), $this->offer(6, false)], 'myOfferCount' => 1]);
		[$own, $other] = $this->rowsHtml($html);

		$this->assertStringContainsString('market-offer--own', $own);
		$this->assertStringContainsString('action=cancel', $own);
		$this->assertStringContainsString('Your offer', $own);
		$this->assertStringNotContainsString('market-buy', $own);

		$this->assertStringContainsString('action=buy', $other);
		$this->assertStringNotContainsString('action=cancel', $other);
		$this->assertStringContainsString('name="csrf" value="' . str_repeat('c', 32) . '"', $other);
		$this->assertStringContainsString('You have 1 active offer', $html);
	}

	public function testBlockedOfferHasDisabledBuyWithReason(): void
	{
		$html = $this->render(['resourceOffers' => [$this->offer(6, false, 'Not enough cargo ships')]]);
		[$row] = $this->rowsHtml($html);
		$this->assertMatchesRegularExpression('/market-buy__btn"\s+disabled/', $row);
		$this->assertStringContainsString('Not enough cargo ships', $row);
		$this->assertStringContainsString('data-block-hc="Not enough cargo ships"', $row);
	}

	public function testEmptyStatesLinkToTradingZone(): void
	{
		$html = $this->render([]);
		$this->assertStringContainsString('No resource offers right now.', $html);
		$this->assertStringContainsString('No ships for sale right now.', $html);
		$this->assertStringContainsString('No completed trades yet.', $html);
		$this->assertStringContainsString('target_mission=16', $html);
		$this->assertStringContainsString('[1:2:17]', $html);
	}

	public function testHistoryAmountsAreFormatted(): void
	{
		$html = $this->render(['resourceHistory' => [[
			'time' => '2026-11-22 08:00:00', 'time_label' => '22. Nov 2026, 08:00:00',
			'res_type' => 2, 'amount' => 45000, 'metal' => 90000, 'crystal' => 0, 'deuterium' => 0,
		]]]);
		$this->assertStringContainsString('90.000', $html);
		$this->assertStringContainsString('45.000', $html);
		$this->assertStringContainsString('22. Nov 2026, 08:00:00', $html);
	}

	public function testTemplateHasNoHardcodedThemeOrEnglishOrInlineScript(): void
	{
		$tpl = (string) file_get_contents(self::tplPath('page.marketPlace.default.tpl'));
		$this->assertStringNotContainsString('styles/theme/nova', $tpl);
		$this->assertStringNotContainsString('Resource market', $tpl);
		$this->assertStringNotContainsString('Fleet market', $tpl);
		$this->assertStringNotContainsString('Reference ratio', $tpl);
		$this->assertStringNotContainsString('<script', $tpl);

		$html = $this->render(['resourceOffers' => [$this->offer(5, false), $this->offer(6, false)]]);
		preg_match_all('/\sid="([^"]+)"/', $html, $m);
		$this->assertSame($m[1], array_values(array_unique($m[1])), 'duplicate element ids');
		$this->assertStringContainsString('./styles/theme/hive/images/crystal.gif', $html);
	}

	public function testTraderResourceButtonsHaveUniqueIds(): void
	{
		$tpl = (string) file_get_contents(self::tplPath('page.trader.default.tpl'));
		$this->assertStringNotContainsString('id="trader_metal"', $tpl);
		$this->assertStringContainsString('id="trader_{$resourceID}"', $tpl);
		$this->assertStringContainsString('for="trader_{$resourceID}"', $tpl);
	}

	public function testUnusedFlightTimeFieldIsGoneFromFleetStep2(): void
	{
		$tpl = (string) file_get_contents(self::tplPath('page.fleetStep2.default.tpl'));
		$this->assertStringNotContainsString('maxFlightTime', $tpl);
	}
	public function testDataAttributesStayRawWithNonEuNumberFormat(): void
	{
		// pretty_number() wraps values in <span class='ln'> for non-EU formats; the old JS
		// parsed that HTML and showed NaN. Ratio inputs must come from raw data attributes.
		$prev = $GLOBALS['userNumberFormat'] ?? null;
		$GLOBALS['userNumberFormat'] = 'us';
		try {
			$offer = $this->offer(6, false);
			$offer['get'][902] = 1234567.0;
			$html = $this->render(['resourceOffers' => [$offer]]);
		} finally {
			if ($prev === null) {
				unset($GLOBALS['userNumberFormat']);
			} else {
				$GLOBALS['userNumberFormat'] = $prev;
			}
		}
		[$row] = $this->rowsHtml($html);
		$this->assertStringContainsString("class='ln'", $row, 'fixture should exercise the non-EU span');
		$this->assertStringContainsString('data-get-902="1234567"', $row);
		$this->assertStringContainsString('data-pay-amount="100000"', $row);
		$this->assertMatchesRegularExpression('/data-get-901="0(\.0+)?"/', $row);

		$js = (string) file_get_contents(ROOT_PATH . 'scripts/game/marketplace.js');
		$this->assertStringNotContainsString('.html().replace', $js);
		$this->assertStringContainsString("attr('data-get-'", $js);
	}

	public function testGermanTypoFixed(): void
	{
		$de = (string) file_get_contents(ROOT_PATH . 'language/de/INGAME.php');
		$this->assertStringNotContainsString('Martplatz', $de);
	}

	public function testMobileLayoutStacksRowsAndClearsBottomNav(): void
	{
		$css = (string) file_get_contents(ROOT_PATH . 'styles/resource/css/ingame/main.css');
		$start = strpos($css, '/* Marketplace (game.php?page=marketPlace) */');
		$this->assertNotFalse($start);
		$block = substr($css, (int) $start);
		$this->assertMatchesRegularExpression('/@media screen and \(max-width: 699px\)/', $block);
		$this->assertStringContainsString('.market-table thead {', $block);
		$this->assertStringContainsString('content: attr(data-label);', $block);
		$this->assertMatchesRegularExpression('/\.market \{\s*padding: 0 4px calc\(56px \+ env\(safe-area-inset-bottom/', $block);
	}

	public function testHowItWorksShowsTheFairPlayRules(): void
	{
		$html = $this->render(['rulesText' => 'Fair-play rules: asking prices must be between 0.5x and 2x']);
		$this->assertStringContainsString('class="market__rules">Fair-play rules: asking prices must be between 0.5x and 2x', $html);
	}

	public function testRuleBlockReasonIsShownOnDisabledBuy(): void
	{
		$html = $this->render(['resourceOffers' => [$this->offer(6, false, '24-hour limit with this seller reached (0 of 1.000.000 left, valued in deuterium)')]]);
		[$row] = $this->rowsHtml($html);
		$this->assertMatchesRegularExpression('/market-buy__btn"\s+disabled/', $row);
		$this->assertStringContainsString('24-hour limit with this seller reached', $row);
	}

	public function testFuelHintShownOnPageAndTradeForm(): void
	{
		$this->assertStringContainsString('market_fuel_cargo_hint', (string) file_get_contents(self::tplPath('page.fleetStep2.default.tpl')));
		$html = $this->render([]);
		$this->assertStringContainsString('Fuel for the flight is carried in the cargo hold', $html);
	}
}
