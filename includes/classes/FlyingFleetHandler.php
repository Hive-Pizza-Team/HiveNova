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
		1	=> 'HiveNova\\Mission\\MissionCaseAttack',
		2	=> 'HiveNova\\Mission\\MissionCaseACS',
		3	=> 'HiveNova\\Mission\\MissionCaseTransport',
		4	=> 'HiveNova\\Mission\\MissionCaseStay',
		5	=> 'HiveNova\\Mission\\MissionCaseStayAlly',
		6	=> 'HiveNova\\Mission\\MissionCaseSpy',
		7	=> 'HiveNova\\Mission\\MissionCaseColonisation',
		8	=> 'HiveNova\\Mission\\MissionCaseRecycling',
		9	=> 'HiveNova\\Mission\\MissionCaseDestruction',
		10	=> 'HiveNova\\Mission\\MissionCaseMIP',
		11	=> 'HiveNova\\Mission\\MissionCaseFoundDM',
		15	=> 'HiveNova\\Mission\\MissionCaseExpedition',
		16	=> 'HiveNova\\Mission\\MissionCaseTrade',
		17	=> 'HiveNova\\Mission\\MissionCaseTransfer',
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
		}
	}
}
