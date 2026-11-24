<?php

use PHPUnit\Framework\TestCase;

class MarketplaceBuyGuardTest extends TestCase
{
	public function testDoBuyClaimsTradeOnlyWhenBuyerFleetIdIsNull(): void
	{
		$source = file_get_contents(__DIR__ . '/../../includes/pages/game/ShowMarketPlacePage.php');
		$this->assertIsString($source);
		$this->assertStringContainsString('buyer_fleet_id IS NULL', $source);
		$this->assertStringContainsString('FOR UPDATE', $source);
		$this->assertStringContainsString('beginTransaction', $source);
	}

	public function testRequireModuleIsMissionTrade(): void
	{
		$source = file_get_contents(__DIR__ . '/../../includes/pages/game/ShowMarketPlacePage.php');
		$this->assertIsString($source);
		$this->assertMatchesRegularExpression(
			'/public static \$requireModule = MODULE_MISSION_TRADE;/',
			$source
		);
		$this->assertStringNotContainsString(
			'public static $requireModule = MODULE_BATTLEHALL;',
			$source
		);
	}

	public function testDoBuyRejectsOwnOfferBeforeAndInsideTheLock(): void
	{
		$source = (string) file_get_contents(__DIR__ . '/../../includes/pages/game/ShowMarketPlacePage.php');
		preg_match('/private function doBuy\(\).*?\n\t}\n/s', $source, $m);
		$this->assertNotEmpty($m, 'doBuy not found');
		$doBuy = $m[0];
		$this->assertSame(2, substr_count($doBuy, 'MarketPlaceService::isOwnOffer('));
		$this->assertStringContainsString("market_p_msg_own_offer", $doBuy);
		// The pre-lock own check runs before any resources are touched.
		$this->assertLessThan(strpos($doBuy, 'beginTransaction'), strpos($doBuy, 'market_p_msg_own_offer'));
	}

	public function testBuyAndCancelRequirePostAndCsrfAndRedirect(): void
	{
		$source = (string) file_get_contents(__DIR__ . '/../../includes/pages/game/ShowMarketPlacePage.php');
		$this->assertStringContainsString("\$_SERVER['REQUEST_METHOD'] === 'POST'", $source);
		$this->assertStringContainsString('MarketPlaceService::validCsrf(', $source);
		$this->assertStringContainsString('MarketPlaceService::cancelOffer(', $source);
		$this->assertStringContainsString('$this->redirectTo(', $source);
	}

	public function testOffersBuyAndHistoryAreScopedToPlayerUniverse(): void
	{
		$source = (string) file_get_contents(__DIR__ . '/../../includes/pages/game/ShowMarketPlacePage.php');
		$this->assertStringContainsString('MarketPlaceService::listActiveOffers(', $source);
		$this->assertStringContainsString('MarketPlaceService::findBuyableOffer(', $source);
		$this->assertStringContainsString('MarketPlaceService::lockBuyableOffer(', $source);
		$this->assertStringContainsString('MarketPlaceService::resourceTradeHistory(', $source);
		$this->assertStringContainsString('MarketPlaceService::fleetTradeHistory(', $source);
	}
}
