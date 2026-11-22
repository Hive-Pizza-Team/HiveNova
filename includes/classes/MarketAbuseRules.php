<?php

namespace HiveNova\Core;

/**
 * Marketplace anti-abuse rules (no IP data: IP tracking is off in this game).
 *
 * All values are in reference units at MarketPlaceService::REFERENCE_RATIO
 * (metal : crystal : deuterium = 4 : 2 : 1), so 1 unit = 1 deuterium =
 * 2 crystal = 4 metal. Bounds are constants rather than Config columns because
 * new Config fields need a schema migration; tune them here.
 *
 * Everything in this class is pure. Database reads live in MarketTradeLedger.
 */
class MarketAbuseRules
{
	/**
	 * Price band: what the buyer gets divided by what the buyer pays (the deal
	 * ratio the page already shows) must stay within [BAND_MIN, BAND_MAX].
	 * 0.5x..2x leaves room for real bargains and premiums but blocks
	 * "1 metal for 500k crystal" gift offers that move value between accounts.
	 */
	public const BAND_MIN = 0.5;
	public const BAND_MAX = 2.0;

	/**
	 * A completed trade counts as a push when the side that gives more value
	 * gives at least this multiple of what it receives (1.25 = 25% over fair)
	 * and is the weaker player. Inside the band, so ordinary bargains are not
	 * flagged unless they repeat between the same two players.
	 */
	public const PUSH_DEAL_RATIO = 1.25;

	/**
	 * Daily cap: total goods value one seller may sell to the same buyer in a
	 * rolling 24 hours (1,000,000 units = 4M metal, 2M crystal or 1M deuterium).
	 */
	public const DAILY_CAP_VALUE = 1000000.0;
	public const DAILY_CAP_WINDOW_SECONDS = 24 * 3600;

	/**
	 * Holding times offered for a Trade fleet, in hours. Was up to 168 h (a week
	 * out of reach of attackers); 24 h still lets every time zone see an offer.
	 */
	public const TRADE_HOLD_HOURS = array(1, 2, 4, 8, 12, 24);

	/**
	 * Listing fee in deuterium, charged when the Trade fleet is sent: this share
	 * of the listed value (ships + cargo). Not refunded on cancel or expiry, so
	 * parking a fleet in the trading zone always costs something.
	 */
	public const LISTING_FEE_RATE = 0.01;

	/**
	 * Hold-time selector for the Trade mission (hours => hours).
	 *
	 * @return array<int, int>
	 */
	public static function tradeStayBlock(): array
	{
		return array_combine(self::TRADE_HOLD_HOURS, self::TRADE_HOLD_HOURS);
	}

	public static function maxHoldHours(): int
	{
		return max(self::TRADE_HOLD_HOURS);
	}

	/**
	 * Build-cost value of a fleet in reference units.
	 *
	 * @param array<int, int|float> $fleet ship id => count
	 * @param array<int, array<string, mixed>> $pricelist
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 */
	public static function fleetValue(array $fleet, array $pricelist, array $ratio): float
	{
		$value = 0.0;
		foreach ($fleet as $shipId => $count) {
			$cost = $pricelist[(int) $shipId]['cost'] ?? array();
			if (!is_array($cost) || $count <= 0) {
				continue;
			}
			$value += (float) $count * MarketPlaceService::referenceValue(array(
				901 => (float) ($cost[901] ?? 0),
				902 => (float) ($cost[902] ?? 0),
				903 => (float) ($cost[903] ?? 0),
			), $ratio);
		}
		return $value;
	}

	/**
	 * Value of the goods an offer sells: the cargo for a resource offer, the
	 * ships' build cost for a fleet offer.
	 *
	 * @param array{901?: float|int, 902?: float|int, 903?: float|int} $cargo
	 * @param array<int, int|float> $fleet
	 * @param array<int, array<string, mixed>> $pricelist
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 */
	public static function goodsValue(int $marketType, array $cargo, array $fleet, array $pricelist, array $ratio): float
	{
		return $marketType === 1
			? self::fleetValue($fleet, $pricelist, $ratio)
			: MarketPlaceService::referenceValue($cargo, $ratio);
	}

	/** Value of the asked price (ex_resource_type 1-3, amount) in reference units. */
	public static function priceValue(int $wantedType, float $wantedAmount, array $ratio): float
	{
		if (MarketPlaceResource::techId($wantedType) === null) {
			return 0.0;
		}
		return MarketPlaceService::referenceValue(MarketPlaceResource::amounts($wantedType, $wantedAmount), $ratio);
	}

	/** Buyer gets / buyer pays. Null when either side has no value. */
	public static function dealRatio(float $goodsValue, float $priceValue): ?float
	{
		if ($goodsValue <= 0 || $priceValue <= 0) {
			return null;
		}
		return $goodsValue / $priceValue;
	}

	public static function inBand(?float $dealRatio): bool
	{
		// Tiny epsilon so the exact bounds shown in the form are accepted after rounding.
		return $dealRatio !== null
			&& $dealRatio >= self::BAND_MIN - 1e-9
			&& $dealRatio <= self::BAND_MAX + 1e-9;
	}

	/**
	 * Allowed asking amount for goods worth $goodsValue, in the wanted resource.
	 *
	 * @return array{min: float, max: float}|null
	 */
	public static function allowedPriceRange(float $goodsValue, int $wantedType, array $ratio): ?array
	{
		$techId = MarketPlaceResource::techId($wantedType);
		if ($techId === null || $goodsValue <= 0) {
			return null;
		}
		$weight = (float) $ratio[$techId - 901];
		if ($weight <= 0) {
			return null;
		}
		// dealRatio = goods / (amount / weight) => amount = goods * weight / dealRatio
		return array(
			'min' => ceil($goodsValue * $weight / self::BAND_MAX),
			'max' => floor($goodsValue * $weight / self::BAND_MIN),
		);
	}

	/**
	 * Listing fee in deuterium for a Trade fleet (ships + cargo), at least 1.
	 *
	 * @param array<int, int|float> $fleet
	 * @param array{901?: float|int, 902?: float|int, 903?: float|int} $cargo
	 * @param array<int, array<string, mixed>> $pricelist
	 */
	public static function listingFee(array $fleet, array $cargo, array $pricelist, array $ratio): float
	{
		$listed = self::fleetValue($fleet, $pricelist, $ratio) + MarketPlaceService::referenceValue($cargo, $ratio);
		$deutWeight = (float) $ratio[2] > 0 ? (float) $ratio[2] : 1.0;
		return max(1.0, ceil($listed * self::LISTING_FEE_RATE * $deutWeight));
	}

	public static function exceedsDailyCap(float $alreadyTraded, float $thisTrade): bool
	{
		return $alreadyTraded + $thisTrade > self::DAILY_CAP_VALUE;
	}

	public static function dailyCapLeft(float $alreadyTraded): float
	{
		return max(0.0, self::DAILY_CAP_VALUE - $alreadyTraded);
	}

	/**
	 * Marketplace-specific reason a buy is refused, as a language key, or null.
	 * Checked after the own-offer / alliance / tech rules.
	 */
	public static function buyRuleKey(bool $buyerFlagged, ?float $dealRatio, float $pairTraded, float $goodsValue): ?string
	{
		if ($buyerFlagged) {
			return 'market_reason_flagged';
		}
		if (!self::inBand($dealRatio)) {
			return 'market_reason_band';
		}
		if (self::exceedsDailyCap($pairTraded, $goodsValue)) {
			return 'market_reason_daily_cap';
		}
		return null;
	}

	/**
	 * Push direction and surplus of a completed trade, or null when it is fair
	 * enough or the side giving more is not the weaker player. The surplus (what
	 * the weaker side gave beyond what it got back) is the value pushed.
	 *
	 * @return array{source: int, dest: int, value: float}|null
	 */
	public static function tradePush(int $sellerId, int $buyerId, float $sellerPoints, float $buyerPoints, float $goodsValue, float $priceValue): ?array
	{
		if ($sellerId === $buyerId || $goodsValue <= 0 || $priceValue <= 0) {
			return null;
		}
		// Seller gave clearly more than it got, and the seller is weaker.
		if ($goodsValue >= $priceValue * self::PUSH_DEAL_RATIO && $sellerPoints < $buyerPoints) {
			return array('source' => $sellerId, 'dest' => $buyerId, 'value' => $goodsValue - $priceValue);
		}
		// Buyer overpaid a stronger seller.
		if ($priceValue >= $goodsValue * self::PUSH_DEAL_RATIO && $buyerPoints < $sellerPoints) {
			return array('source' => $buyerId, 'dest' => $sellerId, 'value' => $priceValue - $goodsValue);
		}
		return null;
	}
}
