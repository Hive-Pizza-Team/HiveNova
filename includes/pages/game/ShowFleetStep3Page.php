<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\FleetDispatchService;
use HiveNova\Repository\UserRepository;

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

class ShowFleetStep3Page extends AbstractGamePage
{
	public static $requireModule = MODULE_FLEET_TABLE;

	function __construct()
	{
		parent::__construct();
	}

	public function show()
	{
		global $USER, $PLANET, $resource, $LNG;

		if (IsVacationMode($USER)) {
			FleetFunctions::GotoFleetPage(0);
		}

		$targetMission        = HTTP::_GP('mission', 3);
		$TransportMetal       = max(0, round(HTTP::_GP('metal', 0.0)));
		$TransportCrystal     = max(0, round(HTTP::_GP('crystal', 0.0)));
		$TransportDeuterium   = max(0, round(HTTP::_GP('deuterium', 0.0)));
		$WantedResourceType   = HTTP::_GP('resEx', 0);
		$WantedResourceAmount = max(0, round(HTTP::_GP('exchange', 0.0)));
		$markettype           = HTTP::_GP('markettype', 0);
		$visibility           = HTTP::_GP('visibility', 0);
		$maxFlightTime        = HTTP::_GP('maxFlightTime', 0);
		$stayTime             = HTTP::_GP('staytime', 0);
		$token                = HTTP::_GP('token', '');

		$config = Config::get();

		if (!isset($_SESSION['fleet'][$token])) {
			FleetFunctions::GotoFleetPage(1);
		}

		if ($_SESSION['fleet'][$token]['time'] < TIMESTAMP - 600) {
			unset($_SESSION['fleet'][$token]);
			FleetFunctions::GotoFleetPage(0);
		}

		$formData = $_SESSION['fleet'][$token];
		unset($_SESSION['fleet'][$token]);

		$requiredKeys = array_flip([
			'distance', 'targetGalaxy', 'targetSystem', 'targetPlanet', 'targetType',
			'fleetGroup', 'fleet', 'fleetRoom', 'fleetSpeed', 'ownPlanet'
		]);
		if (count(array_intersect_key($requiredKeys, $formData)) !== count($requiredKeys)) {
			FleetFunctions::GotoFleetPage(0);
		}

		$distance     = $formData['distance'];
		$targetGalaxy = $formData['targetGalaxy'];
		$targetSystem = $formData['targetSystem'];
		$targetPlanet = $formData['targetPlanet'];
		$targetType   = $formData['targetType'];
		$fleetGroup   = $formData['fleetGroup'];
		$fleetArray   = $formData['fleet'];
		$fleetStorage = $formData['fleetRoom'];
		$fleetSpeed   = $formData['fleetSpeed'];
		$ownPlanet    = $formData['ownPlanet'];

		if ($ownPlanet != $PLANET['id']) {
			$this->printMessage($LNG['fl_own_planet_error'], array(array(
				'label' => $LNG['sys_back'],
				'url'   => 'game.php?page=fleetStep1'
			)));
		}

		if ($targetMission != 2) {
			$fleetGroup = 0;
		}

		// --- Calculate metrics early (needed by validateTarget for consumption/storage) ---
		$metrics      = FleetDispatchService::calculateMetrics($fleetArray, $distance, $fleetSpeed, $USER);
		$duration     = $metrics['duration'];
		$consumption  = $metrics['consumption'];
		$fleetMaxSpeed = $metrics['fleetMaxSpeed'];

		$fleetStorageAfterFuel = floor($fleetStorage - $consumption);

		// --- Validate target coords, resource constraints, ship availability ---
		try {
			FleetDispatchService::validateTarget([
				'targetMission'       => $targetMission,
				'targetGalaxy'        => $targetGalaxy,
				'targetSystem'        => $targetSystem,
				'targetPlanet'        => $targetPlanet,
				'targetType'          => $targetType,
				'TransportMetal'      => $TransportMetal,
				'TransportCrystal'    => $TransportCrystal,
				'TransportDeuterium'  => $TransportDeuterium,
				'WantedResourceAmount' => $WantedResourceAmount,
				'markettype'          => $markettype,
				'fleetArray'          => $fleetArray,
				'fleetStorage'        => $fleetStorageAfterFuel,
				'consumption'         => $consumption,
			], $USER, $PLANET);
		} catch (\RuntimeException $e) {
			// Determine back URL based on which check likely failed (resource/fleet errors go to fleetTable)
			$this->printMessage($e->getMessage(), array(array(
				'label' => $LNG['sys_back'],
				'url'   => 'game.php?page=fleetStep1'
			)));
		}

		// --- Validate fleet slots ---
		try {
			FleetDispatchService::validateSlots($USER, $fleetGroup);
		} catch (\RuntimeException $e) {
			$this->printMessage($e->getMessage(), array(array(
				'label' => $LNG['sys_back'],
				'url'   => 'game.php?page=fleetTable'
			)));
		}

		$ACSTime = 0;
		$db = Database::get();

		if (!empty($fleetGroup)) {
			$sql = "SELECT ankunft FROM %%USERS_ACS%% INNER JOIN %%AKS%% ON id = acsID
			WHERE acsID = :acsID AND :maxFleets > (SELECT COUNT(*) FROM %%FLEETS%% WHERE fleet_group = :acsID);";
			$ACSTime = $db->selectSingle($sql, array(
				':acsID'     => $fleetGroup,
				':maxFleets' => $config->max_fleets_per_acs,
			), 'ankunft');

			if (empty($ACSTime)) {
				$fleetGroup    = 0;
				$targetMission = 1;
			}
		}

		$sql = "SELECT id, id_owner, der_metal, der_crystal, destruyed, ally_deposit FROM %%PLANETS%% WHERE universe = :universe AND galaxy = :targetGalaxy AND `system` = :targetSystem AND planet = :targetPlanet AND planet_type = :targetType;";
		$targetPlanetData = $db->selectSingle($sql, array(
			':universe'     => Universe::current(),
			':targetGalaxy' => $targetGalaxy,
			':targetSystem' => $targetSystem,
			':targetPlanet' => $targetPlanet,
			':targetType'   => ($targetType == 2 ? 1 : $targetType),
		));

		// Determine target player data
		if ($targetMission == 7 || $targetMission == 15 || $targetMission == 16) {
			$targetPlayerData = array(
				'id'          => 0,
				'onlinetime'  => TIMESTAMP,
				'ally_id'     => 0,
				'urlaubs_modus' => 0,
				'authattack'  => 0,
				'total_points' => 0,
			);
		} elseif (isset($targetPlanetData['id_owner']) && $targetPlanetData['id_owner'] == $USER['id']) {
			$targetPlayerData = $USER;
		} elseif (!empty($targetPlanetData['id_owner'])) {
			$targetPlayerData = UserRepository::getUserWithStats((int) $targetPlanetData['id_owner']);
		} else {
			$targetPlayerData = array();
		}

		// Build MisInfo for available missions calculation
		$MisInfo             = array();
		$MisInfo['galaxy']   = $targetGalaxy;
		$MisInfo['system']   = $targetSystem;
		$MisInfo['planet']   = $targetPlanet;
		$MisInfo['planettype'] = $targetType;
		$MisInfo['IsAKS']    = $fleetGroup;
		$MisInfo['Ship']     = $fleetArray;

		$availableMissions = FleetFunctions::GetFleetMissions($USER, $MisInfo, $targetPlanetData);

		// For colonize / expedition / market, override targetPlanetData to synthetic after mission check
		$targetPlanetDataForMission = $targetPlanetData;
		if ($targetMission == 7 || $targetMission == 15 || $targetMission == 16) {
			$targetPlanetDataForMission = array('id' => 0, 'id_owner' => 0, 'planettype' => 1);
		}

		// --- Validate mission feasibility ---
		try {
			FleetDispatchService::validateMission(
				$targetPlanetData,
				$targetPlayerData,
				$targetMission,
				$USER,
				[
					'fleetArray'        => $fleetArray,
					'fleetGroup'        => $fleetGroup,
					'targetType'        => $targetType,
					'stayTime'          => $stayTime,
					'availableMissions' => $availableMissions,
				],
				$config
			);
		} catch (\RuntimeException $e) {
			$this->printMessage($e->getMessage(), array(array(
				'label' => $LNG['sys_back'],
				'url'   => 'game.php?page=fleetStep1'
			)));
		}

		// Resolve stay duration
		$StayDuration = 0;
		if ($targetMission == 5 || $targetMission == 11 || $targetMission == 15 || $targetMission == 16) {
			$StayDuration = round($availableMissions['StayBlock'][$stayTime] * 3600, 0);
		}

		// Build fleet resource payload
		$fleetResource = array(
			901 => min($TransportMetal,     floor($PLANET[$resource[901]])),
			902 => min($TransportCrystal,   floor($PLANET[$resource[902]])),
			903 => min($TransportDeuterium, floor($PLANET[$resource[903]] - $consumption)),
		);

		// Compute timing
		$fleetStartTime  = $duration + TIMESTAMP;
		$timeDifference  = round(max(0, $fleetStartTime - $ACSTime));

		if ($fleetGroup != 0) {
			if ($timeDifference != 0) {
				FleetFunctions::setACSTime($timeDifference, $fleetGroup);
			} else {
				$fleetStartTime = $ACSTime;
			}
		}

		$fleetStayTime = $fleetStartTime + $StayDuration;
		$fleetEndTime  = $fleetStayTime + $duration;

		// --- Dispatch ---
		try {
			$fleet_id = FleetDispatchService::dispatch([
				'fleetArray'          => $fleetArray,
				'targetMission'       => $targetMission,
				'USER'                => $USER,
				'targetPlanetData'    => $targetPlanetDataForMission,
				'targetGalaxy'        => $targetGalaxy,
				'targetSystem'        => $targetSystem,
				'targetPlanet'        => $targetPlanet,
				'targetType'          => $targetType,
				'fleetResource'       => $fleetResource,
				'fleetStartTime'      => $fleetStartTime,
				'fleetStayTime'       => $fleetStayTime,
				'fleetEndTime'        => $fleetEndTime,
				'fleetGroup'          => $fleetGroup,
				'consumption'         => $consumption,
				'markettype'          => $markettype,
				'WantedResourceType'  => $WantedResourceType,
				'WantedResourceAmount' => $WantedResourceAmount,
				'maxFlightTime'       => $maxFlightTime,
				'visibility'          => $visibility,
			], $PLANET);
		} catch (\RuntimeException $e) {
			$this->printMessage($e->getMessage(), array(array(
				'label' => $LNG['sys_back'],
				'url'   => 'game.php?page=fleetTable'
			)));
		}

		$this->tplObj->gotoside('game.php?page=fleetTable');
		$this->assign(array(
			'targetMission'  => $targetMission,
			'distance'       => $distance,
			'consumption'    => $consumption,
			'from'           => $PLANET['galaxy'] . ":" . $PLANET['system'] . ":" . $PLANET['planet'],
			'destination'    => $targetGalaxy . ":" . $targetSystem . ":" . $targetPlanet,
			'fleetStartTime' => _date($LNG['php_tdformat'], $fleetStartTime, $USER['timezone']),
			'fleetEndTime'   => _date($LNG['php_tdformat'], $fleetEndTime, $USER['timezone']),
			'MaxFleetSpeed'  => $fleetMaxSpeed,
			'FleetList'      => $fleetArray,
		));

		$this->display('page.fleetStep3.default.tpl');
	}
}
