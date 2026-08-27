<?php

namespace HiveNova\Core;

class FleetMissionAvailability
{
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
			if(isModuleAvailable(MODULE_MISSION_TRANSPORT)) {
				$MissionInfo['planet'];
				$availableMissions[]	= FLEET_MISSION_TRANSPORT;
			}

			if (!$YourPlanet && self::OnlyShipByID($MissionInfo['Ship'], SHIP_ESPIONAGE_PROBE) && isModuleAvailable(MODULE_MISSION_SPY))
				$availableMissions[]	= FLEET_MISSION_SPY;

			if (!$YourPlanet) {
				if(isModuleAvailable(MODULE_MISSION_TRANSFER)) {
					$availableMissions[]	= FLEET_MISSION_TRANSFER;
				}

				if(isModuleAvailable(MODULE_MISSION_ATTACK))
					$availableMissions[]	= FLEET_MISSION_ATTACK;
				if(isModuleAvailable(MODULE_MISSION_HOLD))
					$availableMissions[]	= FLEET_MISSION_ALLY_STATION;}

			elseif(isModuleAvailable(MODULE_MISSION_STATION)) {
				$availableMissions[]	= FLEET_MISSION_STATION;}

			if (!empty($MissionInfo['IsAKS']) && !$YourPlanet && isModuleAvailable(MODULE_MISSION_ATTACK) && isModuleAvailable(MODULE_MISSION_ACS))
				$availableMissions[]	= FLEET_MISSION_ACS;

			if (!$YourPlanet && $MissionInfo['planettype'] == 3 && isset($MissionInfo['Ship'][SHIP_DEATHSTAR]) && isModuleAvailable(MODULE_MISSION_DESTROY))
				$availableMissions[]	= FLEET_MISSION_DESTROY;

			if ($YourPlanet && $MissionInfo['planettype'] == 3 && self::OnlyShipByID($MissionInfo['Ship'], SHIP_DARK_MATTER) && isModuleAvailable(MODULE_MISSION_DARKMATTER))
				$availableMissions[]	= FLEET_MISSION_DARKMATTER;
		}
	}

	return $availableMissions;
	}
}
