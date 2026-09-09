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
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int, int> $ships
	 * @param array{galaxy:int,system:int,planet:int,type?:int} $target
	 * @return array<string, mixed>
	 */
	public static function send(array &$user, array &$planet, array $ships, array $target, int $mission, int $speed, array $cargo): array
	{
		global $resource, $LNG;

		$fleetArray = [];
		foreach ($ships as $id => $count) {
			$id = (int) $id;
			$count = (int) $count;
			if ($count <= 0 || $id === SHIP_SOLAR_SATELLITE) {
				continue;
			}
			$have = (int) ($planet[$resource[$id]] ?? 0);
			$fleetArray[$id] = min($count, $have);
		}
		$fleetArray = array_filter($fleetArray);
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
			'fleetStorage' => $storage,
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
		);

		if ($mission === FLEET_MISSION_COLONISE || $mission === FLEET_MISSION_EXPEDITION) {
			$targetPlanetData = ['id' => 0, 'id_owner' => 0, 'planettype' => 1];
			$targetPlayerData = [];
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
				'stayTime' => 0,
				'availableMissions' => $available,
			],
			Config::get()
		);

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
		$end = $start + $duration;
		$forMission = $targetPlanetData;
		if ($mission === FLEET_MISSION_COLONISE || $mission === FLEET_MISSION_EXPEDITION) {
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
			'fleetStayTime' => $start,
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
	 */
	public static function recall(array $user, int $fleetId): bool
	{
		if ($fleetId <= 0) {
			return false;
		}
		FleetFunctions::SendFleetBack($user, $fleetId);

		return true;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @return array<string, mixed>
	 */
	public static function spy(array &$user, array &$planet, int $targetPlanetId): array
	{
		global $resource, $LNG;

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
				planet.planet as planet, planet.planet_type as planet_type
			FROM %%PLANETS%% planet WHERE planet.id = :planetID;',
			[':planetID' => $targetPlanetId]
		);
		if (!is_array($targetData)) {
			throw new \RuntimeException($LNG['fa_planet_not_exist'] ?? 'Unknown planet');
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
