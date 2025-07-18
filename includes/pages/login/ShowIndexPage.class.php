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
		
		foreach(Universe::availableUniverses() as $uniId)
		{
			$config = Config::get($uniId);
			$universeSelect[$uniId]	= $config->uni_name.($config->game_disable == 0 ? $LNG['uni_closed'] : '');
		}
		
		$Code	= HTTP::_GP('code', 0);
		$loginErrorMessage	= '';
		if(isset($LNG['login_error_'.$Code]))
		{
			$loginErrorMessage	= $LNG['login_error_'.$Code];
		}

		$db = Database::get();
		$sql = "SELECT capaktiv, cappublic, capprivate FROM uni1_config";
		$verkey = $db->selectSingle($sql);

		$config				= Config::get();
		$this->assign(array(
			'universeSelect'		=> $universeSelect,
			'code'					=> $loginErrorMessage,
			'verkey'			=> $verkey,
			'descHeader'			=> sprintf($LNG['loginWelcome'], $config->game_name),
			'descText'				=> sprintf($LNG['loginServerDesc'], $config->game_name),
            'gameInformations'      => explode("\n", $LNG['gameInformations']),
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