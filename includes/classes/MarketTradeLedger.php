<?php

namespace HiveNova\Core;

/**
 * Reads completed marketplace trades (%%TRADES%% rows with a buyer) for the
 * anti-abuse rules. A trade's seller and buyer are the owners of its two
 * %%LOG_FLEETS%% rows (seller_fleet_id / buyer_fleet_id).
 */
class MarketTradeLedger
{
	/**
	 * Goods value each seller sold to this buyer in the last 24 hours, keyed by seller id.
	 *
	 * @param array<int, array<string, mixed>> $pricelist
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 * @return array<int, float>
	 */
	public static function soldToBuyer(DatabaseInterface $db, int $buyerId, array $pricelist, array $ratio): array
	{
		$rows = $db->select(
			'SELECT seller.fleet_owner AS seller_id, t.transaction_type,
				seller.fleet_array, seller.fleet_resource_metal, seller.fleet_resource_crystal, seller.fleet_resource_deuterium
			FROM %%TRADES%% t
			JOIN %%LOG_FLEETS%% seller ON seller.fleet_id = t.seller_fleet_id
			JOIN %%LOG_FLEETS%% buyer ON buyer.fleet_id = t.buyer_fleet_id
			WHERE buyer.fleet_owner = :buyerId
			  AND t.buy_time > DATE_SUB(NOW(), INTERVAL :window SECOND);',
			array(
				':buyerId' => $buyerId,
				':window' => MarketAbuseRules::DAILY_CAP_WINDOW_SECONDS,
			)
		);
		$sold = array();
		foreach ($rows as $row) {
			$sellerId = (int) $row['seller_id'];
			$sold[$sellerId] = ($sold[$sellerId] ?? 0.0) + self::goodsValue($row, $pricelist, $ratio);
		}
		return $sold;
	}

	/**
	 * Completed trades in a universe since a unix time, with both players' current points.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function completedSince(DatabaseInterface $db, int $universe, int $cutoff): array
	{
		return $db->select(
			'SELECT seller.fleet_owner AS seller_id, buyer.fleet_owner AS buyer_id,
				su.username AS seller_name, bu.username AS buyer_name,
				COALESCE(ssp.total_points, 0) AS seller_points, COALESCE(bsp.total_points, 0) AS buyer_points,
				t.transaction_type, t.ex_resource_type, t.ex_resource_amount,
				seller.fleet_array, seller.fleet_resource_metal, seller.fleet_resource_crystal, seller.fleet_resource_deuterium
			FROM %%TRADES%% t
			JOIN %%LOG_FLEETS%% seller ON seller.fleet_id = t.seller_fleet_id
			JOIN %%LOG_FLEETS%% buyer ON buyer.fleet_id = t.buyer_fleet_id
			JOIN %%USERS%% su ON su.id = seller.fleet_owner
			JOIN %%USERS%% bu ON bu.id = buyer.fleet_owner
			LEFT JOIN %%STATPOINTS%% ssp ON ssp.id_owner = su.id AND ssp.stat_type = 1 AND ssp.universe = :universe
			LEFT JOIN %%STATPOINTS%% bsp ON bsp.id_owner = bu.id AND bsp.stat_type = 1 AND bsp.universe = :universe
			WHERE seller.fleet_universe = :universe
			  AND t.buy_time > FROM_UNIXTIME(:cutoff);',
			array(
				':universe' => $universe,
				':cutoff' => $cutoff,
			)
		);
	}

	/**
	 * Pushes among completed trade rows (see MarketAbuseRules::tradePush).
	 *
	 * @param list<array<string, mixed>> $rows from completedSince()
	 * @param array<int, array<string, mixed>> $pricelist
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 * @return list<array{source: int, dest: int, source_name: string, dest_name: string}>
	 */
	public static function pushesFromRows(array $rows, array $pricelist, array $ratio): array
	{
		$pushes = array();
		foreach ($rows as $row) {
			$sellerId = (int) $row['seller_id'];
			$buyerId = (int) $row['buyer_id'];
			$push = MarketAbuseRules::tradePush(
				$sellerId,
				$buyerId,
				(float) $row['seller_points'],
				(float) $row['buyer_points'],
				self::goodsValue($row, $pricelist, $ratio),
				MarketAbuseRules::priceValue((int) $row['ex_resource_type'], (float) $row['ex_resource_amount'], $ratio)
			);
			if ($push === null) {
				continue;
			}
			$names = array(
				$sellerId => (string) ($row['seller_name'] ?? ''),
				$buyerId => (string) ($row['buyer_name'] ?? ''),
			);
			$pushes[] = array(
				'source' => $push['source'],
				'dest' => $push['dest'],
				'source_name' => $names[$push['source']],
				'dest_name' => $names[$push['dest']],
			);
		}
		return $pushes;
	}

	/**
	 * @param array<string, mixed> $row seller log-fleet columns + transaction_type
	 * @param array<int, array<string, mixed>> $pricelist
	 */
	public static function goodsValue(array $row, array $pricelist, array $ratio): float
	{
		return MarketAbuseRules::goodsValue(
			(int) ($row['transaction_type'] ?? 0),
			array(
				901 => (float) ($row['fleet_resource_metal'] ?? 0),
				902 => (float) ($row['fleet_resource_crystal'] ?? 0),
				903 => (float) ($row['fleet_resource_deuterium'] ?? 0),
			),
			FleetFunctions::unserialize((string) ($row['fleet_array'] ?? '')),
			$pricelist,
			$ratio
		);
	}
}
