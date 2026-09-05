<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\BattleReportId;
use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\LeftoverBonus;
use HiveNova\Core\BattleSimulatorCoords;

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

class ShowBattleSimulatorPage extends AbstractGamePage
{
	public static $requireModule = MODULE_SIMULATOR;

	function __construct() 
	{
		parent::__construct();
	}

	private function simulationCoords(): array
	{
		global $PLANET;

		$config = Config::get();
		$maxGalaxy = (int) $config->max_galaxy;
		$maxSystem = (int) $config->max_system;
		$maxPlanet = (int) $config->max_planets;

		$attacker = BattleSimulatorCoords::normalize([
			'galaxy' => $PLANET['galaxy'] ?? 0,
			'system' => $PLANET['system'] ?? 0,
			'planet' => $PLANET['planet'] ?? 0,
			'type' => $PLANET['planet_type'] ?? BattleSimulatorCoords::TYPE_PLANET,
		], [
			'galaxy' => 1,
			'system' => 1,
			'planet' => 1,
			'type' => BattleSimulatorCoords::TYPE_PLANET,
		], $maxGalaxy, $maxSystem, $maxPlanet);

		$defender = BattleSimulatorCoords::normalize([
			'galaxy' => HTTP::_GP('galaxy', 0),
			'system' => HTTP::_GP('system', 0),
			'planet' => HTTP::_GP('planet', 0),
			'type' => HTTP::_GP('type', 0),
			'planettype' => HTTP::_GP('planettype', 0),
		], BattleSimulatorCoords::DEFAULT_DEFENDER, $maxGalaxy, $maxSystem, $maxPlanet);

		return [
			'attacker' => $attacker,
			'defender' => $defender,
		];
	}

	function send()
	{
		global $reslist, $pricelist, $LNG, $USER;
		
		if(!isset($_REQUEST['battleinput'])) {
			$this->sendJSON(0);
		}

		$simCoords = $this->simulationCoords();
		$attackerFleetDetail = BattleSimulatorCoords::attackerFleetDetail($simCoords['attacker'], $simCoords['defender']);
		$defenderFleetDetail = BattleSimulatorCoords::defenderFleetDetail($simCoords['defender']);
		
		$BattleArray	= $_REQUEST['battleinput'];
		$elements	= array(0, 0);
		foreach($BattleArray as $BattleSlotID => $BattleSlot)
		{
			if(isset($BattleSlot[0]) && (array_sum(array_map('intval', $BattleSlot[0])) > 0 || $BattleSlotID == 0))
			{
				$attacker	= array();
				$attacker['fleetDetail'] 		= $attackerFleetDetail;
				
				$attacker['player']				= array(
					'id' => (1000 + $BattleSlotID + 1),
					'username'	=> $LNG['bs_atter'].' Nr.'.($BattleSlotID + 1),
					'military_tech' => (int) $BattleSlot[0][109],
					'defence_tech' => (int) $BattleSlot[0][110],
					'shield_tech' => (int) $BattleSlot[0][111],
					'dm_attack' => 0,
					'dm_defensive' => 0,
					'universe' => $USER['universe']
				);
				$attacker['player'] = array_merge($attacker['player'], LeftoverBonus::playerTechsFromBattleInput($BattleSlot[0]));
				
				$attacker['player']['factor']	= getFactors($attacker['player'], 'attack');
				
				foreach($BattleSlot[0] as $ID => $Count)
				{
					if(!in_array($ID, $reslist['fleet']) || $BattleSlot[0][$ID] <= 0)
					{
						unset($BattleSlot[0][$ID]);
					}
				}
				
				$attacker['unit'] 	= array_map(intval(...), $BattleSlot[0]);
				
				$attackers[]	= $attacker;
			}
				
			if(isset($BattleSlot[1]) && (array_sum(array_map('intval', $BattleSlot[1])) > 0 || $BattleSlotID == 0))
			{
				$defender	= array();
				$defender['fleetDetail'] 		= $defenderFleetDetail;
				
				$defender['player']				= array(
					'id' => (2000 + $BattleSlotID + 1),
					'username'	=> $LNG['bs_deffer'].' Nr.'.($BattleSlotID + 1),
					'military_tech' => (int) $BattleSlot[1][109],
					'defence_tech' => (int) $BattleSlot[1][110],
					'shield_tech' => (int) $BattleSlot[1][111],
					'dm_attack' => 0,
					'dm_defensive' => 0,
					'universe' => $USER['universe']
				);
				$defender['player'] = array_merge($defender['player'], LeftoverBonus::playerTechsFromBattleInput($BattleSlot[1]));
				
				$defender['player']['factor']	= getFactors($defender['player'], 'attack');
				
				foreach($BattleSlot[1] as $ID => $Count)
				{
					if((!in_array($ID, $reslist['fleet']) && !in_array($ID, $reslist['defense'])) || $BattleSlot[1][$ID] <= 0)
					{
						unset($BattleSlot[1][$ID]);
					}
				}
				
				$defender['unit'] 	= array_map(intval(...), $BattleSlot[1]);
				$defenders[]	= $defender;
			}
		}
		
		$LNG->includeData(array('FLEET'));
		
		require_once 'includes/classes/missions/functions/calculateAttack.php';
		require_once 'includes/classes/missions/functions/calculateSteal.php';
		require_once 'includes/classes/missions/functions/GenerateReport.php';
		
		$combatResult	= calculateAttack($attackers, $defenders, Config::get()->Fleet_Cdr / 100, Config::get()->Defs_Cdr / 100);
		
		if($combatResult['won'] == "a")
		{
			$stealResource = calculateSteal($attackers, array(
			'metal' => (int) $BattleArray[0][1][901],
			'crystal' => (int) $BattleArray[0][1][902],
			'deuterium' => (int) $BattleArray[0][1][903]
			), true);
		}
		else
		{
			$stealResource = array(
				901 => 0,
				902 => 0,
				903 => 0
			);
		}
		
		$debris	= array();
		
		foreach(array(901, 902) as $elementID)
		{
			$debris[$elementID]			= $combatResult['debris']['attacker'][$elementID] + $combatResult['debris']['defender'][$elementID];
		}
		
		$debrisTotal		= array_sum($debris);
		
		$moonFactor			= Config::get()->moon_factor;
		$maxMoonChance		= Config::get()->moon_chance;
		
		$chanceCreateMoon	= round($debrisTotal / 100000 * $moonFactor);
		$chanceCreateMoon	= min($chanceCreateMoon, $maxMoonChance);
		
		$sumSteal	= array_sum($stealResource);
		
		$stealResourceInformation	= sprintf($LNG['bs_derbis_raport'],
			pretty_number(ceil($debrisTotal / $pricelist[209]['capacity'])), $LNG['tech'][209]
		);
		
		$stealResourceInformation	.= '<br>';
		
		$stealResourceInformation	.= sprintf($LNG['bs_steal_raport'], 
			pretty_number(ceil($sumSteal / $pricelist[202]['capacity'])), $LNG['tech'][202], 
			pretty_number(ceil($sumSteal / $pricelist[203]['capacity'])), $LNG['tech'][203]
		);

		$reportInfo	= array(
			'thisFleet'				=> array_merge($attackerFleetDetail, array(
				'fleet_start_time'		=> TIMESTAMP,
			)),
			'debris'				=> $debris,
			'stealResource'			=> $stealResource,
			'moonChance'			=> $chanceCreateMoon,
			'moonDestroy'			=> false,
			'moonName'				=> NULL,
			'moonDestroyChance'		=> NULL,
			'moonDestroySuccess'	=> NULL,
			'fleetDestroyChance'	=> NULL,
			'fleetDestroySuccess'	=> NULL,
			'additionalInfo'		=> $stealResourceInformation,
		);
		
		$reportData	= GenerateReport($combatResult, $reportInfo);
		$reportID	= BattleReportId::generate();

        $db = Database::get();

        $sql = "INSERT INTO %%RW%% SET rid = :reportID, raport = :reportData, time = :time;";
        $db->insert($sql,array(
            ':reportID'     => $reportID,
            ':reportData'   => serialize($reportData),
            ':time'         => TIMESTAMP
        ));

        $this->sendJSON($reportID);
	}
	
	function show()
	{
		global $USER, $PLANET, $reslist, $resource;

		$Slots			= HTTP::_GP('slots', 1);


		$BattleArray[0][0][109]	= $USER[$resource[109]];
		$BattleArray[0][0][110]	= $USER[$resource[110]];
		$BattleArray[0][0][111]	= $USER[$resource[111]];
		$BattleArray[0][0][114]	= $USER[$resource[114]];
		$BattleArray[0][0][120]	= $USER[$resource[120]];
		$BattleArray[0][0][121]	= $USER[$resource[121]];
		$BattleArray[0][0][122]	= $USER[$resource[122]];
		
		if(empty($_REQUEST['battleinput']))
		{
			foreach($reslist['fleet'] as $ID)
			{
				if(FleetFunctions::GetFleetMaxSpeed($ID, $USER) > 0)
				{
					// Add just flyable elements
					$BattleArray[0][0][$ID]	= $PLANET[$resource[$ID]];
				}
			}
		}
		else
		{
			$BattleArray	= HTTP::_GP('battleinput', array());
		}
		
		if(isset($_REQUEST['im']))
		{
			foreach($_REQUEST['im'] as $ID => $Count)
			{
				$BattleArray[0][1][$ID]	= floatToString($Count);
			}
		}
		
		$this->tplObj->loadscript('simple-tabs.js');
		$this->tplObj->loadscript('battlesim.js');

		$simCoords = $this->simulationCoords();
		
		$this->assign(array(
			'Slots'			=> $Slots,
			'battleinput'	=> $BattleArray,
			'fleetList'		=> $reslist['fleet'],
			'defensiveList'	=> $reslist['defense'],
			'simGalaxy'		=> $simCoords['defender']['galaxy'],
			'simSystem'		=> $simCoords['defender']['system'],
			'simPlanet'		=> $simCoords['defender']['planet'],
			'simType'		=> $simCoords['defender']['type'],
		));
		
		$this->display('page.battleSimulator.default.tpl');   
	}
}
