<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;

/**
 * Who is pushing to whom: one shared report used by PushingDetectionCronjob,
 * PvE raids, salvage, PvE packages and the marketplace buy guard.
 *
 * Value moved in the last 14 days from weaker accounts to stronger ones
 * (current total points; see PushingRules) =
 *  - resources carried by transports (mission 3) that were not recalled,
 *    excluding the two legs of a marketplace trade, plus
 *  - the surplus of lopsided marketplace trades (MarketAbuseRules::tradePush).
 * A receiver is flagged by PushingRules::buildReport (per-pair value or funnel).
 *
 * The report is computed once per universe and time per request and cached,
 * so salvage / PvE callers that ask per fleet do not rerun the universe-wide query.
 */
class PushingAccusationQuery
{
	/** @var array<string, list<array<string, mixed>>> */
	private static array $cache = array();

	public static function resetCache(): void
	{
		self::$cache = array();
	}

	/**
	 * Per-pair transport totals, weaker sender -> stronger owner of the target planet.
	 *
	 * @return list<array{source_id: int|string, dest_id: int|string, source_name: string, dest_name: string, fleet_count: int|string, value: float}>
	 */
	public static function transportPairs(int $universe, int $cutoff): array
	{
		$sql = 'SELECT u1.id AS source_id, u2.id AS dest_id, u1.username AS source_name, u2.username AS dest_name,
			COUNT(DISTINCT fl.fleet_id) AS fleet_count,
			SUM(fl.fleet_resource_metal) AS metal,
			SUM(fl.fleet_resource_crystal) AS crystal,
			SUM(fl.fleet_resource_deuterium) AS deuterium
		FROM %%LOG_FLEETS%% fl
		JOIN %%PLANETS%% p ON fl.fleet_end_id = p.id
		JOIN %%USERS%% u1 ON u1.id = fl.fleet_owner
		JOIN %%USERS%% u2 ON u2.id = p.id_owner
		JOIN %%STATPOINTS%% sp1 ON sp1.id_owner = u1.id AND sp1.stat_type = 1 AND sp1.universe = :universe
		JOIN %%STATPOINTS%% sp2 ON sp2.id_owner = u2.id AND sp2.stat_type = 1 AND sp2.universe = :universe
		WHERE fl.fleet_mission = 3
		  AND fl.fleet_state != 2
		  AND fl.fleet_owner != p.id_owner
		  AND sp1.total_points < sp2.total_points
		  AND fl.start_time > :cutoff
		  AND p.universe = :universe
		  AND NOT EXISTS (
			SELECT 1 FROM %%TRADES%% mt
			WHERE mt.seller_fleet_id = fl.fleet_id OR mt.buyer_fleet_id = fl.fleet_id
		  )
		GROUP BY u1.id, u2.id, u1.username, u2.username;';

		$rows = Database::get()->select($sql, [
			':universe' => $universe,
			':cutoff'   => $cutoff,
		]);

		$pairs = [];
		foreach ($rows as $row) {
			$row['value'] = PushingRules::resourceValue(
				(float) ($row['metal'] ?? 0),
				(float) ($row['crystal'] ?? 0),
				(float) ($row['deuterium'] ?? 0),
				MarketPlaceService::REFERENCE_RATIO
			);
			$pairs[] = $row;
		}

		return $pairs;
	}

	/**
	 * Flagged receivers with their sources, counts and value moved (cached per universe + time).
	 *
	 * @return list<array{dest_id: int, dest_name: string, total_value: float, reasons: list<string>, sources: list<array<string, mixed>>}>
	 */
	public static function report(int $universe, ?int $now = null): array
	{
		$now ??= TIMESTAMP;
		$key = $universe . ':' . $now;
		if (isset(self::$cache[$key])) {
			return self::$cache[$key];
		}

		$cutoff = $now - PushingRules::WINDOW_SECONDS;
		$tradePushes = MarketTradeLedger::pushesFromRows(
			MarketTradeLedger::completedSince(Database::get(), $universe, $cutoff),
			is_array($GLOBALS['pricelist'] ?? null) ? $GLOBALS['pricelist'] : array(),
			MarketPlaceService::REFERENCE_RATIO
		);

		return self::$cache[$key] = PushingRules::buildReport(self::transportPairs($universe, $cutoff), $tradePushes);
	}

	/**
	 * User IDs flagged as push receivers (stronger accounts).
	 *
	 * @return list<int>
	 */
	public static function accusedReceiverIds(int $universe, ?int $now = null): array
	{
		return array_map(static fn(array $row): int => (int) $row['dest_id'], self::report($universe, $now));
	}

	public static function isAccusedReceiver(int $userId, int $universe, ?int $now = null): bool
	{
		return in_array($userId, self::accusedReceiverIds($universe, $now), true);
	}
}
