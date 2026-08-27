<?php

namespace HiveNova\Mission;

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\PlayerUtil;

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

class MissionCaseDestruction extends MissionCaseCombat
{
	protected function buildAttackFleets(array $incomingFleets)
	{
		$fleetAttack	= array();
		$userAttack		= array();

		foreach($incomingFleets as $fleetID => $fleetDetail)
		{
			$fleetAttack[$fleetID]['player']	= $this->getUser((int) $fleetDetail['fleet_owner']);

			$fleetAttack[$fleetID]['player']['factor']	= getFactors($fleetAttack[$fleetID]['player'], 'attack', $this->_fleet['fleet_start_time']);
			$fleetAttack[$fleetID]['fleetDetail']		= $fleetDetail;
			$fleetAttack[$fleetID]['unit']				= FleetFunctions::unserialize($fleetDetail['fleet_array']);

			$userAttack[$fleetAttack[$fleetID]['player']['id']]	= $fleetAttack[$fleetID]['player']['username'];
		}

		return array($fleetAttack, $userAttack);
	}

	protected function mergeParentDebrisRows(array &$targetPlanet, array $targetDebris): void
	{
		$targetPlanet 	+= $targetDebris;
	}

	protected function resolveMoonAndOutcome(
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
	) {
		$db	= Database::get();

		$reportInfo	= array(
			'thisFleet'				=> $this->_fleet,
			'debris'				=> $debris,
			'stealResource'			=> $stealResource,
			'moonChance'			=> NULL,
			'moonDestroy'			=> true,
			'moonName'				=> NULL,
			'moonDestroyChance'		=> NULL,
			'moonDestroySuccess'	=> NULL,
			'fleetDestroyChance'	=> NULL,
			'fleetDestroySuccess'	=> false,
		);

		$destroyedMoonParentId	= null;

		switch($combatResult['won'])
		{
			// Win
			case "a":
				$deathstars	= 0.0;
				foreach ($fleetAttack as $attackFleetDetail)
				{
					$deathstars	+= (float) ($attackFleetDetail['unit'][214] ?? 0);
				}
				$moonDestroyChance	= round((100 - sqrt((float) ($targetPlanet['diameter'] ?? 0))) * sqrt($deathstars), 1);

				// Max 100% | Min 0%
				$moonDestroyChance	= min($moonDestroyChance, 100);
				$moonDestroyChance	= max($moonDestroyChance, 0);

				$randChance	= mt_rand(1, 100);
				if ($randChance <= $moonDestroyChance)
				{
					$sql		= 'SELECT id FROM %%PLANETS%% WHERE id_luna = :moonId;';
					$planetID	= $db->selectSingle($sql, array(
						':moonId'	=> $targetPlanet['id']
					), 'id');


					$sql		= 'UPDATE %%FLEETS%% SET
					fleet_start_type		= 1,
					fleet_start_id			= :planetId
					WHERE fleet_start_id	= :moonId;';

					$db->update($sql, array(
						':planetId'	=> $planetID,
						':moonId'	=> $targetPlanet['id']
					));

					$sql		= 'UPDATE %%FLEETS%% SET
					fleet_end_type	= 1,
					fleet_end_id	= :planetId,
					fleet_mission	= IF(fleet_mission = 9, 1, fleet_mission)
					WHERE fleet_end_id = :moonId
					AND fleet_id != :fleetId;';

					$db->update($sql, array(
						':planetId'	=> $planetID,
						':moonId'	=> $targetPlanet['id'],
						':fleetId'	=> $this->_fleet['fleet_id']
					));

					$sql = "UPDATE %%AKS%% SET target = :planetId WHERE target = :moonId;";
					$db->update($sql, array(
						':planetId'	=> $planetID,
						':moonId'	=> $targetPlanet['id']
					));

					// Redirect fleets from moon to player's main planet.
					$db->update("UPDATE %%FLEETS%% SET fleet_start_id = :main_id, fleet_start_galaxy = :main_galaxy, fleet_start_system = :main_system, fleet_start_planet = :main_planet, fleet_start_type = 1 WHERE fleet_start_id = :destroyed", array(
						':main_id' => $targetUser['id_planet'],
						':main_galaxy' => $targetUser['galaxy'],
						':main_system' => $targetUser['system'],
						':main_planet' => $targetUser['planet'],
						':destroyed' => $targetPlanet['id'],
					));

					PlayerUtil::deletePlanet($targetPlanet['id']);
					$destroyedMoonParentId	= $planetID;

					$reportInfo['moonDestroySuccess'] = 1;
					\HiveNova\Core\FeatHooks::afterMoonDestroyed(
						(int) $this->_fleet['fleet_universe'],
						(int) $this->_fleet['fleet_owner']
					);
				} else {
					$reportInfo['moonDestroySuccess'] = 0;
				}

				$fleetDestroyChance	= round(sqrt($targetPlanet['diameter']) / 2);

				$randChance	= mt_rand(1, 100);
				if ($randChance <= $fleetDestroyChance)
				{
					$this->KillFleet();
					$reportInfo['fleetDestroySuccess'] = true;
				}
				else
				{
					$reportInfo['fleetDestroySuccess'] = false;
				}


				$reportInfo['moonDestroyChance']	= $moonDestroyChance;
				$reportInfo['fleetDestroyChance']	= $fleetDestroyChance;

				$attackStatus	= 'wons';
				$defendStatus	= 'loos';
				$class			= array('raportWin', 'raportLose');
				break;
			case "r":
				// Lose
				$attackStatus	= 'loos';
				$defendStatus	= 'wons';
				$class			= array('raportLose', 'raportWin');
				$reportInfo['moonDestroySuccess'] = -1;
				break;
			default:
				// Draw
				$attackStatus	= 'draws';
				$defendStatus	= 'draws';
				$class			= array('raportDraw', 'raportDraw');
				$reportInfo['moonDestroySuccess'] = -1;
				break;
		}

		return array($reportInfo, $attackStatus, $defendStatus, $class, $destroyedMoonParentId, $planetDebris);
	}
}
