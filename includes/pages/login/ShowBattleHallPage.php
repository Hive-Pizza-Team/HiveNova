<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\BattleHallService;
use HiveNova\Core\Universe;

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

class ShowBattleHallPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
	}
	
	function show() 
	{
		global $LNG;

		$missingName = (string) ($LNG['tkb_deleted_player'] ?? BattleHallService::MISSING_NAME);
		$hallRaw = (new BattleHallService())->listTopBattles((int) Universe::current(), 100, $missingName);

		$hallList	= array();
		foreach($hallRaw as $hallRow) {
			$hallList[]	= array(
				'result'	=> $hallRow['result'],
				'time'		=> _date($LNG['php_tdformat'], $hallRow['time']),
				'units'		=> $hallRow['units'],
				'rid'		=> $hallRow['rid'],
				'attacker'	=> $hallRow['attacker'],
				'defender'	=> $hallRow['defender'],
			);
		}

		$universeSelect	= $this->getUniverseSelector();
		
		$this->assign(array(
			'universeSelect'	=> $universeSelect,
			'hallList'			=> $hallList,
		));
		$this->display('page.battleHall.default.tpl');
	}
}