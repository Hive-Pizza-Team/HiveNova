<?php

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

$db = \HiveNova\Core\Database::get();

// One request can need TargetEvent → EndStayEvent → ReturnEvent when a player
// returns after fleet_end_stay / fleet_end_time are already past. Drain due
// events until none remain (or a safety cap) so Hold fleets are not left stuck.
$maxPasses = 5;
for ($pass = 0; $pass < $maxPasses; $pass++) {
	$token = getRandomString();
	$db->update(
		'UPDATE %%FLEETS_EVENT%% SET `lock` = :token WHERE `lock` IS NULL AND `time` <= :time;',
		[
			':time' => TIMESTAMP,
			':token' => $token,
		]
	);

	if ($db->rowCount() === 0) {
		break;
	}

	$fleetObj = new \HiveNova\Core\FlyingFleetHandler();
	$fleetObj->setToken($token);
	$failedFleetIds = $fleetObj->run();

	if ($failedFleetIds === []) {
		$db->update(
			'UPDATE %%FLEETS_EVENT%% SET `lock` = NULL WHERE `lock` = :token;',
			[':token' => $token]
		);
	} else {
		$placeholders = [];
		$params = [':token' => $token];
		foreach ($failedFleetIds as $index => $fleetId) {
			$key = ':failed'.$index;
			$placeholders[] = $key;
			$params[$key] = $fleetId;
		}
		$db->update(
			'UPDATE %%FLEETS_EVENT%% SET `lock` = NULL WHERE `lock` = :token AND fleetID NOT IN ('.implode(', ', $placeholders).')',
			$params
		);
	}
}
