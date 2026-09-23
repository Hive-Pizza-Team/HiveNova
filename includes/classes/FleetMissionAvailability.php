<?php

namespace HiveNova\Core;

class FleetMissionAvailability
{
	/**
	 * Deathstar and Black Moon can run Destroy (mission 9).
	 *
	 * @param array<int, mixed> $ships
	 */
	public static function hasMoonDestroyer(array $ships): bool
	{
		return isset($ships[SHIP_DEATHSTAR]) || isset($ships[SHIP_BLACK_MOON]);
	}

	/**
	 * @param array<string, mixed> $planet
	 * @param array<int, string> $resource
	 */
	public static function planetHasMoonDestroyer(array $planet, array $resource): bool
	{
		foreach ([SHIP_DEATHSTAR, SHIP_BLACK_MOON] as $shipId) {
			$column = $resource[$shipId] ?? null;
			if ($column !== null && ($planet[$column] ?? 0) > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int, mixed> $units
	 */
	public static function moonDestroyerCount(array $units): float
	{
		return (float) ($units[SHIP_DEATHSTAR] ?? 0) + (float) ($units[SHIP_BLACK_MOON] ?? 0);
	}

	/**
	 * @param array<string, mixed> $USER
	 * @param array<string, mixed> $MissionInfo
	 * @param array<string, mixed>|false $GetInfoPlanet
	 * @return list<int>
	 */
	public static function forTarget($USER, $MissionInfo, $GetInfoPlanet): array
	{
	$GetInfoPlanet			= is_array($GetInfoPlanet) ? $GetInfoPlanet : array();
	$YourPlanet				= (!empty($GetInfoPlanet['id_owner']) && $GetInfoPlanet['id_owner'] == $USER['id']) ? true : false;
	$UsedPlanet				= (!empty($GetInfoPlanet['id_owner'])) ? true : false;
	$availableMissions		= array();
	$sameBody				= self::isSameBody($USER, $MissionInfo, $GetInfoPlanet);

	if ($MissionInfo['planet'] == (Config::get($USER['universe'])->max_planets + 1) && isModuleAvailable(MODULE_MISSION_EXPEDITION))
		$availableMissions[]	= FLEET_MISSION_EXPEDITION;
	elseif ($MissionInfo['planet'] == (Config::get($USER['universe'])->max_planets + 2) && isModuleAvailable(MODULE_MISSION_TRADE))
		$availableMissions[]	= FLEET_MISSION_TRADE;
	elseif ($MissionInfo['planettype'] == 2) {
		if ((isset($MissionInfo['Ship'][SHIP_RECYCLER]) || isset($MissionInfo['Ship'][SHIP_PATHFINDER])) && isModuleAvailable(MODULE_MISSION_RECYCLE) && !($GetInfoPlanet['der_metal'] == 0 && $GetInfoPlanet['der_crystal'] == 0))
			$availableMissions[]	= FLEET_MISSION_RECYCLE;
		$package = PvePackageService::findAt(
			(int) $USER['universe'],
			(int) $MissionInfo['galaxy'],
			(int) $MissionInfo['system'],
			(int) $MissionInfo['planet']
		);
		if ($package !== null && isModuleAvailable(MODULE_MISSION_SALVAGE)) {
			$availableMissions[] = FLEET_MISSION_SALVAGE;
		}
	} else {
		$package = PvePackageService::findAt(
			(int) $USER['universe'],
			(int) ($MissionInfo['galaxy'] ?? 0),
			(int) ($MissionInfo['system'] ?? 0),
			(int) $MissionInfo['planet']
		);
		if ($package !== null && isModuleAvailable(MODULE_MISSION_SALVAGE)) {
			$availableMissions[] = FLEET_MISSION_SALVAGE;
		}

		if (!$UsedPlanet) {
			if (isset($MissionInfo['Ship'][SHIP_COLONY_SHIP]) && $MissionInfo['planettype'] == 1 && isModuleAvailable(MODULE_MISSION_COLONY))
				$availableMissions[]	= FLEET_MISSION_COLONISE;
		} else {
			if(!$sameBody && isModuleAvailable(MODULE_MISSION_TRANSPORT)) {
				$availableMissions[]	= FLEET_MISSION_TRANSPORT;
			}

			if (!$YourPlanet && FleetFunctions::OnlyShipByID($MissionInfo['Ship'], SHIP_ESPIONAGE_PROBE) && isModuleAvailable(MODULE_MISSION_SPY))
				$availableMissions[]	= FLEET_MISSION_SPY;

			if (!$YourPlanet) {
				if(isModuleAvailable(MODULE_MISSION_TRANSFER)) {
					$availableMissions[]	= FLEET_MISSION_TRANSFER;
				}

				if(isModuleAvailable(MODULE_MISSION_ATTACK))
					$availableMissions[]	= FLEET_MISSION_ATTACK;
				if(isModuleAvailable(MODULE_MISSION_HOLD))
					$availableMissions[]	= FLEET_MISSION_ALLY_STATION;}

			elseif(!$sameBody && isModuleAvailable(MODULE_MISSION_STATION)) {
				$availableMissions[]	= FLEET_MISSION_STATION;}

			if (!empty($MissionInfo['IsAKS']) && !$YourPlanet && isModuleAvailable(MODULE_MISSION_ATTACK) && isModuleAvailable(MODULE_MISSION_ACS))
				$availableMissions[]	= FLEET_MISSION_ACS;

			if (!$YourPlanet && $MissionInfo['planettype'] == 3 && self::hasMoonDestroyer($MissionInfo['Ship'] ?? []) && isModuleAvailable(MODULE_MISSION_DESTROY))
				$availableMissions[]	= FLEET_MISSION_DESTROY;

			if (!$sameBody && $YourPlanet && $MissionInfo['planettype'] == 3 && FleetFunctions::OnlyShipByID($MissionInfo['Ship'], SHIP_DARK_MATTER) && isModuleAvailable(MODULE_MISSION_DARKMATTER))
				$availableMissions[]	= FLEET_MISSION_DARKMATTER;
		}
	}

	return $availableMissions;
	}

	/**
	 * @param array<string, mixed> $USER
	 * @param array<string, mixed> $MissionInfo
	 * @param array<string, mixed> $GetInfoPlanet
	 */
	private static function isSameBody(array $USER, array $MissionInfo, array $GetInfoPlanet): bool
	{
		$startPlanetId = (int) ($MissionInfo['startPlanetId'] ?? $USER['id_planet'] ?? 0);
		$destPlanetId  = (int) ($GetInfoPlanet['id'] ?? 0);

		return $startPlanetId > 0 && $destPlanetId > 0 && $startPlanetId === $destPlanetId;
	}
}
