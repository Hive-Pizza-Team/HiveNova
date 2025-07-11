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

class ShowSettingsPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
	}
	
	public function show()
	{
		global $USER, $LNG;
		if($USER['urlaubs_modus'] == 1)
		{
			$this->assign(array(
				'vacationUntil'			=> _date($LNG['php_tdformat'], $USER['urlaubs_until'], $USER['timezone']),
				'delete'				=> $USER['db_deaktjava'],
				'canVacationDisbaled'	=> $USER['urlaubs_until'] < TIMESTAMP,
			));
			
			$this->display('page.settings.vacation.tpl');
		}
		else
		{
			$this->assign(array(
				'Selectors'			=> array(
					'timezones' => get_timezone_selector(), 
					'Sort' => array(
						0 => $LNG['op_sort_normal'], 
						1 => $LNG['op_sort_koords'],
						2 => $LNG['op_sort_abc']), 
					'SortUpDown' => array(
						0 => $LNG['op_sort_up'], 
						1 => $LNG['op_sort_down']
					), 
					'Skins' => Theme::getAvalibleSkins(), 
					'lang' => $LNG->getAllowedLangs(false)
					),
				'adminProtection'	=> $USER['authattack'],	
				'userAuthlevel'		=> $USER['authlevel'],
				'changeNickTime'	=> ($USER['uctime'] + USERNAME_CHANGETIME) - TIMESTAMP,
				'username'			=> $USER['username'],
				'email'				=> $USER['email'],
				'permaEmail'		=> $USER['email_2'],
				'hiveAccount'		=> $USER['hive_account'],
				'isHiveKeychainAct' => (substr_compare($USER['email'], '@hive.blog', -strlen('@hive.blog')) === 0),
				'userLang'			=> $USER['lang'],
				'theme'				=> $USER['dpath'],
				'planetSort'		=> $USER['planet_sort'],
				'planetOrder'		=> $USER['planet_sort_order'],
				'spycount'			=> $USER['spio_anz'],
				'fleetActions'		=> $USER['settings_fleetactions'],
				'timezone'			=> $USER['timezone'],
				'delete'			=> $USER['db_deaktjava'],
				'queueMessages'		=> $USER['hof'],
				'spyMessagesMode'	=> $USER['spyMessagesMode'],
				'galaxySpy' 		=> $USER['settings_esp'],
				'galaxyBuddyList' 	=> $USER['settings_bud'],
				'galaxyMissle' 		=> $USER['settings_mis'],
				'galaxyMessage' 	=> $USER['settings_wri'],
				'blockPM' 			=> $USER['settings_blockPM'],
				'userid'		 	=> $USER['id'],
				'ref_active'		=> Config::get()->ref_active,
				'SELF_URL'          => PROTOCOL.HTTP_HOST.HTTP_ROOT
			));
			
			$this->display('page.settings.default.tpl');
		}
	}
	
	private function CheckVMode()
	{
		global $USER, $PLANET;

		if(!empty($USER['b_tech']) || !empty($PLANET['b_building']) || !empty($PLANET['b_hangar']))
			return false;

		$db = Database::get();

		$sql = "SELECT COUNT(*) as state FROM %%FLEETS%% WHERE `fleet_owner` = :userID;";
		$fleets = $db->selectSingle($sql, array(
			':userID'	=> $USER['id']
		), 'state');

		if($fleets != 0)
			return false;

		$sql = "SELECT * FROM %%PLANETS%% WHERE id_owner = :userID AND id != :planetID AND destruyed = 0;";
		$query = $db->select($sql, array(
			':userID'	=> $USER['id'],
			':planetID'	=> $PLANET['id']
		));

		foreach($query as $CPLANET)
		{
			list($USER, $CPLANET)	= $this->ecoObj->CalcResource($USER, $CPLANET, true);
		
			if(!empty($CPLANET['b_building']) || !empty($CPLANET['b_hangar']))
				return false;
			
			unset($CPLANET);
		}

		return true;
	}
	
	public function send()
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
	    }

		global $USER;
		if($USER['urlaubs_modus'] == 1) {
			$this->sendVacation();
		} else {
			$this->sendDefault();
		}
	}
	
	private function sendVacation() 
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
	    }

		global $USER, $LNG; $PLANET;
		
		$delete		= HTTP::_GP('delete', 0);
		$vacation	= HTTP::_GP('vacation', 0);
		
		$db = Database::get();
		
		if($vacation == 1 && $USER['urlaubs_until'] <= TIMESTAMP) {
			$sql = "UPDATE %%USERS%% SET
						urlaubs_modus = '0',
						urlaubs_until = '0'
						WHERE id = :userID;";
			$db->update($sql, array(
				':userID'	=> $USER['id']
			));

			$sql = "UPDATE %%PLANETS%% SET
						last_update = :timestamp,
						energy_used = '10',
						energy = '10',
						metal_mine_porcent = '10',
						crystal_mine_porcent = '10',
						deuterium_sintetizer_porcent = '10',
						solar_plant_porcent = '10',
						fusion_plant_porcent = '10',
						solar_satelit_porcent = '10'
						WHERE id_owner = :userID;";
			$db->update($sql, array(
				':userID'		=> $USER['id'],
				':timestamp'	=> TIMESTAMP
			));
			
			$PLANET['last_update'] = TIMESTAMP;
		}
		
		if($delete == 1) {
			$sql	= "UPDATE %%USERS%% SET db_deaktjava = :timestamp WHERE id = :userID;";
			$db->update($sql, array(
				':userID'		=> $USER['id'],
				':timestamp'	=> TIMESTAMP
			));
		} else {
			$sql	= "UPDATE %%USERS%% SET db_deaktjava = 0 WHERE id = :userID;";
			$db->update($sql, array(
				':userID'	=> $USER['id'],
			));
		}
		
		$this->printMessage($LNG['op_options_changed'], array(array(
			'label'	=> $LNG['sys_forward'],
			'url'	=> 'game.php?page=settings'
		)));
	}
	
	private function sendDefault()
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
	    }

		global $USER, $LNG, $THEME;
		
		$adminprotection	= HTTP::_GP('adminprotection', 0);
		
		$username			= HTTP::_GP('username', $USER['username'], UTF8_SUPPORT);
		$password			= HTTP::_GP('password', '');
		
		$newpassword		= HTTP::_GP('newpassword', '');
		$newpassword2		= HTTP::_GP('newpassword2', '');
		
		$email				= HTTP::_GP('email', $USER['email']);

		$hiveAccount		= HTTP::_GP('hiveAccount', '');
		$hivesign           = HTTP::_GP('hivesign', '');
		
		$timezone			= HTTP::_GP('timezone', '');	
		$language			= HTTP::_GP('language', '');	
		
		$planetSort			= HTTP::_GP('planetSort', 0);	
		$planetOrder		= HTTP::_GP('planetOrder', 0);
				
		$theme				= HTTP::_GP('theme', $THEME->getThemeName());	
	
		$queueMessages		= HTTP::_GP('queueMessages', 0);	
		$spyMessagesMode	= HTTP::_GP('spyMessagesMode', 0);

		$spycount			= HTTP::_GP('spycount', 1.0);	
		$fleetactions		= HTTP::_GP('fleetactions', 5);	
		
		$galaxySpy			= HTTP::_GP('galaxySpy', 0);	
		$galaxyMessage		= HTTP::_GP('galaxyMessage', 0);	
		$galaxyBuddyList	= HTTP::_GP('galaxyBuddyList', 0);	
		$galaxyMissle		= HTTP::_GP('galaxyMissle', 0);
		$blockPM			= HTTP::_GP('blockPM', 0);
		
		$vacation			= HTTP::_GP('vacation', 0);	
		$delete				= HTTP::_GP('delete', 0);
		
		// Vertify
		
		$adminprotection	= ($adminprotection == 1 && $USER['authlevel'] != AUTH_USR) ? $USER['authlevel'] : 0;
		
		$spycount			= min(max(round($spycount), 1), 4294967295);
		$fleetactions		= min(max($fleetactions, 1), 99);
		
		$language			= array_key_exists($language, $LNG->getAllowedLangs(false)) ? $language : $LNG->getLanguage();		
		$theme				= array_key_exists($theme, Theme::getAvalibleSkins()) ? $theme : $THEME->getThemeName();
		
		$db = Database::get();
		
		if (!empty($username) && $USER['username'] != $username)
		{
			if (!PlayerUtil::isNameValid($username))
			{
				$this->printMessage($LNG['op_user_name_no_alphanumeric'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			}
			elseif($USER['uctime'] >= TIMESTAMP - USERNAME_CHANGETIME)
			{
				$this->printMessage($LNG['op_change_name_pro_week'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			}
			else
			{
				$sql = "SELECT
					(SELECT COUNT(*) FROM %%USERS%% WHERE universe = :universe AND username = :username AND id != :userid) +
					(SELECT COUNT(*) FROM %%USERS_VALID%% WHERE universe = :universe AND username = :username) +
					(SELECT COUNT(*) FROM %%USERS%% WHERE universe = :universe AND hive_account = :username AND id != :userid)
				AS count";
				$Count = $db->selectSingle($sql, array(
					':universe'	=> Universe::current(),
					':username'	=> $username,
					':userid' => $USER['id']
				), 'count');

				if (!empty($Count)) {
					$this->printMessage(sprintf($LNG['op_change_name_exist'], $username), array(array(
						'label'	=> $LNG['sys_back'],
						'url'	=> 'game.php?page=settings'
					)));
				} else {
					$sql = "UPDATE %%USERS%% SET username = :username, uctime = :timestamp WHERE id = :userID;";
					$db->update($sql, array(
						':username'	=> $username,
						':userID'	=> $USER['id'],
						':timestamp'=> TIMESTAMP
					));

					Session::load()->delete();
				}
			}
		}
		
		if (!empty($newpassword) && !empty($password) && password_verify($password, $USER['password']) && $newpassword == $newpassword2)
		{
			$newpass 	 = PlayerUtil::cryptPassword($newpassword);
			$sql = "UPDATE %%USERS%% SET password = :newpass WHERE id = :userID;";
			$db->update($sql, array(
				':newpass'	=> $newpass,
				':userID'	=> $USER['id']
			));
			Session::load()->delete();
		}

		if (!empty($email) && $email != $USER['email'])
		{
			if (!password_verify($password, $USER['password'])) {
				$this->printMessage($LNG['op_need_pass_mail'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			}
			elseif(!ValidateAddress($email))
			{
				$this->printMessage($LNG['op_not_vaild_mail'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			}
			else
			{
				$sql = "SELECT
							(SELECT COUNT(*) FROM %%USERS%% WHERE id != :userID AND universe = :universe AND (email = :email OR email_2 = :email)) +
							(SELECT COUNT(*) FROM %%USERS_VALID%% WHERE universe = :universe AND email = :email)
						as count";
				$Count = $db->selectSingle($sql, array(
					':universe'	=> Universe::current(),
					':userID'	=> $USER['id'],
					':email'	=> $email
				), 'count');

				if (!empty($Count)) {
					$this->printMessage(sprintf($LNG['op_change_mail_exist'], $email), array(array(
						'label'	=> $LNG['sys_back'],
						'url'	=> 'game.php?page=settings'
					)));
				} else {
					$sql	= "UPDATE %%USERS%% SET email = :email, setmail = :time WHERE id = :userID;";
					$db->update($sql, array(
						':email'	=> $email,
						':time'		=> (TIMESTAMP + 604800),
						':userID'	=> $USER['id']
					));
				}
			}
		}		
			
		
		if ($vacation == 1)
		{
			if(!$this->CheckVMode())
			{
				$this->printMessage($LNG['op_cant_activate_vacation_mode'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			}
			else
			{
				$sql = "UPDATE %%USERS%% SET urlaubs_modus = '1', urlaubs_until = :time WHERE id = :userID";
				$db->update($sql, array(
					':userID'	=> $USER['id'],
					':time'		=> (TIMESTAMP + Config::get()->vmode_min_time),
				));

				$sql = "UPDATE %%PLANETS%% SET energy_used = '0', energy = '0', metal_mine_porcent = '0', crystal_mine_porcent = '0', deuterium_sintetizer_porcent = '0', solar_plant_porcent = '0', fusion_plant_porcent = '0', solar_satelit_porcent = '0', metal_perhour = '0', crystal_perhour = '0', deuterium_perhour = '0' WHERE id_owner = :userID;";
				$db->update($sql, array(
					':userID'	=> $USER['id'],
				));
			}
		}

		if($delete == 1) {
			$sql	= "UPDATE %%USERS%% SET db_deaktjava = :timestamp WHERE id = :userID;";
			$db->update($sql, array(
				':userID'	=> $USER['id'],
				':timestamp'	=> TIMESTAMP
			));
		} else {
			$sql	= "UPDATE %%USERS%% SET db_deaktjava = 0 WHERE id = :userID;";
			$db->update($sql, array(
				':userID'	=> $USER['id'],
			));
		}

		if(!empty($hivesign) && !empty($hiveAccount) && $USER['hive_account'] != $hiveAccount) {
			// validate signature before saving hive account in DB
			if (!PlayerUtil::isHiveAccountValid($hiveAccount))
			{
				$this->printMessage($LNG['op_user_name_no_alphanumeric'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			} else if (!PlayerUtil::isHiveSignValid($hiveAccount, $hivesign)) {
				$this->printMessage($LNG['op_user_name_no_alphanumeric'], array(array(
					'label'	=> $LNG['sys_back'],
					'url'	=> 'game.php?page=settings'
				)));
			} else {

				// check if hiveaccount is already linked to a user
				$db = Database::get();
				$sql = "SELECT COUNT(*) as state FROM %%USERS%% WHERE `hive_account` = :hiveAccount;";
				$linkedAccounts = $db->selectSingle($sql, array(
					':hiveAccount'	=> $hiveAccount
				), 'state');
		
				if($linkedAccounts != 0) {
					$this->printMessage($LNG['op_user_name_no_alphanumeric'], array(array(
						'label'	=> $LNG['sys_back'],
						'url'	=> 'game.php?page=settings'
					)));
				} else {
					$sql	= "UPDATE %%USERS%% SET hive_account = :hiveaccount WHERE id = :userID;";
					$db->update($sql, array(
						':hiveaccount'  => $hiveAccount,
						':userID'       => $USER['id']
					));
				}
			}
		}

		$sql =  "UPDATE %%USERS%% SET
		dpath					= :theme,
		timezone				= :timezone,
		planet_sort				= :planetSort,
		planet_sort_order		= :planetOrder,
		spio_anz				= :spyCount,
		settings_fleetactions	= :fleetActions,
		settings_esp			= :galaxySpy,
		settings_wri			= :galaxyMessage,
		settings_bud			= :galaxyBuddyList,
		settings_mis			= :galaxyMissle,
		settings_blockPM		= :blockPM,
		authattack				= :adminProtection,
		lang					= :language,
		hof						= :queueMessages,
		spyMessagesMode			= :spyMessagesMode
		WHERE id = :userID;";
		$db->update($sql, array(
			':theme'			=> $theme,
			':timezone'			=> $timezone,
			':planetSort'		=> $planetSort,
			':planetOrder'		=> $planetOrder,
			':spyCount'			=> $spycount,
			':fleetActions'		=> $fleetactions,
			':galaxySpy'		=> $galaxySpy,
			':galaxyMessage'	=> $galaxyMessage,
			':galaxyBuddyList'	=> $galaxyBuddyList,
			':galaxyMissle'		=> $galaxyMissle,
			':blockPM'			=> $blockPM,
			':adminProtection'	=> $adminprotection,
			':language'			=> $language,
			':queueMessages'	=> $queueMessages,
			':spyMessagesMode'	=> $spyMessagesMode,
			':userID'			=> $USER['id']
		));
		
		$this->printMessage($LNG['op_options_changed'], array(array(
			'label'	=> $LNG['sys_forward'],
			'url'	=> 'game.php?page=settings'
		)));
	}
}
