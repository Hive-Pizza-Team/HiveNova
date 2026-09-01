<?php

namespace HiveNova\Mission;

use HiveNova\Core\BattleReportId;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\EventFirehoseWriter;
use HiveNova\Core\DiscordWebhookService;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\MissionFunctions;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\ResourceUpdate;
use HiveNova\Repository\PlanetRepository;
use OutOfRangeException;

/**
 * Shared TargetEvent combat pipeline for attack and destruction missions.
 *
 * Mission-specific behavior is provided via protected hooks (PVE attackers,
 * moon create vs destroy, debris persistence, report extras).
 */
abstract class MissionCaseCombat extends MissionFunctions implements Mission
{
	function __construct($Fleet)
	{
		$this->_fleet	= $Fleet;
	}

	function TargetEvent()
	{
		global $resource, $reslist;

		$db				= Database::get();
		$config			= Config::get($this->_fleet['fleet_universe']);

		$fleetAttack	= array();
		$fleetDefend	= array();

		$userAttack		= array();
		$userDefend		= array();

		$incomingFleets	= array();

		$stealResource	= array(
			901	=> 0,
			902	=> 0,
			903	=> 0,
		);

		$debris			= array();
		$planetDebris	= array();

		$debrisResource	= array(901, 902);

		$messageHTML	= CombatReportMessageBuilder::template();

		$targetPlanet 	= PlanetRepository::getPlanetById((int) $this->_fleet['fleet_end_id']);

		// return fleet if target planet deleted
		if($targetPlanet == false)
		{
			$this->setState(FLEET_RETURN);
			$this->SaveFleet();
			return;
		}

		$targetUser		= $this->getUser((int) $targetPlanet['id_owner']);
		if ($targetUser === []) {
			$this->setState(FLEET_RETURN);
			$this->SaveFleet();
			return;
		}
		$targetUser['factor']	= getFactors($targetUser, 'basic', $this->_fleet['fleet_start_time']);

		$planetUpdater	= new ResourceUpdate();
		$planetUpdater->setResourceData($resource, $reslist);
		list($targetUser, $targetPlanet)	= $planetUpdater->CalcResource($targetUser, $targetPlanet, true, $this->_fleet['fleet_start_time']);

		if($this->_fleet['fleet_group'] != 0)
		{
			$sql	= "DELETE FROM %%AKS%% WHERE id = :acsId;";
			$db->delete($sql, array(
				':acsId'	=> $this->_fleet['fleet_group'],
			));

			$sql	= "SELECT * FROM %%FLEETS%% WHERE fleet_group = :acsId;";

			$incomingFleetsResult = $db->select($sql, array(
				':acsId'	=> $this->_fleet['fleet_group'],
			));

			foreach($incomingFleetsResult as $incomingFleetRow)
			{
				$incomingFleets[$incomingFleetRow['fleet_id']] = $incomingFleetRow;
			}

			unset($incomingFleetsResult);
		}
		else
		{
			$incomingFleets = array($this->_fleet['fleet_id'] => $this->_fleet);
		}

		list($fleetAttack, $userAttack) = $this->buildAttackFleets($incomingFleets);

		$sql	= "SELECT * FROM %%FLEETS%%
		WHERE fleet_mission		= :mission
		AND fleet_end_id		= :fleetEndId
		AND fleet_start_time 	<= :timeStamp
		AND fleet_end_stay 		>= :timeStamp;";

		$targetFleetsResult = $db->select($sql, array(
			':mission'		=> 5,
			':fleetEndId'	=> $this->_fleet['fleet_end_id'],
			':timeStamp'	=> $this->_fleet['fleet_start_time']
		));

		foreach($targetFleetsResult as $fleetDetail)
		{
			$fleetID	= $fleetDetail['fleet_id'];

			$fleetDefend[$fleetID]['player']			= $this->getUser((int) $fleetDetail['fleet_owner']);

			$fleetDefend[$fleetID]['player']['factor']	= getFactors($fleetDefend[$fleetID]['player'], 'attack', $this->_fleet['fleet_start_time']);
			$fleetDefend[$fleetID]['fleetDetail']		= $fleetDetail;
			$fleetDefend[$fleetID]['unit']				= FleetFunctions::unserialize($fleetDetail['fleet_array']);

			$userDefend[$fleetDefend[$fleetID]['player']['id']]	= $fleetDefend[$fleetID]['player']['username'];
		}

		unset($targetFleetsResult);

		$fleetDefend[0]['player']			= $targetUser;
		$fleetDefend[0]['player']['factor']	= getFactors($fleetDefend[0]['player'], 'attack', $this->_fleet['fleet_start_time']);
		$fleetDefend[0]['fleetDetail']		= array(
			'fleet_start_galaxy'	=> $targetPlanet['galaxy'],
			'fleet_start_system'	=> $targetPlanet['system'],
			'fleet_start_planet'	=> $targetPlanet['planet'],
			'fleet_start_type'		=> $targetPlanet['planet_type'],
		);

		$fleetDefend[0]['unit']				= array();

		foreach(array_merge($reslist['fleet'], $reslist['defense']) as $elementID)
		{
			if (empty($targetPlanet[$resource[$elementID]])) continue;

			$fleetDefend[0]['unit'][$elementID] = $targetPlanet[$resource[$elementID]];
		}

		$userDefend[$fleetDefend[0]['player']['id']]	= $fleetDefend[0]['player']['username'];

		require_once 'includes/classes/missions/functions/calculateAttack.php';

		$fleetIntoDebris	= $config->Fleet_Cdr / 100;
		$defIntoDebris		= $config->Defs_Cdr / 100;

		$this->beforeCalculateAttack($config);

		$combatResult 		= calculateAttack($fleetAttack, $fleetDefend, $fleetIntoDebris, $defIntoDebris);

		$reportExtras		= $this->combatReportExtras($incomingFleets, $fleetAttack);

		foreach ($fleetAttack as $fleetID => $fleetDetail)
		{
			$fleetArray = '';
			$totalCount = 0;

			$fleetDetail['unit']	= array_filter($fleetDetail['unit']);
			foreach ($fleetDetail['unit'] as $elementID => $amount)
			{
				$fleetArray .= $elementID.','.floatToString($amount).';';
				$totalCount += $amount;
			}

			if($totalCount == 0)
			{
				if($this->_fleet['fleet_id'] == $fleetID)
				{
					$this->KillFleet();
				}
				else
				{
					$sql	= 'DELETE %%FLEETS%%, %%FLEETS_EVENT%%
					FROM %%FLEETS%%
					INNER JOIN %%FLEETS_EVENT%% ON fleetID = fleet_id
					WHERE fleet_id = :fleetId;';

					$db->delete($sql, array(
						':fleetId'	=> $fleetID
					));
				}

				$sql	= 'UPDATE %%LOG_FLEETS%% SET fleet_state = :fleetState WHERE fleet_id = :fleetId;';
				$db->update($sql, array(
					':fleetId'		=> $fleetID,
					':fleetState'	=> FLEET_HOLD,
				));

				unset($fleetAttack[$fleetID]);
			}
			elseif($totalCount > 0)
			{
				$sql = "UPDATE %%FLEETS%% fleet, %%LOG_FLEETS%% log SET
				fleet.fleet_array	= :fleetData,
				fleet.fleet_amount	= :fleetCount,
				log.fleet_array		= :fleetData,
				log.fleet_amount	= :fleetCount
				WHERE fleet.fleet_id = :fleetId AND log.fleet_id = :fleetId;";

				$db->update($sql, array(
					':fleetData'	=> substr($fleetArray, 0, -1),
					':fleetCount'	=> $totalCount,
					':fleetId'		=> $fleetID
			  	));
			}
			else
			{
				throw new OutOfRangeException("Negative Fleet amount ....");
			}
		}

		foreach ($fleetDefend as $fleetID => $fleetDetail)
		{
			if($fleetID != 0)
			{
				// Stay fleet
				$fleetArray = '';
				$totalCount = 0;

				$fleetDetail['unit']	= array_filter($fleetDetail['unit']);

				foreach ($fleetDetail['unit'] as $elementID => $amount)
				{
					$fleetArray .= $elementID.','.floatToString($amount).';';
					$totalCount += $amount;
				}

				if($totalCount == 0)
				{
					$sql	= 'DELETE %%FLEETS%%, %%FLEETS_EVENT%%
					FROM %%FLEETS%%
					INNER JOIN %%FLEETS_EVENT%% ON fleetID = fleet_id
					WHERE fleet_id = :fleetId;';

					$db->delete($sql, array(
						':fleetId'	=> $fleetID
					));

					$sql	= 'UPDATE %%LOG_FLEETS%% SET fleet_state = :fleetState WHERE fleet_id = :fleetId;';
					$db->update($sql, array(
						':fleetId'		=> $fleetID,
						':fleetState'	=> FLEET_HOLD,
					));

					unset($fleetAttack[$fleetID]);
				}
				elseif($totalCount > 0)
				{
					$sql = "UPDATE %%FLEETS%% fleet, %%LOG_FLEETS%% log SET
					fleet.fleet_array	= :fleetData,
					fleet.fleet_amount	= :fleetCount,
					log.fleet_array		= :fleetData,
					log.fleet_amount	= :fleetCount
					WHERE fleet.fleet_id = :fleetId AND log.fleet_id = :fleetId;";

					$db->update($sql, array(
	   					':fleetData'	=> substr($fleetArray, 0, -1),
						':fleetCount'	=> $totalCount,
						':fleetId'		=> $fleetID
					));
				}
				else
				{
					throw new OutOfRangeException("Negative Fleet amount ....");
				}
			}
			else
			{
				$params	= array(':planetId' => $this->_fleet['fleet_end_id']);

				// Planet fleet
				$fleetArray = array();
				foreach ($fleetDetail['unit'] as $elementID => $amount)
				{
					$fleetArray[] = '`'.$resource[$elementID].'` = :'.$resource[$elementID];
					$params[':'.$resource[$elementID]]	= $amount;
				}

				if(!empty($fleetArray))
				{
					$sql = 'UPDATE %%PLANETS%% SET '.implode(', ', $fleetArray).' WHERE id = :planetId;';
					$db->update($sql, $params);
				}
			}
		}

		if ($combatResult['won'] == "a")
		{
			require_once 'includes/classes/missions/functions/calculateSteal.php';
			$db->beginTransaction();
			try {
				$lockedPlanet = $db->selectSingle(
					'SELECT metal, crystal, deuterium FROM %%PLANETS%% WHERE id = :planetId FOR UPDATE',
					array(':planetId' => $this->_fleet['fleet_end_id'])
				);
				if (is_array($lockedPlanet)) {
					$targetPlanet[$resource[901]] = $lockedPlanet[$resource[901]];
					$targetPlanet[$resource[902]] = $lockedPlanet[$resource[902]];
					$targetPlanet[$resource[903]] = $lockedPlanet[$resource[903]];
				}
				$stealResource = calculateSteal($fleetAttack, $targetPlanet);
				$db->update(
					'UPDATE %%PLANETS%% SET
					metal		= GREATEST(0, metal - :metal),
					crystal		= GREATEST(0, crystal - :crystal),
					deuterium	= GREATEST(0, deuterium - :deuterium)
					WHERE id = :planetId',
					array(
						':metal'		=> $stealResource[901],
						':crystal'		=> $stealResource[902],
						':deuterium'	=> $stealResource[903],
						':planetId'		=> $this->_fleet['fleet_end_id'],
					)
				);
				$db->commit();
			} catch (\Throwable $e) {
				$db->rollback();
				throw $e;
			}
		}

		if($this->_fleet['fleet_end_type'] == 3)
		{
			// Use planet debris, if attack on moons
			$sql			= "SELECT der_metal, der_crystal FROM %%PLANETS%% WHERE id_luna = :moonId;";
			$targetDebris	= $db->selectSingle($sql, array(
				':moonId'	=> $this->_fleet['fleet_end_id']
			));
			if (is_array($targetDebris)) {
				$this->mergeParentDebrisRows($targetPlanet, $targetDebris);
			}
		}

		foreach($debrisResource as $elementID)
		{
			$debris[$elementID]			= $combatResult['debris']['attacker'][$elementID] + $combatResult['debris']['defender'][$elementID];
			$planetDebris[$elementID]	= $targetPlanet['der_'.$resource[$elementID]] + $debris[$elementID];
		}

		list($reportInfo, $attackStatus, $defendStatus, $class, $destroyedMoonParentId, $planetDebris) =
			$this->resolveMoonAndOutcome(
				$combatResult,
				$debris,
				$stealResource,
				$planetDebris,
				$debrisResource,
				$fleetAttack,
				$targetPlanet,
				$targetUser,
				$config,
				$reportExtras
			);

		require_once 'includes/classes/missions/functions/GenerateReport.php';
		$reportData	= GenerateReport($combatResult, $reportInfo);

		$reportID	= BattleReportId::generate();

		$sql	= 'INSERT INTO %%RW%% SET
		rid 		= :reportId,
		raport 		= :reportData,
		time 		= :time,
		attacker	= :attackers,
		defender	= :defenders;';

		$db->insert($sql, array(
			':reportId'		=> $reportID,
			':reportData'	=> serialize($reportData),
			':time'			=> $this->_fleet['fleet_start_time'],
			':attackers'	=> implode(',', array_keys($userAttack)),
			':defenders'	=> implode(',', array_keys($userDefend))
		));

		$i = 0;

		foreach(array($userAttack, $userDefend) as $data)
		{
			foreach($data as $userID => $userName)
			{
				$LNG		= $this->getLanguage(NULL, $userID);

				$message	= sprintf($messageHTML,
					$reportID,
					$class[$i],
					$LNG['sys_mess_attack_report'],
					sprintf(
						$LNG['sys_adress_planet'],
						$this->_fleet['fleet_end_galaxy'],
						$this->_fleet['fleet_end_system'],
						$this->_fleet['fleet_end_planet']
					),
					$LNG['type_planet_short_'.$this->_fleet['fleet_end_type']],
					$LNG['sys_lost'],
					$class[0],
					$LNG['sys_attack_attacker_pos'],
					pretty_number($combatResult['unitLost']['attacker']),
					$class[1],
					$LNG['sys_attack_defender_pos'],
					pretty_number($combatResult['unitLost']['defender']),
					$LNG['sys_gain'],
					$LNG['tech'][901],
					pretty_number($stealResource[901]),
					$LNG['tech'][902],
					pretty_number($stealResource[902]),
					$LNG['tech'][903],
					pretty_number($stealResource[903]),
					$LNG['sys_debris'],
					$LNG['tech'][901],
					pretty_number($debris[901]),
					$LNG['tech'][902],
					pretty_number($debris[902])
				);

				PlayerUtil::sendMessage($userID, 0, $LNG['sys_mess_tower'], 3, $LNG['sys_mess_attack_report'],
					$message, $this->_fleet['fleet_start_time'], NULL, 1, $this->_fleet['fleet_universe']);

				$sql	= "INSERT INTO %%TOPKB_USERS%% SET
				rid			= :reportId,
				role		= :userRole,
				username	= :username,
				uid			= :userId;";

				$db->insert($sql, array(
					':reportId'	=> $reportID,
					':userRole'	=> $i + 1,
					':username'	=> $userName,
					':userId'	=> $userID
				));
			}

			$i++;
		}

		if($this->_fleet['fleet_end_type'] == 3)
		{
			$debrisType	= 'id_luna';
		}
		else
		{
			$debrisType	= 'id';
		}

		$debrisPlanetId	= $this->_fleet['fleet_end_id'];
		if (!empty($destroyedMoonParentId))
		{
			$debrisType		= 'id';
			$debrisPlanetId	= $destroyedMoonParentId;
		}

		$db->beginTransaction();
		try {
			if ($this->shouldPersistDebris()) {
				if ($debrisType === 'id_luna') {
					$lockedDebris = $db->selectSingle(
						'SELECT der_metal, der_crystal FROM %%PLANETS%% WHERE id_luna = :moonId FOR UPDATE',
						array(':moonId' => $debrisPlanetId)
					);
					$derMetal = is_array($lockedDebris) ? (int) $lockedDebris['der_metal'] + (int) $debris[901] : (int) $planetDebris[901];
					$derCrystal = is_array($lockedDebris) ? (int) $lockedDebris['der_crystal'] + (int) $debris[902] : (int) $planetDebris[902];
				} else {
					$lockedDebris = $db->selectSingle(
						'SELECT der_metal, der_crystal FROM %%PLANETS%% WHERE id = :planetId FOR UPDATE',
						array(':planetId' => $debrisPlanetId)
					);
					$derMetal = is_array($lockedDebris) ? (int) $lockedDebris['der_metal'] + (int) $debris[901] : (int) $planetDebris[901];
					$derCrystal = is_array($lockedDebris) ? (int) $lockedDebris['der_crystal'] + (int) $debris[902] : (int) $planetDebris[902];
				}

				$sql = 'UPDATE %%PLANETS%% SET
				der_metal	= :metal,
				der_crystal	= :crystal
				WHERE '.$debrisType.' = :planetId;';

				$db->update($sql, array(
					':metal'	=> $derMetal,
					':crystal'	=> $derCrystal,
					':planetId'	=> $debrisPlanetId
				));
			}

			$db->commit();
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}

		$sql = 'INSERT INTO %%TOPKB%% SET
		units 		= :units,
		rid			= :reportId,
		time		= :time,
		universe	= :universe,
		result		= :result;';

		$db->insert($sql, array(
			':units'	=> $combatResult['unitLost']['attacker'] + $combatResult['unitLost']['defender'],
			':reportId'	=> $reportID,
			':time'		=> $this->_fleet['fleet_start_time'],
			':universe'	=> $this->_fleet['fleet_universe'],
			':result'	=> $combatResult['won']
		));

		EventFirehoseWriter::record(
			(int) $this->_fleet['fleet_universe'],
			(int) $this->_fleet['fleet_start_time'],
			(float) ($combatResult['unitLost']['attacker'] + $combatResult['unitLost']['defender']),
			(string) $combatResult['won'],
			(string) (reset($userAttack) ?: ''),
			(string) (reset($userDefend) ?: '')
		);

		if ($this->shouldUpdateAttackerStats($userAttack)) {
			$sql = 'UPDATE %%USERS%% SET
			`'.$attackStatus.'` = `'.$attackStatus.'` + 1,
			kbmetal		= kbmetal + :debrisMetal,
			kbcrystal	= kbcrystal + :debrisCrystal,
			lostunits	= lostunits + :lostUnits,
			desunits	= desunits + :destroyedUnits
			WHERE id IN ('.implode(',', array_keys($userAttack)).');';

			$db->update($sql, array(
				':debrisMetal'		=> $debris[901],
				':debrisCrystal'	=> $debris[902],
				':lostUnits'		=> $combatResult['unitLost']['attacker'],
				':destroyedUnits'	=> $combatResult['unitLost']['defender']
			));
		}

		$sql = 'UPDATE %%USERS%% SET
		`'.$defendStatus.'` = `'.$defendStatus.'` + 1,
		kbmetal		= kbmetal + :debrisMetal,
		kbcrystal	= kbcrystal + :debrisCrystal,
		lostunits	= lostunits + :lostUnits,
		desunits	= desunits + :destroyedUnits
		WHERE id IN ('.implode(',', array_keys($userDefend)).');';

		$db->update($sql, array(
			':debrisMetal'		=> $debris[901],
			':debrisCrystal'	=> $debris[902],
			':lostUnits'		=> $combatResult['unitLost']['defender'],
			':destroyedUnits'	=> $combatResult['unitLost']['attacker']
		));

		\HiveNova\Core\AchievementHooks::afterCombatWithFeats(
			$userAttack,
			$userDefend,
			$attackStatus,
			$defendStatus,
			(int) $this->_fleet['fleet_universe'],
			\HiveNova\Core\FeatHooks::attackerShipCount((string) ($this->_fleet['fleet_array'] ?? '')),
			\HiveNova\Core\FeatHooks::planetDefenseCount($targetPlanet),
			\HiveNova\Core\FeatHooks::fleetHasDeathstar((string) ($this->_fleet['fleet_array'] ?? '')),
			\HiveNova\Core\FeatHooks::planetHasDeathstar($targetPlanet)
		);

		$this->setState(FLEET_RETURN);
		$this->SaveFleet();

		DiscordWebhookService::notifyCombatResolved(
			(int) $targetUser['id'],
			(int) $this->_fleet['fleet_mission'],
			(int) $this->_fleet['fleet_end_galaxy'],
			(int) $this->_fleet['fleet_end_system'],
			(int) $this->_fleet['fleet_end_planet'],
			(int) $this->_fleet['fleet_end_type'],
			(int) $this->_fleet['fleet_owner'],
			(string) $this->_fleet['fleet_array']
		);
	}

	function EndStayEvent()
	{
		return;
	}

	function ReturnEvent()
	{
		$LNG		= $this->getLanguage(NULL, $this->_fleet['fleet_owner']);


		$planetName	= PlanetRepository::getPlanetName($this->_fleet['fleet_end_id']);

		$Message	= sprintf(
			$LNG['sys_fleet_won'],
			$planetName,
			GetTargetAddressLink($this->_fleet, ''),
			pretty_number($this->_fleet['fleet_resource_metal']),
			$LNG['tech'][901],
			pretty_number($this->_fleet['fleet_resource_crystal']),
			$LNG['tech'][902],
			pretty_number($this->_fleet['fleet_resource_deuterium']),
			$LNG['tech'][903]
		);

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 4, $LNG['sys_mess_fleetback'],
			$Message, $this->_fleet['fleet_end_time'], NULL, 1, $this->_fleet['fleet_universe']);

		$this->RestoreFleet();
	}

	/**
	 * Build attacker fleets and username map from incoming fleets.
	 *
	 * @param array<int, array<string, mixed>> $incomingFleets
	 * @return array{0: array, 1: array} [$fleetAttack, $userAttack]
	 */
	abstract protected function buildAttackFleets(array $incomingFleets);

	/**
	 * Mission-specific setup immediately before calculateAttack().
	 */
	protected function beforeCalculateAttack(Config $config): void
	{
	}

	/**
	 * Extra reportInfo keys after combat (e.g. fuelConsumption for attack).
	 *
	 * @param array<int, array<string, mixed>> $incomingFleets
	 * @param array<int, array<string, mixed>> $fleetAttack
	 * @return array<string, mixed>
	 */
	protected function combatReportExtras(array $incomingFleets, array $fleetAttack): array
	{
		return array();
	}

	/**
	 * Merge parent-planet debris rows into the moon target planet array.
	 *
	 * @param array<string, mixed> $targetPlanet
	 * @param array<string, mixed> $targetDebris
	 */
	abstract protected function mergeParentDebrisRows(array &$targetPlanet, array $targetDebris): void;

	/**
	 * Moon create/destroy policy plus combat outcome status for reports/stats.
	 *
	 * @param array<string, mixed> $combatResult
	 * @param array<int, float|int> $debris
	 * @param array<int, float|int> $stealResource
	 * @param array<int, float|int> $planetDebris
	 * @param array<int, int> $debrisResource
	 * @param array<int, array<string, mixed>> $fleetAttack
	 * @param array<string, mixed> $targetPlanet
	 * @param array<string, mixed> $targetUser
	 * @param array<string, mixed> $reportExtras
	 * @return array{0: array, 1: string, 2: string, 3: array, 4: int|null, 5: array}
	 *         [$reportInfo, $attackStatus, $defendStatus, $class, $destroyedMoonParentId, $planetDebris]
	 */
	abstract protected function resolveMoonAndOutcome(
		array $combatResult,
		array $debris,
		array $stealResource,
		array $planetDebris,
		array $debrisResource,
		array $fleetAttack,
		array $targetPlanet,
		array $targetUser,
		Config $config,
		array $reportExtras
	);

	/**
	 * Whether debris should be written to the planet row.
	 */
	protected function shouldPersistDebris(): bool
	{
		return true;
	}

	/**
	 * Whether attacker user KB stats should be updated.
	 *
	 * @param array<int, string> $userAttack
	 */
	protected function shouldUpdateAttackerStats(array $userAttack): bool
	{
		return true;
	}
}
