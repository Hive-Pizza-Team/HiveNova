<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;
use HiveNova\Core\FleetFunctions;

class PveRaidService
{
	public static function run(int $universe, ?int $now = null): int
	{
		if (!isModuleAvailable(MODULE_MISSION_SALVAGE)) {
			return 0;
		}

		$now = $now ?? TIMESTAMP;
		$spawned = 0;
		$spawned += self::raidCombatOutPlayers($universe, $now);
		$spawned += self::raidAccusedWithoutCombat($universe, $now);

		return $spawned;
	}

	private static function raidCombatOutPlayers(int $universe, int $now): int
	{
		$rows = Database::get()->select(
			'SELECT DISTINCT fleet_owner, fleet_start_id, fleet_start_galaxy, fleet_start_system,
				fleet_start_planet, fleet_start_type, fleet_amount
			FROM %%FLEETS%%
			WHERE fleet_universe = :universe
			  AND fleet_mission IN (1, 2, 9)
			  AND fleet_mess = :outward;',
			[
				':universe' => $universe,
				':outward'  => FLEET_OUTWARD,
			]
		);

		$count = 0;
		foreach ($rows as $row) {
			if (self::trySpawnRaid($universe, (int) $row['fleet_owner'], $row, $now, false)) {
				$count++;
			}
		}

		return $count;
	}

	private static function raidAccusedWithoutCombat(int $universe, int $now): int
	{
		$count = 0;
		foreach (PushingAccusationQuery::accusedReceiverIds($universe, $now) as $userId) {
			if (mt_rand(1, 100) > PVE_ACCUSED_RAID_CHANCE) {
				continue;
			}
			$planet = Database::get()->selectSingle(
				'SELECT id, galaxy, `system`, planet, planet_type, id_owner FROM %%PLANETS%%
				WHERE id_owner = :userId AND planet_type = 1 AND destruyed = 0
				ORDER BY id ASC LIMIT 1;',
				[':userId' => $userId]
			);
			if (empty($planet['id'])) {
				continue;
			}
			$fake = [
				'fleet_owner' => $userId,
				'fleet_start_id' => $planet['id'],
				'fleet_start_galaxy' => $planet['galaxy'],
				'fleet_start_system' => $planet['system'],
				'fleet_start_planet' => $planet['planet'],
				'fleet_start_type' => $planet['planet_type'],
				'fleet_amount' => 50,
			];
			if (self::trySpawnRaid($universe, $userId, $fake, $now, true)) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @param array<string, mixed> $outward
	 */
	public static function trySpawnRaid(int $universe, int $userId, array $outward, int $now, bool $accusedIdlePath): bool
	{
		$user = Database::get()->selectSingle(
			'SELECT id, urlaubs_modus FROM %%USERS%% WHERE id = :id;',
			[':id' => $userId]
		);
		if (empty($user) || !empty($user['urlaubs_modus'])) {
			return false;
		}

		$planetId = (int) $outward['fleet_start_id'];
		if (self::hasInboundNpc($planetId)) {
			return false;
		}

		$planet = Database::get()->selectSingle(
			'SELECT * FROM %%PLANETS%% WHERE id = :id;',
			[':id' => $planetId]
		);
		if (empty($planet['id'])) {
			return false;
		}

		$hangar = self::hangarPower($planet);
		$outbound = max(1, (int) $outward['fleet_amount']);
		if (!$accusedIdlePath && $hangar >= $outbound * PVE_HANGAR_WEAK_FRACTION) {
			return false;
		}
		if ($accusedIdlePath && $hangar >= $outbound * PVE_HANGAR_WEAK_FRACTION) {
			return false;
		}

		$accused = PushingAccusationQuery::isAccusedReceiver($userId, $universe, $now);
		$family = mt_rand(0, 1) ? 'pirate' : 'alien';
		$tier = $accused ? 2 : 1;
		$ships = PveNpcFleetFactory::template($family, $tier, $accused);
		$flight = PVE_RAID_FLIGHT_SECONDS;
		$startTime = $now + $flight;
		$endTime = $startTime + $flight;

		FleetFunctions::sendFleet(
			$ships,
			1,
			0,
			0,
			(int) $planet['galaxy'],
			(int) $planet['system'],
			(int) $planet['planet'],
			(int) $planet['planet_type'],
			$userId,
			$planetId,
			(int) $planet['galaxy'],
			(int) $planet['system'],
			(int) $planet['planet'],
			(int) $planet['planet_type'],
			[901 => 0, 902 => 0, 903 => 0],
			$startTime,
			$startTime,
			$endTime,
			0,
			0,
			1,
			0,
			$universe
		);

		return true;
	}

	private static function hasInboundNpc(int $planetId): bool
	{
		$row = Database::get()->selectSingle(
			'SELECT COUNT(*) AS total FROM %%FLEETS%%
			WHERE fleet_end_id = :planetId AND fleet_owner = 0 AND fleet_mission = 1 AND fleet_mess = :outward;',
			[
				':planetId' => $planetId,
				':outward'  => FLEET_OUTWARD,
			]
		);

		return (int) ($row['total'] ?? 0) > 0;
	}

	/**
	 * @param array<string, mixed> $planet
	 */
	public static function hangarPower(array $planet): int
	{
		global $resource, $reslist, $pricelist;

		$power = 0;
		$ids = array_merge($reslist['fleet'] ?? [], $reslist['defense'] ?? []);
		foreach ($ids as $elementId) {
			$key = $resource[$elementId] ?? null;
			if ($key === null || empty($planet[$key])) {
				continue;
			}
			$attack = (int) ($pricelist[$elementId]['attack'] ?? 1);
			$power += $attack * (int) $planet[$key];
		}

		return $power;
	}
}
