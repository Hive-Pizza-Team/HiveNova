<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Database;
use HiveNova\Core\Universe;
use HiveNova\Core\Cronjob;

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


class ShowRecordsPage extends AbstractGamePage
{
    public static $requireModule = MODULE_RECORDS;

	function __construct() 
	{
		parent::__construct();
	}
	
	function show()
	{
		global $USER, $LNG, $reslist;

		$db = Database::get();

		$sql = "SELECT elementID, level, userID, username, r.universe
		FROM %%USERS%% u
		INNER JOIN %%RECORDS%% r ON r.userID = u.id
		WHERE r.universe = :universe;";

		$recordResult = $db->select($sql, array(
			':universe'	=> Universe::current()
		));

		$defenseIds = array_fill_keys(array_merge($reslist['defense'], $reslist['missile']), true);
		$fleetIds = array_fill_keys($reslist['fleet'], true);
		$researchIds = array_fill_keys($reslist['tech'], true);
		$buildIds = array_fill_keys($reslist['build'], true);
		$officerIds = array_fill_keys($reslist['officier'], true);

		$defenseList	= array();
		$fleetList		= array();
		$researchList	= array();
		$buildList		= array();
		$officerList	= array();
		
		foreach($recordResult as $recordRow) {
			$elementId = $recordRow['elementID'];
			if (isset($defenseIds[$elementId])) {
				$defenseList[$elementId][] = $recordRow;
			} elseif (isset($fleetIds[$elementId])) {
				$fleetList[$elementId][] = $recordRow;
			} elseif (isset($researchIds[$elementId])) {
				$researchList[$elementId][] = $recordRow;
			} elseif (isset($buildIds[$elementId])) {
				$buildList[$elementId][] = $recordRow;
			} elseif (isset($officerIds[$elementId])) {
				$officerList[$elementId][] = $recordRow;
			}
		}

		$this->assign(array(
			'defenseList'	=> $defenseList,
			'fleetList'		=> $fleetList,
			'researchList'	=> $researchList,
			'buildList'		=> $buildList,
			'officerList'	=> $officerList,
			'update'		=> _date($LNG['php_tdformat'], Cronjob::getLastExecutionTime('statistic'), $USER['timezone']),
		));
		
		$this->display('page.records.default.tpl');
	}
}
