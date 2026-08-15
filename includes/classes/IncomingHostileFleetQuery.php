<?php

namespace HiveNova\Core;

class IncomingHostileFleetQuery
{
	/** Attack, ACS, spy, destroy moon, missile — same set as hostile web push. */
	public const HOSTILE_MISSIONS = [1, 2, 6, 9, 10];

	public static function countForUser(int $userId): int
	{
		if ($userId <= 0) {
			return 0;
		}

		$sql = 'SELECT COUNT(*) as incoming
			FROM %%FLEETS%%
			WHERE fleet_target_owner = :targetUserId
			AND fleet_owner != :ownerId
			AND fleet_mess = :outward
			AND fleet_mission IN (1, 2, 6, 9, 10);';

		$row = Database::get()->selectSingle($sql, [
			':targetUserId' => $userId,
			':ownerId'      => $userId,
			':outward'      => FLEET_OUTWARD,
		]);

		return (int) ($row['incoming'] ?? 0);
	}
}
