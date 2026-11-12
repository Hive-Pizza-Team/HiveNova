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
		$failedFleetIds = array();

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
			$fleetId		= (int) $fleetRow['fleet_id'];

			try {
				// One locked row can advance through multiple due stages in a
				// single request (e.g. overdue EndStay then Return) so Overview
				// briefing/slots match after one reload instead of two.
				$current = $fleetRow;
				for ($step = 0; $step < 3 && is_array($current); $step++) {
					$beforeMess = (int) $current['fleet_mess'];
					$beforeStart = (int) ($current['fleet_start_time'] ?? 0);
					$beforeStay = (int) ($current['fleet_end_stay'] ?? 0);
					$beforeEnd = (int) ($current['fleet_end_time'] ?? 0);

					/** @var \HiveNova\Mission\Mission $missionObj */
					$missionObj	= new $missionName($current);

					switch ((int) $current['fleet_mess']) {
						case FLEET_OUTWARD:
							$missionObj->TargetEvent();
							break;
						case FLEET_RETURN:
							$missionObj->ReturnEvent();
							break;
						case FLEET_HOLD:
							$missionObj->EndStayEvent();
							break;
						default:
							break 2;
					}

					$current = $db->selectSingle(
						'SELECT %%FLEETS%%.*
						FROM %%FLEETS%%
						INNER JOIN %%FLEETS_EVENT%% ON fleetID = fleet_id
						WHERE fleet_id = :fleetId
							AND `lock` = :token
							AND `time` <= :time;',
						array(
							':fleetId'	=> $fleetId,
							':token'	=> $this->token,
							':time'		=> TIMESTAMP,
						)
					);

					// Bail if the mission step did not advance mess/times — avoids
					// re-running the same stage on a stuck/bad locked row.
					if (is_array($current)
						&& (int) $current['fleet_mess'] === $beforeMess
						&& (int) ($current['fleet_start_time'] ?? 0) === $beforeStart
						&& (int) ($current['fleet_end_stay'] ?? 0) === $beforeStay
						&& (int) ($current['fleet_end_time'] ?? 0) === $beforeEnd
					) {
						break;
					}
				}
			} catch (\Throwable $e) {
				error_log(sprintf(
					'FlyingFleetHandler: fleet %s mission %s: %s',
					$fleetRow['fleet_id'] ?? '?',
					$fleetRow['fleet_mission'] ?? '?',
					$e->getMessage()
				));
				$failedFleetIds[] = (int) $fleetRow['fleet_id'];
			}
		}

		return $failedFleetIds;
	}
}
