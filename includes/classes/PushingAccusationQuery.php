<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;

/**
 * Who is pushing to whom: one rule shared by PushingDetectionCronjob, PvE raids,
 * salvage packages and the marketplace buy guard.
 *
 * A pair (weaker source -> stronger destination, current total points) is
 * flagged when, inside 14 days, it has more than 5 pushes:
 *  - transport fleets (mission 3) from source to destination, excluding the two
 *    legs of a marketplace trade (the buy path logs them as transports), plus
 *  - completed marketplace trades where the weaker side gave at least
 *    MarketAbuseRules::PUSH_DEAL_RATIO times what it got back.
 */
class PushingAccusationQuery
{
	/**
	 * Per-pair transport counts, weaker sender -> stronger owner of the target planet.
	 *
	 * @return list<array{source_id: int|string, dest_id: int|string, source_name: string, dest_name: string, fleet_count: int|string}>
	 */
	public static function transportPairs(int $universe, int $cutoff): array
	{
		$sql = 'SELECT u1.id AS source_id, u2.id AS dest_id, u1.username AS source_name, u2.username AS dest_name,
			COUNT(DISTINCT fl.fleet_id) AS fleet_count
		FROM %%LOG_FLEETS%% fl
		JOIN %%PLANETS%% p ON fl.fleet_end_id = p.id
		JOIN %%USERS%% u1 ON u1.id = fl.fleet_owner
		JOIN %%USERS%% u2 ON u2.id = p.id_owner
		JOIN %%STATPOINTS%% sp1 ON sp1.id_owner = u1.id AND sp1.stat_type = 1 AND sp1.universe = :universe
		JOIN %%STATPOINTS%% sp2 ON sp2.id_owner = u2.id AND sp2.stat_type = 1 AND sp2.universe = :universe
		WHERE fl.fleet_mission = 3
		  AND fl.fleet_owner != p.id_owner
		  AND sp1.total_points < sp2.total_points
		  AND fl.start_time > :cutoff
		  AND p.universe = :universe
		  AND NOT EXISTS (
			SELECT 1 FROM %%TRADES%% mt
			WHERE mt.seller_fleet_id = fl.fleet_id OR mt.buyer_fleet_id = fl.fleet_id
		  )
		GROUP BY u1.id, u2.id, u1.username, u2.username;';

		return Database::get()->select($sql, [
			':universe' => $universe,
			':cutoff'   => $cutoff,
		]);
	}

	/**
	 * Flagged pairs with names and how many transports / trades each has.
	 *
	 * @return list<array{source_id: int, dest_id: int, source_name: string, dest_name: string, transports: int, trades: int}>
	 */
	public static function pushPairs(int $universe, ?int $now = null): array
	{
		$cutoff = ($now ?? TIMESTAMP) - MarketAbuseRules::PUSH_WINDOW_SECONDS;

		$tradeRows = MarketTradeLedger::completedSince(Database::get(), $universe, $cutoff);
		$tradePushes = MarketTradeLedger::pushesFromRows(
			$tradeRows,
			is_array($GLOBALS['pricelist'] ?? null) ? $GLOBALS['pricelist'] : array(),
			MarketPlaceService::REFERENCE_RATIO
		);

		return MarketAbuseRules::mergePushPairs(self::transportPairs($universe, $cutoff), $tradePushes);
	}

	/**
	 * User IDs that GM mail names as push destinations (stronger receivers).
	 *
	 * @return list<int>
	 */
	public static function accusedReceiverIds(int $universe, ?int $now = null): array
	{
		$ids = [];
		foreach (self::pushPairs($universe, $now) as $pair) {
			$ids[$pair['dest_id']] = true;
		}

		return array_keys($ids);
	}

	public static function isAccusedReceiver(int $userId, int $universe, ?int $now = null): bool
	{
		return in_array($userId, self::accusedReceiverIds($universe, $now), true);
	}
}
