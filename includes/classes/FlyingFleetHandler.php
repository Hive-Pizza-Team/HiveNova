<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;

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

class FlyingFleetHandler
{
	protected $token;

	public static $missionObjPattern	= array(
		FLEET_MISSION_ATTACK		=> 'HiveNova\\Mission\\MissionCaseAttack',
		FLEET_MISSION_ACS			=> 'HiveNova\\Mission\\MissionCaseACS',
		FLEET_MISSION_TRANSPORT		=> 'HiveNova\\Mission\\MissionCaseTransport',
		FLEET_MISSION_STATION		=> 'HiveNova\\Mission\\MissionCaseStay',
		FLEET_MISSION_ALLY_STATION	=> 'HiveNova\\Mission\\MissionCaseStayAlly',
		FLEET_MISSION_SPY			=> 'HiveNova\\Mission\\MissionCaseSpy',
		FLEET_MISSION_COLONISE		=> 'HiveNova\\Mission\\MissionCaseColonisation',
		FLEET_MISSION_RECYCLE		=> 'HiveNova\\Mission\\MissionCaseRecycling',
		FLEET_MISSION_DESTROY		=> 'HiveNova\\Mission\\MissionCaseDestruction',
		FLEET_MISSION_MIP			=> 'HiveNova\\Mission\\MissionCaseMIP',
		FLEET_MISSION_DARKMATTER	=> 'HiveNova\\Mission\\MissionCaseFoundDM',
		FLEET_MISSION_EXPEDITION	=> 'HiveNova\\Mission\\MissionCaseExpedition',
		FLEET_MISSION_TRADE			=> 'HiveNova\\Mission\\MissionCaseTrade',
		FLEET_MISSION_TRANSFER		=> 'HiveNova\\Mission\\MissionCaseTransfer',
		FLEET_MISSION_SALVAGE		=> 'HiveNova\\Mission\\MissionCaseSalvage',
	);

	function setToken($token)
	{
		$this->token	= $token;
	}

	function run()
	{
		$db	= Database::get();

		$sql = 'SELECT %%FLEETS%%.*
		FROM %%FLEETS_EVENT%%
		INNER JOIN %%FLEETS%% ON fleetID = fleet_id
		WHERE `lock` = :token;';

		$fleetResult = $db->select($sql, array(
			':token'	=> $this->token
		));

		foreach($fleetResult as $fleetRow)
		{
			if(!isset(self::$missionObjPattern[$fleetRow['fleet_mission']])) {
				$sql = 'DELETE FROM %%FLEETS%% WHERE fleet_id = :fleetId;';

				$db->delete($sql, array(
					':fleetId'	=> $fleetRow['fleet_id']
			  	));

				continue;
			}

			$missionName	= self::$missionObjPattern[$fleetRow['fleet_mission']];

			/** @var \HiveNova\Mission\Mission $missionObj */
			$missionObj	= new $missionName($fleetRow);

			try {
				switch($fleetRow['fleet_mess'])
				{
					case 0:
						$missionObj->TargetEvent();
					break;
					case 1:
						$missionObj->ReturnEvent();
					break;
					case 2:
						$missionObj->EndStayEvent();
					break;
				}
			} catch (\Throwable $e) {
				error_log(sprintf(
					'FlyingFleetHandler: fleet %s mission %s: %s',
					$fleetRow['fleet_id'] ?? '?',
					$fleetRow['fleet_mission'] ?? '?',
					$e->getMessage()
				));
			}
		}
	}
}
