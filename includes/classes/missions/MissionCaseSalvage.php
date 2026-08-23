<?php

namespace HiveNova\Mission;

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\MissionFunctions;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\PveNpcFleetFactory;
use HiveNova\Core\PvePackageService;
use HiveNova\Core\PushingAccusationQuery;
use HiveNova\Repository\PlanetRepository;

class MissionCaseSalvage extends MissionFunctions implements Mission
{
	function __construct($Fleet)
	{
		$this->_fleet = $Fleet;
	}

	function TargetEvent()
	{
		global $pricelist, $resource;

		$db = Database::get();
		$universe = (int) $this->_fleet['fleet_universe'];
		$galaxy = (int) $this->_fleet['fleet_end_galaxy'];
		$system = (int) $this->_fleet['fleet_end_system'];
		$planet = (int) $this->_fleet['fleet_end_planet'];

		$colonized = $db->selectSingle(
			'SELECT id FROM %%PLANETS%%
			WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND planet = :planet
			AND planet_type = 1 AND destruyed = 0;',
			[
				':universe' => $universe,
				':galaxy'   => $galaxy,
				':system'   => $system,
				':planet'   => $planet,
			]
		);
		if (is_array($colonized) && !empty($colonized['id']) && (int) $this->_fleet['fleet_end_id'] === 0) {
			$this->UpdateFleet('fleet_end_id', (int) $colonized['id']);
			PvePackageService::attachToPlanet($universe, $galaxy, $system, $planet, (int) $colonized['id']);
		}

		$package = PvePackageService::findAt($universe, $galaxy, $system, $planet);
		if ($package === null) {
			$this->bounceEmpty();
			return;
		}

		$owner = $this->getUser((int) $this->_fleet['fleet_owner']);
		$accused = PushingAccusationQuery::isAccusedReceiver((int) $this->_fleet['fleet_owner'], $universe)
			|| (!empty($package['planet_id']) && $this->planetOwnerAccused((int) $package['planet_id'], $universe));

		$chance = PVE_ENCOUNTER_CHANCE + ($accused ? PVE_ACCUSED_ENCOUNTER_BONUS : 0);
		$roll = abs((int) $package['encounter_seed']) % 100;
		if ($roll < $chance) {
			$survived = $this->fightEncounter($package, $owner, $accused);
			if (!$survived) {
				return;
			}
		}

		$fleetData = FleetFunctions::unserialize($this->_fleet['fleet_array']);
		$capacity = 0;
		foreach ($fleetData as $shipId => $shipAmount) {
			$capacity += ($pricelist[$shipId]['capacity'] ?? 0) * $shipAmount;
		}
		$factors = getFactors($owner);
		$capacity *= (1 + ($factors['ShipStorage'] ?? 0));
		$incoming = (int) $this->_fleet['fleet_resource_metal']
			+ (int) $this->_fleet['fleet_resource_crystal']
			+ (int) $this->_fleet['fleet_resource_deuterium'];
		$free = max(0, (int) $capacity - $incoming);
		$takeMetal = 0;
		$takeCrystal = 0;

		$db->beginTransaction();
		try {
			$locked = PvePackageService::lockAt($universe, $galaxy, $system, $planet);
			if ($locked === null) {
				$db->rollback();
				$this->bounceEmpty();
				return;
			}

			$loot = PvePackageService::currentLoot($locked);
			$totalLoot = $loot['metal'] + $loot['crystal'];
			$factor = $totalLoot > 0 ? min(1, $free / $totalLoot) : 0;
			$takeMetal = (int) floor($loot['metal'] * $factor);
			$takeCrystal = (int) floor($loot['crystal'] * $factor);

			if ($takeMetal > 0 || $takeCrystal > 0) {
				$collected = PvePackageService::collect(
					(int) $locked['id'],
					$takeMetal,
					$takeCrystal,
					(int) $locked['metal'],
					(int) $locked['crystal']
				);
				if (!$collected) {
					$db->rollback();
					$this->bounceEmpty();
					return;
				}
			}
			$db->commit();
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}

		$this->UpdateFleet('fleet_resource_metal', (int) $this->_fleet['fleet_resource_metal'] + $takeMetal);
		$this->UpdateFleet('fleet_resource_crystal', (int) $this->_fleet['fleet_resource_crystal'] + $takeCrystal);

		$LNG = $this->getLanguage($owner['lang'] ?? null);
		PlayerUtil::sendMessage(
			(int) $this->_fleet['fleet_owner'],
			0,
			$LNG['sys_mess_tower'] ?? 'Fleet',
			15,
			$LNG['type_mission_18'] ?? 'Salvage',
			sprintf($LNG['sys_salvage_collected'] ?? 'Collected %s metal and %s crystal.', pretty_number($takeMetal), pretty_number($takeCrystal)),
			TIMESTAMP,
			null,
			1,
			$universe
		);

		$this->setState(FLEET_RETURN);
		$this->SaveFleet();
	}

	private function bounceEmpty(): void
	{
		$owner = $this->getUser((int) $this->_fleet['fleet_owner']);
		$LNG = $this->getLanguage($owner['lang'] ?? null);
		PlayerUtil::sendMessage(
			(int) $this->_fleet['fleet_owner'],
			0,
			$LNG['sys_mess_tower'] ?? 'Fleet',
			15,
			$LNG['type_mission_18'] ?? 'Salvage',
			$LNG['fl_salvage_gone'] ?? 'The salvage package is gone.',
			TIMESTAMP,
			null,
			1,
			(int) $this->_fleet['fleet_universe']
		);
		$this->setState(FLEET_RETURN);
		$this->SaveFleet();
	}

	private function planetOwnerAccused(int $planetId, int $universe): bool
	{
		$row = Database::get()->selectSingle(
			'SELECT id_owner FROM %%PLANETS%% WHERE id = :id;',
			[':id' => $planetId]
		);
		if (!is_array($row) || empty($row['id_owner'])) {
			return false;
		}

		return PushingAccusationQuery::isAccusedReceiver((int) $row['id_owner'], $universe);
	}

	/**
	 * @param array<string, mixed> $package
	 * @param array<string, mixed> $owner
	 */
	private function fightEncounter(array $package, array $owner, bool $accused): bool
	{
		$family = PveNpcFleetFactory::familyFromSeed((int) $package['encounter_seed']);
		$npcShips = PveNpcFleetFactory::template($family, (int) $package['tier'], $accused);
		$fleetArray = FleetFunctions::unserialize($this->_fleet['fleet_array']);

		$fleetID = $this->_fleet['fleet_id'];
		$fleetAttack = [];
		$fleetAttack[$fleetID]['fleetDetail'] = $this->_fleet;
		$fleetAttack[$fleetID]['player'] = $owner;
		$fleetAttack[$fleetID]['player']['factor'] = getFactors($owner, 'attack', $this->_fleet['fleet_start_time']);
		$fleetAttack[$fleetID]['unit'] = $fleetArray;

		$npc = PveNpcFleetFactory::syntheticPlayer(PveNpcFleetFactory::displayName($family));
		$fleetDefend = [];
		$fleetDefend[0]['fleetDetail'] = [
			'fleet_start_galaxy' => $this->_fleet['fleet_end_galaxy'],
			'fleet_start_system' => $this->_fleet['fleet_end_system'],
			'fleet_start_planet' => $this->_fleet['fleet_end_planet'],
			'fleet_start_type' => 1,
			'fleet_end_galaxy' => $this->_fleet['fleet_end_galaxy'],
			'fleet_end_system' => $this->_fleet['fleet_end_system'],
			'fleet_end_planet' => $this->_fleet['fleet_end_planet'],
			'fleet_end_type' => 1,
			'fleet_resource_metal' => 0,
			'fleet_resource_crystal' => 0,
			'fleet_resource_deuterium' => 0,
		];
		$fleetDefend[0]['player'] = $npc;
		$fleetDefend[0]['player']['factor'] = $npc['factor'];
		$fleetDefend[0]['unit'] = $npcShips;

		require_once 'includes/classes/missions/functions/calculateAttack.php';
		$config = Config::get($this->_fleet['fleet_universe']);
		$combatResult = calculateAttack($fleetAttack, $fleetDefend, $config->Fleet_Cdr / 100, $config->Defs_Cdr / 100);

		$fleetString = '';
		$totalCount = 0;
		$fleetAttack[$fleetID]['unit'] = array_filter($fleetAttack[$fleetID]['unit']);
		foreach ($fleetAttack[$fleetID]['unit'] as $element => $amount) {
			$fleetString .= $element . ',' . $amount . ';';
			$totalCount += $amount;
		}

		if ($totalCount <= 0) {
			$this->KillFleet();
			return false;
		}

		$this->UpdateFleet('fleet_array', substr($fleetString, 0, -1));
		$this->UpdateFleet('fleet_amount', $totalCount);
		return true;
	}

	function EndStayEvent()
	{
		return;
	}

	function ReturnEvent()
	{
		$LNG = $this->getLanguage(null, $this->_fleet['fleet_owner']);
		$planetName = PlanetRepository::getPlanetName($this->_fleet['fleet_start_id']) ?? '';
		$Message = sprintf(
			$LNG['sys_fleet_won'] ?? '%s %s %s %s %s %s %s %s',
			$planetName,
			GetTargetAddressLink($this->_fleet, ''),
			pretty_number($this->_fleet['fleet_resource_metal']),
			$LNG['tech'][901] ?? 'Metal',
			pretty_number($this->_fleet['fleet_resource_crystal']),
			$LNG['tech'][902] ?? 'Crystal',
			pretty_number($this->_fleet['fleet_resource_deuterium']),
			$LNG['tech'][903] ?? 'Deuterium'
		);
		PlayerUtil::sendMessage(
			$this->_fleet['fleet_owner'],
			0,
			$LNG['sys_mess_tower'] ?? '',
			4,
			$LNG['sys_mess_fleetback'] ?? '',
			$Message,
			$this->_fleet['fleet_end_time'],
			null,
			1,
			$this->_fleet['fleet_universe']
		);
		$this->RestoreFleet();
	}
}
