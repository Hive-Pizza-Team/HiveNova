<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;

class PushingAccusationQuery
{
	/**
	 * User IDs that GM mail names as push destinations (stronger receivers).
	 *
	 * @return list<int>
	 */
	public static function accusedReceiverIds(int $universe, ?int $now = null): array
	{
		$cutoff = ($now ?? TIMESTAMP) - (14 * 24 * 60 * 60);

		$sql = 'SELECT dest_id
		FROM (
			SELECT u2.id AS dest_id
			FROM %%LOG_FLEETS%% fl
			JOIN %%PLANETS%% p ON fl.fleet_end_id = p.id
			JOIN %%USERS%% u1 ON u1.id = fl.fleet_owner
			JOIN %%USERS%% u2 ON u2.id = p.id_owner
			JOIN %%STATPOINTS%% sp1 ON sp1.id_owner = u1.id AND sp1.universe = :universe
			JOIN %%STATPOINTS%% sp2 ON sp2.id_owner = u2.id AND sp2.universe = :universe
			WHERE fl.fleet_mission = 3
			  AND fl.fleet_owner != p.id_owner
			  AND sp1.total_points < sp2.total_points
			  AND fl.start_time > :cutoff
			  AND p.universe = :universe
			GROUP BY u1.id, u2.id
			HAVING COUNT(DISTINCT fl.fleet_id) > 5
		) pushers
		GROUP BY dest_id;';

		$rows = Database::get()->select($sql, [
			':universe' => $universe,
			':cutoff'   => $cutoff,
		]);

		$ids = [];
		foreach ($rows as $row) {
			$ids[] = (int) $row['dest_id'];
		}

		return $ids;
	}

	public static function isAccusedReceiver(int $userId, int $universe, ?int $now = null): bool
	{
		return in_array($userId, self::accusedReceiverIds($universe, $now), true);
	}
}
