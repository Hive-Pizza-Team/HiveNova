<?php

declare(strict_types=1);

use HiveNova\Core\MarketAbuseRules;
use HiveNova\Core\MarketPlaceService;
use PHPUnit\Framework\TestCase;

class MarketAbuseRulesTest extends TestCase
{
	private const RATIO = MarketPlaceService::REFERENCE_RATIO;

	/** @return array<int, array<string, mixed>> */
	private function pricelist(): array
	{
		return [
			202 => ['cost' => [901 => 2000, 902 => 2000, 903 => 0]],
			203 => ['cost' => [901 => 6000, 902 => 6000, 903 => 0]],
			207 => ['cost' => [901 => 45000, 902 => 15000, 903 => 0]],
		];
	}

	public function testDefaultsAreTheApprovedOnes(): void
	{
		$this->assertSame(0.5, MarketAbuseRules::BAND_MIN);
		$this->assertSame(2.0, MarketAbuseRules::BAND_MAX);
		$this->assertSame(24, MarketAbuseRules::maxHoldHours());
		$this->assertSame(0.01, MarketAbuseRules::LISTING_FEE_RATE);
		$this->assertSame(1000000.0, MarketAbuseRules::DAILY_CAP_VALUE);
		$this->assertSame([1 => 1, 2 => 2, 4 => 4, 8 => 8, 12 => 12, 24 => 24], MarketAbuseRules::tradeStayBlock());
	}

	public function testBandAcceptsBoundsAndRejectsOutside(): void
	{
		$this->assertTrue(MarketAbuseRules::inBand(1.0));
		$this->assertTrue(MarketAbuseRules::inBand(0.5));
		$this->assertTrue(MarketAbuseRules::inBand(2.0));
		$this->assertFalse(MarketAbuseRules::inBand(0.49));
		$this->assertFalse(MarketAbuseRules::inBand(2.01));
		$this->assertFalse(MarketAbuseRules::inBand(null));
	}

	public function testDealRatioIsBuyerGetsOverBuyerPays(): void
	{
		// 100k crystal (50k units) for 100k metal (25k units): buyer gets 2x.
		$goods = MarketPlaceService::referenceValue([902 => 100000], self::RATIO);
		$price = MarketAbuseRules::priceValue(1, 100000, self::RATIO);
		$this->assertSame(2.0, MarketAbuseRules::dealRatio($goods, $price));
		$this->assertNull(MarketAbuseRules::dealRatio(0.0, $price));
		$this->assertNull(MarketAbuseRules::dealRatio($goods, 0.0));
		$this->assertSame(0.0, MarketAbuseRules::priceValue(9, 100, self::RATIO));
	}

	public function testAllowedPriceRangeMatchesTheBand(): void
	{
		// 60k crystal = 30k units. Metal: 60k..240k; crystal 30k..120k; deuterium 15k..60k.
		$this->assertSame(['min' => 60000.0, 'max' => 240000.0], MarketAbuseRules::allowedPriceRange(30000, 1, self::RATIO));
		$this->assertSame(['min' => 30000.0, 'max' => 120000.0], MarketAbuseRules::allowedPriceRange(30000, 2, self::RATIO));
		$this->assertSame(['min' => 15000.0, 'max' => 60000.0], MarketAbuseRules::allowedPriceRange(30000, 3, self::RATIO));
		$this->assertNull(MarketAbuseRules::allowedPriceRange(30000, 0, self::RATIO));
		$this->assertNull(MarketAbuseRules::allowedPriceRange(0, 1, self::RATIO));

		foreach ([1, 2, 3] as $type) {
			$range = MarketAbuseRules::allowedPriceRange(30000, $type, self::RATIO);
			foreach ([$range['min'], $range['max']] as $amount) {
				$ratio = MarketAbuseRules::dealRatio(30000, MarketAbuseRules::priceValue($type, $amount, self::RATIO));
				$this->assertTrue(MarketAbuseRules::inBand($ratio), "bound $amount of type $type must be accepted");
			}
		}
	}

	public function testFleetAndGoodsValue(): void
	{
		// LC 2000/2000 = 500 + 1000 = 1500 units; Battleship 45000/15000 = 11250 + 7500 = 18750.
		$this->assertSame(1500.0 * 3 + 18750.0, MarketAbuseRules::fleetValue([202 => 3, 207 => 1], $this->pricelist(), self::RATIO));
		$this->assertSame(0.0, MarketAbuseRules::fleetValue([999 => 5, 202 => 0], $this->pricelist(), self::RATIO));
		$this->assertSame(18750.0, MarketAbuseRules::goodsValue(1, [901 => 400000], [207 => 1], $this->pricelist(), self::RATIO));
		$this->assertSame(100000.0, MarketAbuseRules::goodsValue(0, [901 => 400000], [207 => 1], $this->pricelist(), self::RATIO));
	}

	public function testListingFeeIsOnePercentOfShipsAndCargoInDeuterium(): void
	{
		// 10 HC = 45k units, 400k metal = 100k units => 1% of 145k = 1450 deuterium.
		$this->assertSame(1450.0, MarketAbuseRules::listingFee([203 => 10], [901 => 400000], $this->pricelist(), self::RATIO));
		// Rounds up, never below 1.
		$this->assertSame(1.0, MarketAbuseRules::listingFee([], [903 => 10], $this->pricelist(), self::RATIO));
		$this->assertSame(16.0, MarketAbuseRules::listingFee([202 => 1], [903 => 1], $this->pricelist(), self::RATIO));
	}

	public function testDailyCap(): void
	{
		$this->assertFalse(MarketAbuseRules::exceedsDailyCap(0, 1000000));
		$this->assertTrue(MarketAbuseRules::exceedsDailyCap(1, 1000000));
		$this->assertTrue(MarketAbuseRules::exceedsDailyCap(900000, 100001));
		$this->assertSame(100000.0, MarketAbuseRules::dailyCapLeft(900000));
		$this->assertSame(0.0, MarketAbuseRules::dailyCapLeft(2000000));
	}

	public function testBuyRuleKeyOrder(): void
	{
		$this->assertSame('market_reason_flagged', MarketAbuseRules::buyRuleKey(true, 5.0, 2000000, 10));
		$this->assertSame('market_reason_band', MarketAbuseRules::buyRuleKey(false, 5.0, 2000000, 10));
		$this->assertSame('market_reason_band', MarketAbuseRules::buyRuleKey(false, null, 0, 10));
		$this->assertSame('market_reason_daily_cap', MarketAbuseRules::buyRuleKey(false, 1.0, 999999, 10));
		$this->assertNull(MarketAbuseRules::buyRuleKey(false, 1.0, 0, 10));
	}

	public function testTradePushDirections(): void
	{
		// Weaker seller gives 2x: push seller -> buyer.
		$this->assertSame(['source' => 1, 'dest' => 2], MarketAbuseRules::tradePush(1, 2, 100, 5000, 2000, 1000));
		// Same trade but the seller is stronger: not a push (a stronger player giving to a weaker one).
		$this->assertNull(MarketAbuseRules::tradePush(1, 2, 5000, 100, 2000, 1000));
		// Weaker buyer overpays a stronger seller: push buyer -> seller.
		$this->assertSame(['source' => 2, 'dest' => 1], MarketAbuseRules::tradePush(1, 2, 5000, 100, 1000, 2000));
		// Under the 1.25 threshold: fair enough.
		$this->assertNull(MarketAbuseRules::tradePush(1, 2, 100, 5000, 1240, 1000));
		$this->assertSame(['source' => 1, 'dest' => 2], MarketAbuseRules::tradePush(1, 2, 100, 5000, 1250, 1000));
		// Self or empty trades never count.
		$this->assertNull(MarketAbuseRules::tradePush(1, 1, 100, 5000, 2000, 1000));
		$this->assertNull(MarketAbuseRules::tradePush(1, 2, 100, 5000, 0, 1000));
	}

	public function testMergePushPairsAddsTradesToTransports(): void
	{
		$transports = [
			['source_id' => 1, 'dest_id' => 9, 'source_name' => 'a', 'dest_name' => 'Nine', 'fleet_count' => 3],
			['source_id' => 2, 'dest_id' => 9, 'source_name' => 'b', 'dest_name' => 'Nine', 'fleet_count' => 6],
			['source_id' => 3, 'dest_id' => 8, 'source_name' => 'c', 'dest_name' => 'Eight', 'fleet_count' => 5],
		];
		$trades = [
			['source' => 1, 'dest' => 9], ['source' => 1, 'dest' => 9], ['source' => 1, 'dest' => 9],
			['source' => 4, 'dest' => 7, 'source_name' => 'd', 'dest_name' => 'Seven'],
		];
		$pairs = MarketAbuseRules::mergePushPairs($transports, $trades);
		$this->assertSame([
			['source_id' => 1, 'dest_id' => 9, 'source_name' => 'a', 'dest_name' => 'Nine', 'transports' => 3, 'trades' => 3],
			['source_id' => 2, 'dest_id' => 9, 'source_name' => 'b', 'dest_name' => 'Nine', 'transports' => 6, 'trades' => 0],
		], $pairs);

		$six = array_fill(0, 6, ['source' => 4, 'dest' => 7, 'source_name' => 'd', 'dest_name' => 'Seven']);
		$this->assertSame(
			[['source_id' => 4, 'dest_id' => 7, 'source_name' => 'd', 'dest_name' => 'Seven', 'transports' => 0, 'trades' => 6]],
			MarketAbuseRules::mergePushPairs([], $six)
		);
	}
}
