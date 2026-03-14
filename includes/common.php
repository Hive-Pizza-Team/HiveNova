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

use HiveNova\Core\Cache;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseBC;
use HiveNova\Core\HTTP;
use HiveNova\Core\Language;
use HiveNova\Core\Session;
use HiveNova\Core\Theme;
use HiveNova\Core\Universe;
use HiveNova\Page\Game\ShowErrorPage;

if (isset($_POST['GLOBALS']) || isset($_GET['GLOBALS'])) {
	exit('You cannot set the GLOBALS-array from outside the script.');
}

$composerAutoloader = __DIR__.'/../vendor/autoload.php';

if (file_exists($composerAutoloader)) {
    require $composerAutoloader;
}

if (function_exists('mb_internal_encoding')) {
	mb_internal_encoding("UTF-8");
}

ignore_user_abort(true);
error_reporting(E_ALL);

// If date.timezone is invalid
date_default_timezone_set(@date_default_timezone_get());

ini_set('display_errors', getenv('APP_ENV') === 'development' ? 1 : 0);
header('Content-Type: text/html; charset=UTF-8');
define('TIMESTAMP',	time());

require 'includes/constants.php';

ini_set('log_errors', 'On');
ini_set('error_log', 'includes/error.log');

require 'includes/GeneralFunctions.php';
set_exception_handler(exceptionHandler(...));
set_error_handler(errorHandler(...));

// Say Browsers to Allow ThirdParty Cookies (Thanks to morktadela)
HTTP::sendHeader('P3P', 'CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
define('AJAX_REQUEST', HTTP::_GP('ajax', 0));

$THEME		= new Theme();

if (MODE === 'INSTALL')
{
	return;
}

if(!file_exists('includes/config.php') || filesize('includes/config.php') === 0) {
	HTTP::redirectTo('install/index.php');
}

try {
    $sql	= "SELECT dbVersion FROM %%SYSTEM%%;";

    $dbVersion	= Database::get()->selectSingle($sql, array(), 'dbVersion');

    $dbNeedsUpgrade = $dbVersion < DB_VERSION_REQUIRED;
} catch (Exception $e) {
    $dbNeedsUpgrade = true;
}

if ($dbNeedsUpgrade) {
    HTTP::redirectTo('install/index.php?mode=upgrade');
}

if(defined('DATABASE_VERSION') && DATABASE_VERSION === 'OLD')
{
	/* For our old Admin panel */
	$DATABASE	= new DatabaseBC();

	$dbTableNames	= Database::get()->getDbTableNames();
	$dbTableNames	= array_combine($dbTableNames['keys'], $dbTableNames['names']);

	foreach($dbTableNames as $dbAlias => $dbName)
	{
		define(substr((string) $dbAlias, 2, -2), $dbName);
	}
}

$config = Config::get();
date_default_timezone_set($config->timezone);


if (MODE === 'INGAME' || MODE === 'ADMIN' || MODE === 'CRON')
{
	$session	= Session::load();

	if(!(!$session->isValidSession() && isset($_GET['page']) && $_GET['page']=="raport" && isset($_GET['raport']) && count($_GET)>=2 && MODE === 'INGAME'))
	if(!$session->isValidSession())
	{
	    $session->delete();
		HTTP::redirectTo('index.php?code=3');
	}

	require 'includes/vars.php';

	if(!AJAX_REQUEST && MODE === 'INGAME' && isModuleAvailable(MODULE_FLEET_EVENTS)) {
		require('includes/FleetHandler.php');
	}

	$db		= Database::get();


	$sql	= "SELECT
	user.*,
	COUNT(message.message_id) as messages
	FROM %%USERS%% as user
	LEFT JOIN %%MESSAGES%% as message ON message.message_owner = user.id AND message.message_unread = :unread
	WHERE user.id = :userId
	GROUP BY message.message_owner;";


	$USER	= $db->selectSingle($sql, array(
		':unread'	=> 1,
		':userId'	=> $session->userId
	));

	if(!$session->isValidSession() && isset($_GET['page']) && $_GET['page']=="raport" && isset($_GET['raport']) && count($_GET)>=2 && MODE === 'INGAME') {
	$USER = [];
	$USER['lang']='en';
	$USER['bana']=0;
	$USER['timezone']="Europe/Berlin";
	$USER['urlaubs_modus']=0;
	$USER['authlevel']=0;
	$USER['id']=0;
}


	if(!(!$session->isValidSession() && isset($_GET['page']) && $_GET['page']=="raport" && isset($_GET['raport']) && count($_GET)>=2 && MODE === 'INGAME'))
	if(empty($USER))
	{
		HTTP::redirectTo('index.php?code=3');
	}

	$LNG	= new Language($USER['lang']);
	$LNG->includeData(array('L18N', 'INGAME', 'TECH', 'CUSTOM'));
	if(!empty($USER['dpath'])) { $THEME->setUserTheme($USER['dpath']); }
	$GLOBALS['userNumberFormat'] = $USER['number_format'] ?? 'auto';

	if($config->game_disable == 0 && $USER['authlevel'] == AUTH_USR) {
		ShowErrorPage::printError($LNG['sys_closed_game'].'<br><br>'.$config->close_reason, false);
	}

	if($USER['bana'] == 1) {
		ShowErrorPage::printError("<font size=\"6px\">".$LNG['css_account_banned_message']."</font><br><br>".sprintf($LNG['css_account_banned_expire'], _date($LNG['php_tdformat'], $USER['banaday'], $USER['timezone']))."<br><br>".$LNG['css_goto_homeside'], false);
	}

	if(!(!$session->isValidSession() && isset($_GET['page']) && $_GET['page']=="raport" && isset($_GET['raport']) && count($_GET)>=2 && MODE === 'INGAME'))
	if (MODE === 'INGAME')
	{
		$universeAmount	= count(Universe::availableUniverses());
		if(Universe::current() != $USER['universe'] && $universeAmount > 1)
		{
			HTTP::redirectToUniverse($USER['universe']);
		}

		$session->selectActivePlanet();

		$sql	= "SELECT * FROM %%PLANETS%% WHERE id = :planetId;";
		$PLANET	= $db->selectSingle($sql, array(
			':planetId'	=> $session->planetId,
		));

		if(empty($PLANET))
		{
			$sql	= "SELECT * FROM %%PLANETS%% WHERE id = :planetId;";
			$PLANET	= $db->selectSingle($sql, array(
				':planetId'	=> $USER['id_planet'],
			));

			if(empty($PLANET))
			{
				$sql	= "SELECT id FROM %%PLANETS%% WHERE id_owner = :userId AND destruyed = 0 ORDER BY id ASC LIMIT 1;";
				$fallback = $db->selectSingle($sql, array(':userId' => $USER['id']), 'id');
				if(empty($fallback))
				{
					ShowErrorPage::printError("Main Planet does not exist!", false);
				}
				$session->planetId	= $fallback;
				$USER['id_planet']	= $fallback;
				$db->update("UPDATE %%USERS%% SET id_planet = :planetId WHERE id = :userId;", array(
					':planetId'	=> $fallback,
					':userId'	=> $USER['id'],
				));
				$sql	= "SELECT * FROM %%PLANETS%% WHERE id = :planetId;";
				$PLANET	= $db->selectSingle($sql, array(':planetId' => $fallback));
			}
			else
			{
				$session->planetId = $USER['id_planet'];
			}
		}

		$USER['factor']		= getFactors($USER);
		$USER['PLANETS']	= getPlanets($USER);
	}
	elseif (MODE === 'ADMIN')
	{
		error_reporting(E_ERROR | E_WARNING | E_PARSE);

		$USER['rights']		= safe_unserialize($USER['rights']);
		$LNG->includeData(array('ADMIN', 'CUSTOM'));
	}
}
elseif(MODE === 'LOGIN')
{
	$LNG	= new Language();
	$LNG->getUserAgentLanguage();
	$LNG->includeData(array('L18N', 'INGAME', 'PUBLIC', 'CUSTOM'));
}
elseif(MODE === 'CHAT')
{
	$session	= Session::load();

	if(!$session->isValidSession())
	{
		HTTP::redirectTo('index.php?code=3');
	}
}
