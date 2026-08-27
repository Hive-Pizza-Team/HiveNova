<?php

namespace HiveNova\Mission;

use HiveNova\Core\Config;
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

class MissionCaseAttack extends MissionCaseCombat
{
	protected function buildAttackFleets(array $incomingFleets)
	{
		$fleetAttack	= array();
		$userAttack		= array();

		foreach($incomingFleets as $fleetID => $fleetDetail)
		{
			if ((int) $fleetDetail['fleet_owner'] === 0) {
				$family = \HiveNova\Core\PveNpcFleetFactory::familyFromFleetArray((string) $fleetDetail['fleet_array']);
				$fleetAttack[$fleetID]['player'] = \HiveNova\Core\PveNpcFleetFactory::syntheticPlayer(
					\HiveNova\Core\PveNpcFleetFactory::displayName($family)
				);
				$fleetAttack[$fleetID]['player']['universe'] = (int) $this->_fleet['fleet_universe'];
			} else {
				$fleetAttack[$fleetID]['player']	= $this->getUser((int) $fleetDetail['fleet_owner']);
			}

			$fleetAttack[$fleetID]['player']['factor']	= getFactors($fleetAttack[$fleetID]['player'], 'attack', $this->_fleet['fleet_start_time']);
			$fleetAttack[$fleetID]['fleetDetail']		= $fleetDetail;
			$fleetAttack[$fleetID]['unit']				= FleetFunctions::unserialize($fleetDetail['fleet_array']);

			$attackerId = (int) $fleetAttack[$fleetID]['player']['id'];
			if ($attackerId !== 0) {
				$userAttack[$attackerId] = $fleetAttack[$fleetID]['player']['username'];
			}
		}

		return array($fleetAttack, $userAttack);
	}

	protected function beforeCalculateAttack(Config $config): void
	{
		// Chance of Moon
		// $moonFactor = $config->moon_factor * 100000;
		// define('MOON_UNIT_PROB', $moonFactor);

		// Max. Chance of Moon
		if (!defined('MAX_MOON_PROB')) {
			define('MAX_MOON_PROB', $config->moon_chance); // max probability to moon creation.
		}
	}

	protected function combatReportExtras(array $incomingFleets, array $fleetAttack): array
	{
		$fuelConsumption = 0;
		$gameSpeed = Config::get((int) $this->_fleet['fleet_universe'])->fleet_speed / 2500;
		foreach ($incomingFleets as $fleetID => $fleetDetail) {
			$distance = FleetFunctions::GetTargetDistance(
				[$fleetDetail['fleet_start_galaxy'], $fleetDetail['fleet_start_system'], $fleetDetail['fleet_start_planet']],
				[$fleetDetail['fleet_end_galaxy'], $fleetDetail['fleet_end_system'], $fleetDetail['fleet_end_planet']]
			);
			$duration = $fleetDetail['fleet_end_time'] - $fleetDetail['fleet_start_time'];
			$fleetArray = FleetFunctions::unserialize($fleetDetail['fleet_array']);
			$fuelConsumption += FleetFunctions::GetFleetConsumption(
				$fleetArray, $duration, $distance, $fleetAttack[$fleetID]['player'], $gameSpeed
			);
		}

		return array('fuelConsumption' => $fuelConsumption);
	}

	protected function mergeParentDebrisRows(array &$targetPlanet, array $targetDebris): void
	{
		$targetPlanet['der_metal'] += $targetDebris['der_metal'];
		$targetPlanet['der_crystal'] += $targetDebris['der_crystal'];
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
		$debrisTotal		= array_sum($debris);

		$moonFactor			= $config->moon_factor;
		$maxMoonChance		= $config->moon_chance;

		if($targetPlanet['id_luna'] == 0 && $targetPlanet['planet_type'] == 1)
		{
			$chanceCreateMoon	= round($debrisTotal / 100000 * $moonFactor);
			$chanceCreateMoon	= min($chanceCreateMoon, $maxMoonChance);
		}
		else
		{
			$chanceCreateMoon	= 0;
		}

		$reportInfo	= array(
			'thisFleet'				=> $this->_fleet,
			'debris'				=> $debris,
			'stealResource'			=> $stealResource,
			'moonChance'			=> $chanceCreateMoon,
			'moonDestroy'			=> false,
			'moonName'				=> NULL,
			'moonDestroyChance'		=> NULL,
			'moonDestroySuccess'	=> NULL,
			'fleetDestroyChance'	=> NULL,
			'fleetDestroySuccess'	=> NULL,
			'fuelConsumption'		=> $reportExtras['fuelConsumption'] ?? 0,
		);

		$randChance	= mt_rand(1, 100);
		if ($randChance <= $chanceCreateMoon)
		{
			$LNG					= $this->getLanguage($targetUser['lang']);
			$reportInfo['moonName']	= $LNG['type_planet_3'];

			PlayerUtil::createMoon(
				$this->_fleet['fleet_universe'],
				$this->_fleet['fleet_end_galaxy'],
				$this->_fleet['fleet_end_system'],
				$this->_fleet['fleet_end_planet'],
				$targetUser['id'],
				$chanceCreateMoon
			);
			\HiveNova\Core\FeatHooks::afterMoonCreated(
				(int) $this->_fleet['fleet_universe'],
				(int) $targetUser['id'],
				(int) $this->_fleet['fleet_owner']
			);

			if(Config::get($this->_fleet['fleet_universe'])->debris_moon == 1)
			{
				foreach($debrisResource as $elementID)
				{
					$planetDebris[$elementID]	= 0;
				}
			}
		}

		switch($combatResult['won'])
		{
			case "a":
				// Win
				$attackStatus	= 'wons';
				$defendStatus	= 'loos';
				$class			= array('raportWin', 'raportLose');
				break;
			case "r":
				// Lose
				$attackStatus	= 'loos';
				$defendStatus	= 'wons';
				$class			= array('raportLose', 'raportWin');
				break;
			case "w":
			default:
				// Draw
				$attackStatus	= 'draws';
				$defendStatus	= 'draws';
				$class			= array('raportDraw', 'raportDraw');
				break;
		}

		return array($reportInfo, $attackStatus, $defendStatus, $class, null, $planetDebris);
	}

	protected function shouldPersistDebris(): bool
	{
		return (int) $this->_fleet['fleet_owner'] !== 0;
	}

	protected function shouldUpdateAttackerStats(array $userAttack): bool
	{
		return array_keys($userAttack) !== [];
	}

	function ReturnEvent()
	{
		if ((int) $this->_fleet['fleet_owner'] === 0) {
			$this->KillFleet();
			return;
		}

		parent::ReturnEvent();
	}
}
