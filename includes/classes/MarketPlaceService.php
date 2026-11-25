<?php

namespace HiveNova\Core;

/**
 * Marketplace (Trade mission 16) rules shared by the page, the buy path and tests.
 *
 * Offers are seller fleets holding in a trading zone with a %%TRADES%% row.
 * Pure rules plus database queries that take DatabaseInterface explicitly.
 */
class MarketPlaceService
{
	public const SHIP_LIGHT_CARGO = 202;
	public const SHIP_HEAVY_CARGO = 203;

	/** shipType request values: 1 = light cargo first, anything else = heavy cargo first. */
	public const PAY_LIGHT_FIRST = 1;
	public const PAY_HEAVY_FIRST = 2;

	public const DIPLO_WAR = 5;

	public const VISIBILITY_ALL = 0;
	public const VISIBILITY_PARTNERS = 1;
	public const VISIBILITY_NO_ENEMIES = 2;

	public const CSRF_SESSION_KEY = 'marketplace_csrf';
	public const FLASH_SESSION_KEY = 'marketplace_flash';

	/** Reference ratio (metal : crystal : deuterium) for deal ratings and the anti-abuse rules. */
	public const REFERENCE_RATIO = array(4, 2, 1);

	/** Deal rating thresholds around 1.0 (offer value / price value at the reference ratio). */
	public const DEAL_GOOD_AT = 1.05;
	public const DEAL_BAD_BELOW = 0.95;

	public static function isOwnOffer(array $offer, int $userId): bool
	{
		return (int) ($offer['fleet_owner'] ?? 0) === $userId;
	}

	/**
	 * Active unsold Trade offers holding in the given universe, with the buyer's
	 * resolved alliance pact level (war wins, deduplicated per alliance).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function listActiveOffers(DatabaseInterface $db, int $universe, int $buyerAllyId): array
	{
		// u.* is needed: seller drive/armour techs feed delivery time and the fleet-market tech rule.
		$sql = 'SELECT f.*, t.*, u.*, packts.level AS diplo_level
			FROM %%FLEETS%% f
			JOIN %%USERS%% u ON f.fleet_owner = u.id
			JOIN %%TRADES%% t ON f.fleet_id = t.seller_fleet_id
			LEFT JOIN (
				SELECT al, CASE WHEN MAX(level = :war) = 1 THEN :war ELSE MIN(level) END AS level
				FROM (
					SELECT owner_2 AS al, level FROM %%DIPLO%% WHERE owner_1 = :al AND accept = 1
					UNION ALL
					SELECT owner_1 AS al, level FROM %%DIPLO%% WHERE owner_2 = :al AND accept = 1
				) pacts
				GROUP BY al
			) packts ON packts.al = u.ally_id AND u.ally_id <> 0
			WHERE f.fleet_mission = :mission
			  AND f.fleet_mess = :hold
			  AND f.fleet_universe = :universe
			  AND t.buyer_fleet_id IS NULL
			ORDER BY f.fleet_end_stay ASC;';
		return $db->select($sql, array(
			':al' => $buyerAllyId,
			':war' => self::DIPLO_WAR,
			':mission' => FLEET_MISSION_TRADE,
			':hold' => FLEET_HOLD,
			':universe' => $universe,
		));
	}

	/**
	 * Pre-lock lookup of an active, unsold Trade offer in the buyer's universe.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function findBuyableOffer(DatabaseInterface $db, int $fleetId, int $universe): ?array
	{
		if ($fleetId <= 0) {
			return null;
		}
		$row = $db->selectSingle(
			'SELECT f.*, t.*, u.*
			FROM %%FLEETS%% f
			JOIN %%TRADES%% t ON f.fleet_id = t.seller_fleet_id
			JOIN %%USERS%% u ON f.fleet_owner = u.id
			WHERE f.fleet_id = :fleet_id
			  AND f.fleet_mission = :mission
			  AND f.fleet_mess = :hold
			  AND f.fleet_universe = :universe
			  AND t.buyer_fleet_id IS NULL;',
			array(
				':fleet_id' => $fleetId,
				':mission' => FLEET_MISSION_TRADE,
				':hold' => FLEET_HOLD,
				':universe' => $universe,
			)
		);
		return is_array($row) ? $row : null;
	}

	/**
	 * Row-locked lookup of an active, unsold Trade offer in the buyer's universe.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function lockBuyableOffer(DatabaseInterface $db, int $fleetId, int $universe): ?array
	{
		if ($fleetId <= 0) {
			return null;
		}
		$row = $db->selectSingle(
			'SELECT f.*, t.*
			FROM %%FLEETS%% f
			JOIN %%TRADES%% t ON f.fleet_id = t.seller_fleet_id
			WHERE f.fleet_id = :fleet_id
			  AND f.fleet_mission = :mission
			  AND f.fleet_mess = :hold
			  AND f.fleet_universe = :universe
			  AND t.buyer_fleet_id IS NULL
			FOR UPDATE;',
			array(
				':fleet_id' => $fleetId,
				':mission' => FLEET_MISSION_TRADE,
				':hold' => FLEET_HOLD,
				':universe' => $universe,
			)
		);
		return is_array($row) ? $row : null;
	}

	/**
	 * Recent completed resource trades in the given universe.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function resourceTradeHistory(DatabaseInterface $db, int $universe, int $limit): array
	{
		$sql = 'SELECT
			buy_time as time,
			ex_resource_type as res_type,
			ex_resource_amount as amount,
			seller.fleet_resource_metal as metal,
			seller.fleet_resource_crystal as crystal,
			seller.fleet_resource_deuterium as deuterium
			FROM %%TRADES%%
			JOIN %%LOG_FLEETS%% seller ON seller.fleet_id = seller_fleet_id
			JOIN %%LOG_FLEETS%% buyer ON buyer.fleet_id = buyer_fleet_id
			WHERE transaction_type = 0
			  AND seller.fleet_universe = :universe
			ORDER BY time DESC LIMIT :limit;';
		return $db->select($sql, array(
			':universe' => $universe,
			':limit' => $limit,
		));
	}

	/**
	 * Recent completed fleet trades in the given universe.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function fleetTradeHistory(DatabaseInterface $db, int $universe, int $limit): array
	{
		$sql = 'SELECT
			seller.fleet_array as fleet,
			buy_time as time,
			ex_resource_type as res_type,
			ex_resource_amount as amount
			FROM %%TRADES%%
			JOIN %%LOG_FLEETS%% seller ON seller.fleet_id = seller_fleet_id
			JOIN %%LOG_FLEETS%% buyer ON buyer.fleet_id = buyer_fleet_id
			WHERE transaction_type = 1
			  AND seller.fleet_universe = :universe
			ORDER BY time DESC LIMIT :limit;';
		return $db->select($sql, array(
			':universe' => $universe,
			':limit' => $limit,
		));
	}

	/**
	 * One diplomacy level per alliance pair. Duplicate or two-way pact rows used to
	 * duplicate offers in the list. War wins over any pact; otherwise the closest pact.
	 *
	 * @param list<array{level?: mixed, accept?: mixed}> $pacts
	 */
	public static function resolveDiploLevel(array $pacts): ?int
	{
		$levels = array();
		foreach ($pacts as $pact) {
			if (isset($pact['accept']) && (int) $pact['accept'] !== 1) {
				continue;
			}
			if (!isset($pact['level']) || $pact['level'] === null || $pact['level'] === '') {
				continue;
			}
			$levels[] = (int) $pact['level'];
		}
		if ($levels === array()) {
			return null;
		}
		if (in_array(self::DIPLO_WAR, $levels, true)) {
			return self::DIPLO_WAR;
		}
		return min($levels);
	}

	/**
	 * Returns a language key when the seller's visibility filter blocks this buyer.
	 */
	public static function diploBlockKey(int $visibility, ?int $level, int $sellerAllyId, int $buyerAllyId): ?string
	{
		if ($visibility === self::VISIBILITY_NO_ENEMIES && $level === self::DIPLO_WAR) {
			return 'market_buyable_no_enemies';
		}
		$sameAlliance = $buyerAllyId !== 0 && $buyerAllyId === $sellerAllyId;
		if ($visibility === self::VISIBILITY_PARTNERS && !$sameAlliance && ($level === null || $level > 3)) {
			return 'market_buyable_only_trade_partners';
		}
		return null;
	}

	/**
	 * Fleet market only: ships keep the seller's tech, so a buyer with better
	 * weapons, shields or armour may not buy them (original 2Moons/Steemnova rule).
	 *
	 * @param array{0: int|float, 1: int|float, 2: int|float} $buyer attack, defensive, shield levels incl. factors
	 * @param array{0: int|float, 1: int|float, 2: int|float} $seller
	 */
	public static function techBlockKey(array $buyer, array $seller): ?string
	{
		for ($i = 0; $i < 3; $i++) {
			if ($buyer[$i] > $seller[$i]) {
				return 'market_buyable_no_tech';
			}
		}
		return null;
	}

	/**
	 * Same sizing the buy path has always used: fill with the preferred cargo
	 * type first, then top up with the other one.
	 *
	 * @param array<int, float|int> $capacity per-ship cargo keyed by ship id (202, 203)
	 * @param array<int, float|int> $available ships on the planet keyed by ship id
	 * @return array{fleet: array<int, int>, missing: float}
	 */
	public static function planPickup(float $amount, int $payWith, array $capacity, array $available): array
	{
		$first = $payWith === self::PAY_LIGHT_FIRST ? self::SHIP_LIGHT_CARGO : self::SHIP_HEAVY_CARGO;
		$second = $first === self::SHIP_LIGHT_CARGO ? self::SHIP_HEAVY_CARGO : self::SHIP_LIGHT_CARGO;

		$fleet = array($first => 0, $second => 0);
		$left = $amount;
		foreach (array($first, $second) as $shipId) {
			if ($left <= 0) {
				break;
			}
			$cap = (float) ($capacity[$shipId] ?? 0);
			$need = (int) ceil($left / max($cap, 1));
			$fleet[$shipId] = (int) min((float) ($available[$shipId] ?? 0), $need);
			$left -= $fleet[$shipId] * $cap;
		}

		return array(
			'fleet' => array_filter($fleet),
			'missing' => max(0.0, (float) $left),
		);
	}

	/**
	 * Pickup fleet sized for the payment plus its own fuel. Fuel rides in the cargo
	 * hold (same rule as every fleet send), so sizing for the payment alone could
	 * show a ship count that is one short. Iterates because more ships burn more fuel.
	 *
	 * @param array<int, float|int> $capacity
	 * @param array<int, float|int> $available
	 * @param callable(array<int, int>): float $fuelFor fuel for a given fleet
	 * @return array{fleet: array<int, int>, missing: float, fuel: float}
	 */
	public static function planPickupWithFuel(float $amount, int $payWith, array $capacity, array $available, callable $fuelFor): array
	{
		$fuel = 0.0;
		$plan = self::planPickup($amount, $payWith, $capacity, $available);
		for ($i = 0; $i < 8; $i++) {
			if ($plan['fleet'] === array()) {
				break;
			}
			$fuel = (float) $fuelFor($plan['fleet']);
			$next = self::planPickup($amount + $fuel, $payWith, $capacity, $available);
			if ($next['fleet'] === $plan['fleet']) {
				$plan = $next;
				break;
			}
			$plan = $next;
		}
		if ($plan['fleet'] !== array()) {
			$fuel = (float) $fuelFor($plan['fleet']);
			$room = 0.0;
			foreach ($plan['fleet'] as $shipId => $count) {
				$room += $count * (float) ($capacity[$shipId] ?? 0);
			}
			$plan['missing'] = max($plan['missing'], $amount + $fuel - $room);
		}
		$plan['fuel'] = $fuel;
		return $plan;
	}

	/**
	 * Row colour class: war = enemy; same alliance (never "no alliance" = 0) or pact <= 3 = ally.
	 */
	public static function diploClass(?int $level, int $sellerAllyId, int $buyerAllyId): string
	{
		if ($level === self::DIPLO_WAR) {
			return 'trade-enemy';
		}
		if (($level !== null && $level <= 3) || ($sellerAllyId !== 0 && $sellerAllyId === $buyerAllyId)) {
			return 'trade-ally';
		}
		return '';
	}

	/**
	 * First reason the current planet cannot buy, as a language key, or null.
	 * $ruleKey is MarketAbuseRules::buyRuleKey() (flagged buyer, price band, daily cap).
	 *
	 * @param array{901: float|int, 902: float|int, 903: float|int} $planetResources
	 * @param array{901: float|int, 902: float|int, 903: float|int} $price
	 */
	public static function buyBlockKey(
		bool $isOwn,
		?string $diploKey,
		?string $techKey,
		bool $hasFreeSlot,
		float $missingCargo,
		array $planetResources,
		array $price,
		float $fuel,
		?string $ruleKey = null
	): ?string {
		if ($isOwn) {
			return 'market_your_offer';
		}
		if ($diploKey !== null) {
			return $diploKey;
		}
		if ($techKey !== null) {
			return $techKey;
		}
		if ($ruleKey !== null) {
			return $ruleKey;
		}
		if (!$hasFreeSlot) {
			return 'market_reason_no_slots';
		}
		if ($missingCargo > 0) {
			return 'market_reason_ships';
		}
		if ($planetResources[901] < $price[901]
			|| $planetResources[902] < $price[902]
			|| $planetResources[903] < $price[903] + $fuel) {
			return 'market_reason_resources';
		}
		return null;
	}

	/**
	 * Value of a resource bundle in reference units (metal : crystal : deuterium).
	 *
	 * @param array{901?: float|int, 902?: float|int, 903?: float|int} $bundle
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 */
	public static function referenceValue(array $bundle, array $ratio): float
	{
		$value = 0.0;
		foreach (array(901 => 0, 902 => 1, 903 => 2) as $resId => $i) {
			$weight = (float) $ratio[$i];
			if ($weight <= 0) {
				continue;
			}
			$value += (float) ($bundle[$resId] ?? 0) / $weight;
		}
		return $value;
	}

	/**
	 * What you get divided by what you pay, both at the reference ratio. 1.0 = fair.
	 *
	 * @param array{901?: float|int, 902?: float|int, 903?: float|int} $offer
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 */
	public static function dealRatio(array $offer, int $wantedType, float $wantedAmount, array $ratio): ?float
	{
		$price = self::referenceValue(MarketPlaceResource::amounts($wantedType, $wantedAmount), $ratio);
		if ($price <= 0) {
			return null;
		}
		return self::referenceValue($offer, $ratio) / $price;
	}

	public static function dealRating(?float $ratio): string
	{
		if ($ratio === null) {
			return 'none';
		}
		if ($ratio >= self::DEAL_GOOD_AT) {
			return 'good';
		}
		if ($ratio < self::DEAL_BAD_BELOW) {
			return 'bad';
		}
		return 'fair';
	}

	/**
	 * Why an offer cannot be cancelled by this user, or null when it can.
	 *
	 * @param array<string, mixed>|null $row fleet row joined with its trade row
	 */
	public static function cancelBlockKey(?array $row, int $userId): ?string
	{
		if ($row === null) {
			return 'market_p_msg_not_found';
		}
		if ((int) ($row['fleet_owner'] ?? 0) !== $userId) {
			return 'market_p_msg_not_found';
		}
		if ((int) ($row['fleet_mission'] ?? 0) !== FLEET_MISSION_TRADE
			|| (int) ($row['fleet_mess'] ?? -1) !== FLEET_HOLD
			|| (int) ($row['fleet_no_m_return'] ?? 0) !== 0
			|| ($row['buyer_fleet_id'] ?? null) !== null) {
			return 'market_p_msg_cannot_cancel';
		}
		return null;
	}

	/**
	 * Recall a holding, unsold offer and drop its trade row. Returns null on
	 * success or a language key. Locks the fleet + trade rows so a concurrent
	 * buy (which also locks them) cannot claim the offer mid-cancel.
	 *
	 * @param callable(array<string, mixed>, int): bool $sendBack defaults to FleetFunctions::SendFleetBack
	 */
	public static function cancelOffer(DatabaseInterface $db, int $userId, int $fleetId, ?callable $sendBack = null): ?string
	{
		if ($fleetId <= 0) {
			return 'market_p_msg_not_found';
		}
		$sendBack ??= static fn(array $user, int $id): bool => (bool) FleetFunctions::SendFleetBack($user, $id);

		$db->beginTransaction();
		try {
			$row = $db->selectSingle(
				'SELECT f.fleet_id, f.fleet_owner, f.fleet_mission, f.fleet_mess, f.fleet_no_m_return, t.buyer_fleet_id
				FROM %%FLEETS%% f
				JOIN %%TRADES%% t ON t.seller_fleet_id = f.fleet_id
				WHERE f.fleet_id = :fleetId
				FOR UPDATE;',
				array(':fleetId' => $fleetId)
			);
			$block = self::cancelBlockKey(is_array($row) ? $row : null, $userId);
			if ($block !== null) {
				$db->rollback();
				return $block;
			}
			if (!$sendBack(array('id' => $userId), $fleetId)) {
				$db->rollback();
				return 'market_p_msg_cannot_cancel';
			}
			$db->delete(
				'DELETE FROM %%TRADES%% WHERE seller_fleet_id = :fleetId AND buyer_fleet_id IS NULL;',
				array(':fleetId' => $fleetId)
			);
			$db->commit();
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}
		return null;
	}

	/**
	 * Per-session token for buy / cancel posts. Stable for the session so several
	 * open tabs keep working.
	 *
	 * @param array<string, mixed> $session
	 */
	public static function csrfToken(array &$session): string
	{
		$token = $session[self::CSRF_SESSION_KEY] ?? '';
		if (!is_string($token) || strlen($token) < 32) {
			$token = bin2hex(random_bytes(16));
			$session[self::CSRF_SESSION_KEY] = $token;
		}
		return $token;
	}

	/**
	 * @param array<string, mixed> $session
	 */
	public static function validCsrf(array $session, mixed $token): bool
	{
		$expected = $session[self::CSRF_SESSION_KEY] ?? '';
		return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
	}

	/**
	 * @param array<string, mixed> $session
	 */
	public static function pushFlash(array &$session, string $message, bool $ok): void
	{
		$session[self::FLASH_SESSION_KEY] = array('message' => $message, 'ok' => $ok);
	}

	/**
	 * @param array<string, mixed> $session
	 * @return array{message: string, ok: bool}|null
	 */
	public static function pullFlash(array &$session): ?array
	{
		$flash = $session[self::FLASH_SESSION_KEY] ?? null;
		unset($session[self::FLASH_SESSION_KEY]);
		if (!is_array($flash) || !isset($flash['message']) || !is_string($flash['message']) || $flash['message'] === '') {
			return null;
		}
		return array('message' => $flash['message'], 'ok' => (bool) ($flash['ok'] ?? false));
	}

	public static function normalizeTab(mixed $tab): string
	{
		return $tab === 'fleet' ? 'fleet' : 'resources';
	}

	public static function normalizePayWith(mixed $shipType): int
	{
		return (int) $shipType === self::PAY_LIGHT_FIRST ? self::PAY_LIGHT_FIRST : self::PAY_HEAVY_FIRST;
	}

	/**
	 * "2 LC + 1 HC" style summary.
	 *
	 * @param array<int, int> $fleet
	 * @param array<int|string, string> $shortNames
	 */
	public static function fleetSummary(array $fleet, array $shortNames): string
	{
		$parts = array();
		foreach ($fleet as $shipId => $count) {
			if ($count <= 0) {
				continue;
			}
			$parts[] = number_format((float) $count, 0, ',', '.') . ' ' . ($shortNames[$shipId] ?? (string) $shipId);
		}
		return implode(' + ', $parts);
	}
}
