<?php

namespace HiveNova\Core;

use HiveNova\Repository\UserRepository;

class FleetPlayService
{
	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<string, string> $lng
	 * @return array<string, mixed>
	 */
	public static function table(array $user, array $planet, mixed $lng): array
	{
		global $resource, $reslist;

		$ships = [];
		foreach ($reslist['fleet'] ?? [] as $fleetId) {
			$count = (int) ($planet[$resource[$fleetId]] ?? 0);
			if ($count <= 0) {
				continue;
			}
			$ships[] = [
				'id' => (int) $fleetId,
				'name' => EconomyPlayService::techName($lng, (int) $fleetId),
				'count' => $count,
				'speed' => (int) FleetFunctions::GetFleetMaxSpeed($fleetId, $user),
			];
		}

		$db = Database::get();
		$rows = $db->select(
			'SELECT * FROM %%FLEETS%% WHERE fleet_owner = :userID AND fleet_mission <> :mip ORDER BY fleet_end_time ASC;',
			[':userID' => (int) $user['id'], ':mip' => FLEET_MISSION_MIP]
		);
		$fleets = [];
		foreach ($rows as $row) {
			$fleets[] = self::describeFlight($row, $lng, TIMESTAMP);
		}

		return [
			'slots' => [
				'used' => count($fleets),
				'max' => (int) FleetFunctions::GetMaxFleetSlots($user),
			],
			'ships' => $ships,
			'fleets' => $fleets,
			'missions' => self::missionChoices($lng),
			'deuterium' => (int) floor((float) ($planet[$resource[RESOURCE_DEUTERIUM] ?? 'deuterium'] ?? 0)),
		];
	}

	/**
	 * @param array<string, mixed> $planet
	 * @param array<int|string, mixed> $ships
	 * @return array<int, int>
	 */
	public static function pickShips(array $planet, array $ships): array
	{
		global $resource, $reslist;

		$allowed = array_flip($reslist['fleet'] ?? []);
		$fleetArray = [];
		foreach ($ships as $id => $count) {
			$id = (int) $id;
			$count = (int) $count;
			if ($count <= 0 || $id === SHIP_SOLAR_SATELLITE || !isset($allowed[$id])) {
				continue;
			}
			$col = $resource[$id] ?? null;
			$have = $col !== null ? (int) ($planet[$col] ?? 0) : 0;
			$fleetArray[$id] = min($count, $have);
		}

		return array_filter($fleetArray);
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int, int> $ships
	 * @param array{galaxy:int,system:int,planet:int,type?:int} $target
	 * @param array<int, int> $cargo
	 * @return array<string, mixed>
	 */
	public static function preview(array $user, array $planet, array $ships, array $target, int $speed, array $cargo): array
	{
		global $resource;

		$fleetArray = self::pickShips($planet, $ships);
		$deutCol = $resource[RESOURCE_DEUTERIUM] ?? 'deuterium';
		$deuterium = (int) floor((float) ($planet[$deutCol] ?? 0));
		$cargoSum = (int) ($cargo[901] ?? 0) + (int) ($cargo[902] ?? 0) + (int) ($cargo[903] ?? 0);
		if ($fleetArray === []) {
			return [
				'distance' => 0,
				'duration' => 0,
				'consumption' => 0,
				'storage' => 0,
				'cargo' => $cargoSum,
				'deuterium' => $deuterium,
				'enoughFuel' => false,
				'enoughCargo' => true,
				'ready' => false,
			];
		}

		$speed = min(max($speed, 1), 10);
		$distance = FleetFunctions::GetTargetDistance(
			[(int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']],
			[(int) $target['galaxy'], (int) $target['system'], (int) $target['planet']]
		);
		$metrics = FleetDispatchService::calculateMetrics($fleetArray, (int) $distance, $speed, $user);
		$consumption = (float) $metrics['consumption'];
		$storage = (int) FleetFunctions::GetFleetRoom($fleetArray);

		return [
			'distance' => (int) $distance,
			'duration' => (int) $metrics['duration'],
			'consumption' => (int) $consumption,
			'storage' => $storage,
			'cargo' => $cargoSum,
			'deuterium' => $deuterium,
			'enoughFuel' => $deuterium >= $consumption,
			'enoughCargo' => $storage >= ($cargoSum + $consumption),
			'ready' => true,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int, int> $ships
	 * @param array{galaxy:int,system:int,planet:int,type?:int} $target
	 * @return array<string, mixed>
	 */
	public static function send(array &$user, array &$planet, array $ships, array $target, int $mission, int $speed, array $cargo, int $stayTime = 0): array
	{
		global $resource, $LNG;

		$fleetArray = self::pickShips($planet, $ships);
		if ($fleetArray === []) {
			throw new \RuntimeException($LNG['fl_unselect_all_ships'] ?? 'No ships');
		}

		$targetGalaxy = (int) $target['galaxy'];
		$targetSystem = (int) $target['system'];
		$targetPlanet = (int) $target['planet'];
		$targetType = (int) ($target['type'] ?? 1);
		$speed = min(max($speed, 1), 10);

		$distance = FleetFunctions::GetTargetDistance(
			[(int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']],
			[$targetGalaxy, $targetSystem, $targetPlanet]
		);
		$metrics = FleetDispatchService::calculateMetrics($fleetArray, (int) $distance, $speed, $user);
		$duration = (int) $metrics['duration'];
		$consumption = (float) $metrics['consumption'];
		$storage = FleetFunctions::GetFleetRoom($fleetArray);
		$fleetStorageAfterFuel = (int) floor($storage - $consumption);

		$params = [
			'targetMission' => $mission,
			'targetGalaxy' => $targetGalaxy,
			'targetSystem' => $targetSystem,
			'targetPlanet' => $targetPlanet,
			'targetType' => $targetType,
			'TransportMetal' => (int) ($cargo[901] ?? 0),
			'TransportCrystal' => (int) ($cargo[902] ?? 0),
			'TransportDeuterium' => (int) ($cargo[903] ?? 0),
			'WantedResourceAmount' => 0,
			'markettype' => 0,
			'fleetArray' => $fleetArray,
			'fleetStorage' => $fleetStorageAfterFuel,
			'fleetSpeed' => $speed,
			'distance' => $distance,
			'consumption' => $consumption,
		];
		FleetDispatchService::validateTarget($params, $user, $planet);
		FleetDispatchService::validateSlots($user, 0);

		$db = Database::get();
		$targetPlanetData = $db->selectSingle(
			'SELECT * FROM %%PLANETS%% WHERE universe = :uni AND galaxy = :g AND system = :s AND planet = :p AND planet_type = :t;',
			[
				':uni' => (int) $user['universe'],
				':g' => $targetGalaxy,
				':s' => $targetSystem,
				':p' => $targetPlanet,
				':t' => $targetType === 3 ? 3 : 1,
			]
		) ?: null;

		// Match ShowFleetStep3Page: keep the real (possibly empty) planet for validation;
		// only swap in a synthetic row for dispatch on colonize/expedition/market/salvage.
		if ($mission === FLEET_MISSION_COLONISE || $mission === FLEET_MISSION_EXPEDITION
			|| $mission === FLEET_MISSION_TRADE || $mission === FLEET_MISSION_SALVAGE) {
			$targetPlayerData = [
				'id' => 0,
				'onlinetime' => TIMESTAMP,
				'ally_id' => 0,
				'urlaubs_modus' => 0,
				'authattack' => 0,
				'total_points' => 0,
			];
		} elseif (is_array($targetPlanetData) && (int) ($targetPlanetData['id_owner'] ?? 0) === (int) $user['id']) {
			$targetPlayerData = $user;
		} elseif (is_array($targetPlanetData) && !empty($targetPlanetData['id_owner'])) {
			$targetPlayerData = UserRepository::getUserWithStats((int) $targetPlanetData['id_owner']);
		} else {
			$targetPlanetData = is_array($targetPlanetData) ? $targetPlanetData : [];
			$targetPlayerData = [];
		}

		$misInfo = [
			'galaxy' => $targetGalaxy,
			'system' => $targetSystem,
			'planet' => $targetPlanet,
			'planettype' => $targetType,
			'IsAKS' => 0,
			'Ship' => $fleetArray,
			'startPlanetId' => (int) $planet['id'],
		];
		$available = FleetFunctions::GetFleetMissions($user, $misInfo, $targetPlanetData);
		FleetDispatchService::validateMission(
			$targetPlanetData,
			$targetPlayerData,
			$mission,
			$user,
			[
				'fleetArray' => $fleetArray,
				'fleetGroup' => 0,
				'targetType' => $targetType,
				'stayTime' => $stayTime,
				'availableMissions' => $available,
			],
			Config::get()
		);

		$stayDuration = 0;
		if ($mission === FLEET_MISSION_ALLY_STATION || $mission === FLEET_MISSION_DARKMATTER
			|| $mission === FLEET_MISSION_EXPEDITION || $mission === FLEET_MISSION_TRADE) {
			$stayDuration = (int) round(($available['StayBlock'][$stayTime] ?? 0) * 3600, 0);
		}

		$eco = new ResourceUpdate();
		$eco->setResourceData($GLOBALS['resource'], $GLOBALS['reslist']);
		$eco->setData($user, $planet);
		[$user, $planet] = $eco->SavePlanetToDB($user, $planet);

		$fleetResource = [
			901 => min((int) ($cargo[901] ?? 0), (int) floor((float) $planet[$resource[901]])),
			902 => min((int) ($cargo[902] ?? 0), (int) floor((float) $planet[$resource[902]])),
			903 => min((int) ($cargo[903] ?? 0), (int) floor((float) $planet[$resource[903]] - $consumption)),
		];
		$start = $duration + TIMESTAMP;
		$fleetStayTime = $start + $stayDuration;
		$end = $fleetStayTime + $duration;
		$forMission = $targetPlanetData;
		if ($mission === FLEET_MISSION_COLONISE || $mission === FLEET_MISSION_EXPEDITION
			|| $mission === FLEET_MISSION_TRADE || $mission === FLEET_MISSION_SALVAGE) {
			$forMission = ['id' => 0, 'id_owner' => 0, 'planettype' => 1];
		}

		$fleetId = FleetDispatchService::dispatch([
			'fleetArray' => $fleetArray,
			'targetMission' => $mission,
			'USER' => $user,
			'targetPlanetData' => $forMission,
			'targetGalaxy' => $targetGalaxy,
			'targetSystem' => $targetSystem,
			'targetPlanet' => $targetPlanet,
			'targetType' => $targetType,
			'fleetResource' => $fleetResource,
			'fleetStartTime' => $start,
			'fleetStayTime' => $fleetStayTime,
			'fleetEndTime' => $end,
			'fleetGroup' => 0,
			'consumption' => $consumption,
			'markettype' => 0,
			'WantedResourceType' => 0,
			'WantedResourceAmount' => 0,
			'maxFlightTime' => 0,
			'visibility' => 0,
			'fleetMeta' => null,
		], $planet);

		return [
			'fleetId' => $fleetId,
			'arrival' => $start,
			'return' => $end,
			'consumption' => (int) $consumption,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{recalled: bool}
	 */
	public static function recall(array $user, int $fleetId): array
	{
		if ($fleetId <= 0) {
			return ['recalled' => false];
		}

		return ['recalled' => (bool) FleetFunctions::SendFleetBack($user, $fleetId)];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @return array<string, mixed>
	 */
	public static function spy(array &$user, array &$planet, int $targetPlanetId): array
	{
		global $resource, $LNG;

		if (!isModuleAvailable(MODULE_MISSION_SPY)) {
			throw new \RuntimeException($LNG['sys_module_inactive'] ?? 'Inactive module');
		}
		if (IsVacationMode($user)) {
			throw new \RuntimeException($LNG['fa_vacation_mode_current'] ?? 'Vacation mode');
		}
		if ($targetPlanetId <= 0) {
			throw new \RuntimeException($LNG['fa_planet_not_exist'] ?? 'Unknown planet');
		}

		$ships = min((int) ($user['spio_anz'] ?? 1), (int) ($planet[$resource[SHIP_ESPIONAGE_PROBE]] ?? 0));
		if ($ships <= 0) {
			throw new \RuntimeException($LNG['fa_no_spios'] ?? 'No probes');
		}
		if (FleetFunctions::GetCurrentFleets($user['id']) >= FleetFunctions::GetMaxFleetSlots($user)) {
			throw new \RuntimeException($LNG['fa_no_more_slots'] ?? 'No slots');
		}

		$db = Database::get();
		$targetData = $db->selectSingle(
			'SELECT planet.id as id, planet.id_owner as id_owner, planet.galaxy as galaxy, planet.system as system,
				planet.planet as planet, planet.planet_type as planet_type,
				total_points, onlinetime, urlaubs_modus, banaday, authattack
			FROM %%PLANETS%% planet
			INNER JOIN %%USERS%% user ON planet.id_owner = user.id
			LEFT JOIN %%STATPOINTS%% as stat ON stat.id_owner = user.id AND stat.stat_type = \'1\'
			WHERE planet.id = :planetID;',
			[':planetID' => $targetPlanetId]
		);
		if (!is_array($targetData)) {
			throw new \RuntimeException($LNG['fa_planet_not_exist'] ?? 'Unknown planet');
		}
		if (Config::get()->adm_attack == 1 && (int) ($targetData['authattack'] ?? 0) > (int) ($user['authlevel'] ?? 0)) {
			throw new \RuntimeException($LNG['fa_action_not_allowed'] ?? 'Action not allowed');
		}
		if (IsVacationMode($targetData)) {
			throw new \RuntimeException($LNG['fa_vacation_mode'] ?? 'Target vacation');
		}
		mergeUserStatPoints($user);
		$noob = CheckNoobProtec($user, $targetData, $targetData);
		if (!empty($noob['NoobPlayer'])) {
			throw new \RuntimeException($LNG['fa_week_player'] ?? 'Target too weak');
		}
		if (!empty($noob['StrongPlayer'])) {
			throw new \RuntimeException($LNG['fa_strong_player'] ?? 'Target too strong');
		}
		if ((int) $targetData['id_owner'] === (int) $user['id']) {
			throw new \RuntimeException($LNG['fa_not_spy_yourself'] ?? 'Cannot spy yourself');
		}

		$fleetArray = [SHIP_ESPIONAGE_PROBE => $ships];
		$speedFactor = FleetFunctions::GetGameSpeedFactor();
		$distance = FleetFunctions::GetTargetDistance(
			[(int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']],
			[(int) $targetData['galaxy'], (int) $targetData['system'], (int) $targetData['planet']]
		);
		$speedMin = FleetFunctions::GetFleetMaxSpeed($fleetArray, $user);
		$duration = FleetFunctions::GetMissionDuration(10, $speedMin, $distance, $speedFactor, $user);
		$consumption = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $user, $speedFactor);
		if ($planet['deuterium'] < $consumption) {
			throw new \RuntimeException($LNG['fa_not_enough_fuel'] ?? 'Not enough fuel');
		}
		if ($consumption > FleetFunctions::GetFleetRoom($fleetArray)) {
			throw new \RuntimeException($LNG['fa_no_fleetroom'] ?? 'Not enough cargo for fuel');
		}
		$planet['deuterium'] -= $consumption;
		$start = $duration + TIMESTAMP;
		FleetFunctions::sendFleet(
			$fleetArray,
			FLEET_MISSION_SPY,
			$user['id'],
			$planet['id'],
			$planet['galaxy'],
			$planet['system'],
			$planet['planet'],
			$planet['planet_type'],
			$targetData['id_owner'],
			$targetPlanetId,
			$targetData['galaxy'],
			$targetData['system'],
			$targetData['planet'],
			$targetData['planet_type'],
			[901 => 0, 902 => 0, 903 => 0],
			$start,
			$start,
			$start + $duration,
			0,
			0,
			0,
			$consumption
		);

		return ['message' => (string) ($LNG['fa_sending'] ?? 'Sent')];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @return array<string, mixed>
	 */
	public static function recycle(array &$user, array &$planet, int $targetPlanetId): array
	{
		global $resource, $LNG, $pricelist;

		if (!isModuleAvailable(MODULE_MISSION_RECYCLE)) {
			throw new \RuntimeException($LNG['sys_module_inactive'] ?? 'Inactive module');
		}
		if ($targetPlanetId <= 0) {
			throw new \RuntimeException($LNG['fa_planet_not_exist'] ?? 'Unknown planet');
		}
		if (FleetFunctions::GetCurrentFleets($user['id']) >= FleetFunctions::GetMaxFleetSlots($user)) {
			throw new \RuntimeException($LNG['fa_no_more_slots'] ?? 'No slots');
		}

		$db = Database::get();
		$targetData = $db->selectSingle(
			'SELECT planet.id as id, planet.id_owner as id_owner, planet.galaxy as galaxy, planet.system as system,
				planet.planet as planet, planet.planet_type as planet_type,
				planet.der_metal as der_metal, planet.der_crystal as der_crystal
			FROM %%PLANETS%% planet WHERE planet.id = :planetID;',
			[':planetID' => $targetPlanetId]
		);
		if (!is_array($targetData)) {
			throw new \RuntimeException($LNG['fa_planet_not_exist'] ?? 'Unknown planet');
		}

		$totalDebris = (int) ($targetData['der_metal'] ?? 0) + (int) ($targetData['der_crystal'] ?? 0);
		$storageFactor = 1 + (float) ($user['factor']['ShipStorage'] ?? 0);
		$fleetArray = [];
		foreach ([SHIP_BATTLE_RECYCLER, SHIP_RECYCLER] as $elementId) {
			$col = $resource[$elementId] ?? null;
			$have = $col !== null ? (int) ($planet[$col] ?? 0) : 0;
			if ($have <= 0 || $totalDebris <= 0) {
				continue;
			}
			$capacity = (float) ($pricelist[$elementId]['capacity'] ?? 0) * $storageFactor;
			if ($capacity <= 0) {
				continue;
			}
			$need = (int) min((int) ceil($totalDebris / $capacity), $have);
			if ($need <= 0) {
				continue;
			}
			$fleetArray[$elementId] = $need;
			$totalDebris -= (int) ($need * $capacity);
		}
		if ($fleetArray === []) {
			throw new \RuntimeException($LNG['fa_no_recyclers'] ?? 'No recyclers');
		}

		$speedFactor = FleetFunctions::GetGameSpeedFactor();
		$distance = FleetFunctions::GetTargetDistance(
			[(int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']],
			[(int) $targetData['galaxy'], (int) $targetData['system'], (int) $targetData['planet']]
		);
		$speedMin = FleetFunctions::GetFleetMaxSpeed($fleetArray, $user);
		$duration = FleetFunctions::GetMissionDuration(10, $speedMin, $distance, $speedFactor, $user);
		$consumption = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $user, $speedFactor);
		if ($planet['deuterium'] < $consumption) {
			throw new \RuntimeException($LNG['fa_not_enough_fuel'] ?? 'Not enough fuel');
		}
		if ($consumption > FleetFunctions::GetFleetRoom($fleetArray)) {
			throw new \RuntimeException($LNG['fa_no_fleetroom'] ?? 'Not enough cargo for fuel');
		}
		$planet['deuterium'] -= $consumption;
		$start = $duration + TIMESTAMP;
		FleetFunctions::sendFleet(
			$fleetArray,
			FLEET_MISSION_RECYCLE,
			$user['id'],
			$planet['id'],
			$planet['galaxy'],
			$planet['system'],
			$planet['planet'],
			$planet['planet_type'],
			(int) ($targetData['id_owner'] ?? 0),
			$targetPlanetId,
			$targetData['galaxy'],
			$targetData['system'],
			$targetData['planet'],
			$targetData['planet_type'],
			[901 => 0, 902 => 0, 903 => 0],
			$start,
			$start,
			$start + $duration,
			0,
			0,
			0,
			$consumption
		);

		return ['message' => (string) ($LNG['fa_sending'] ?? 'Sent'), 'ships' => $fleetArray];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function describeFlight(array $row, mixed $lng, int $now): array
	{
		$mission = (int) $row['fleet_mission'];
		$outward = (int) $row['fleet_mess'] === FLEET_OUTWARD;
		$returnTime = ($mission === FLEET_MISSION_STATION && $outward)
			? (int) $row['fleet_start_time']
			: (int) $row['fleet_end_time'];

		return [
			'id' => (int) $row['fleet_id'],
			'mission' => $mission,
			'missionName' => self::lngKey($lng, 'type_mission_'.$mission, 'Mission '.$mission),
			'state' => (int) $row['fleet_mess'],
			'heading' => $outward ? 'outbound' : 'return',
			'start' => [
				'galaxy' => (int) $row['fleet_start_galaxy'],
				'system' => (int) $row['fleet_start_system'],
				'planet' => (int) $row['fleet_start_planet'],
			],
			'end' => [
				'galaxy' => (int) $row['fleet_end_galaxy'],
				'system' => (int) $row['fleet_end_system'],
				'planet' => (int) $row['fleet_end_planet'],
			],
			'ships' => FleetFunctions::unserialize($row['fleet_array'] ?? ''),
			'resources' => [
				901 => (int) ($row['fleet_resource_metal'] ?? 0),
				902 => (int) ($row['fleet_resource_crystal'] ?? 0),
				903 => (int) ($row['fleet_resource_deuterium'] ?? 0),
			],
			'arrival' => (int) $row['fleet_start_time'],
			'return' => $returnTime,
			'restSeconds' => $returnTime - $now,
			'recallable' => $outward && (int) ($row['fleet_no_m_return'] ?? 0) === 0,
		];
	}

	/**
	 * @return list<array{id: int, name: string}>
	 */
	public static function missionChoices(mixed $lng): array
	{
		$choices = [];
		foreach ([
			FLEET_MISSION_ATTACK,
			FLEET_MISSION_TRANSPORT,
			FLEET_MISSION_STATION,
			FLEET_MISSION_SPY,
			FLEET_MISSION_COLONISE,
			FLEET_MISSION_RECYCLE,
			FLEET_MISSION_DESTROY,
			FLEET_MISSION_EXPEDITION,
		] as $missionId) {
			$choices[] = [
				'id' => $missionId,
				'name' => self::lngKey($lng, 'type_mission_'.$missionId, 'Mission '.$missionId),
			];
		}

		return $choices;
	}

	public static function lngKey(mixed $lng, string $key, string $fallback): string
	{
		if (is_array($lng) || $lng instanceof \ArrayAccess) {
			$value = $lng[$key] ?? null;
			if (is_string($value) && $value !== '') {
				return $value;
			}
		}

		return $fallback;
	}
}
