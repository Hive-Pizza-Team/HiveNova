<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
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

class ShowIndexPage extends AbstractLoginPage
{
	function __construct() 
	{
		parent::__construct();
		$this->setWindow('light');
	}
	
	function show()
	{
		global $LNG;

		$referralID		= HTTP::_GP('ref', 0);
		if(!empty($referralID))
		{
			$this->redirectTo('index.php?page=register&referralID='.$referralID);
		}

		$universeSelect	= array();
		$universeStats	= array();

		$db = Database::get();

		foreach(array_reverse(Universe::availableUniverses()) as $uniId)
		{
			$uniConfig = Config::get($uniId);
			$universeSelect[$uniId]	= $uniConfig->uni_name.($uniConfig->game_disable == 0 ? $LNG['uni_closed'] : '');

			$sql = 'SELECT COUNT(*) as cnt FROM %%FLEETS%% WHERE fleet_universe = :uniId;';
			$fleetCount = $db->selectSingle($sql, array(':uniId' => $uniId), 'cnt');

			$sql = 'SELECT MIN(register_time) as started FROM %%USERS%% WHERE universe = :uniId AND register_time > 0;';
			$startedAt = (int) $db->selectSingle($sql, array(':uniId' => $uniId), 'started');

			$sql = 'SELECT COUNT(*) as cnt FROM (
				SELECT galaxy, `system`
				FROM %%PLANETS%%
				WHERE universe = :uniId
					AND planet_type = 1
					AND galaxy >= 1 AND galaxy <= :maxGalaxy
					AND `system` >= 1 AND `system` <= :maxSystem
				GROUP BY galaxy, `system`
			) AS occupied_systems;';
			$occupiedSystems = (int) $db->selectSingle($sql, array(
				':uniId'      => $uniId,
				':maxGalaxy'  => (int) $uniConfig->max_galaxy,
				':maxSystem'  => (int) $uniConfig->max_system,
			), 'cnt');

			$totalSystems = calculate_universe_system_capacity(
				(int) $uniConfig->max_galaxy,
				(int) $uniConfig->max_system
			);
			$vacantSystems = calculate_universe_vacant_systems($occupiedSystems, $totalSystems);

			$universeStats[$uniId] = array(
				'name'                => $uniConfig->uni_name,
				'open'                => (int) $uniConfig->game_disable === 1,
				'reg_open'            => (int) $uniConfig->reg_closed === 0,
				'game_speed'          => $uniConfig->game_speed / 2500,
				'fleet_speed'         => $uniConfig->fleet_speed / 2500,
				'resource_multiplier' => (int) $uniConfig->resource_multiplier,
				'galaxy_size'         => sprintf($LNG['uni_info_galaxy_format'], $uniConfig->max_galaxy, $uniConfig->max_system),
				'age'                 => format_universe_age_label($startedAt),
				'vacancy_pct'         => universe_vacancy_percent($vacantSystems, $totalSystems),
				'vacancy_level'       => universe_vacancy_level($vacantSystems, $totalSystems),
				'vacancy_label'       => format_universe_vacancy_label($vacantSystems, $totalSystems),
				'players'             => (int) $uniConfig->users_amount,
				'fleets'              => (int) $fleetCount,
			);
		}

		$Code	= HTTP::_GP('code', 0);
		$loginErrorMessage	= '';
		if(isset($LNG['login_error_'.$Code]))
		{
			$loginErrorMessage	= $LNG['login_error_'.$Code];
		}

		$sql = "SELECT capaktiv, cappublic, capprivate FROM uni1_config";
		$verkey = $db->selectSingle($sql);

		$config				= Config::get();
		$this->assign(array(
			'universeSelect'		=> $universeSelect,
			'defaultUniverse'		=> $this->getDefaultUniverseId(),
			'universeStats'			=> $universeStats,
			'code'					=> $loginErrorMessage,
			'verkey'			=> $verkey,
			'descHeader'			=> sprintf($LNG['loginWelcome'], $config->game_name),
			'descText'				=> sprintf($LNG['loginServerDesc'], $config->game_name),
            'gameInformations'      => array_filter(explode("\n", (string) $LNG['gameInformations']), 'strlen'),
			'loginInfo'				=> sprintf($LNG['loginInfo'], '<a href="index.php?page=rules">'.$LNG['menu_rules'].'</a>')
		));

		if ($loginErrorMessage) {
			AbstractLoginPage::printMessage($loginErrorMessage, array(array(
				'label'	=> $LNG['sys_back'],
				'url'	=> 'index.php')), array('index.php', 5), true);
		}
		
		$this->display('page.index.default.tpl');
	}
}