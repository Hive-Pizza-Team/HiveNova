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

define('MODE', 'INGAME');
define('ROOT_PATH', str_replace('\\', '/',dirname(__FILE__)).'/');
set_include_path(ROOT_PATH.'includes/libs/BBCodeParser2/'.':'.ROOT_PATH.':'.get_include_path());
require_once('HTML/BBCodeParser2.php');

require 'includes/common.php';
/** @var $LNG Language */

use HiveNova\Page\Game\ShowErrorPage;
use HiveNova\Core\AuthLevel;
use HiveNova\Core\Config;
use HiveNova\Core\DatabaseSeasonStore;
use HiveNova\Core\Language;
use HiveNova\Core\SeasonService;


$page 		= \HiveNova\Core\HTTP::_GP('page', 'overview');
$mode 		= \HiveNova\Core\HTTP::_GP('mode', 'show');
$page		= str_replace(array('_', '\\', '/', '.', "\0"), '', $page);
$pageClass	= 'Show'.ucwords($page).'Page';

$fqcn		= 'HiveNova\\Page\\Game\\' . $pageClass;

$uniConfig = Config::get();
if (isset($uniConfig->season_mode) && (int) $uniConfig->season_mode === 1 && isset($USER) && !empty($USER['id']) && !AuthLevel::isStaff((int) $USER['authlevel'])) {
	$seasonGate = new SeasonService(new DatabaseSeasonStore());
	if ($seasonGate->mustRedirect($USER, $uniConfig, $page)) {
		\HiveNova\Core\HTTP::redirectTo('game.php?page=season');
	}
}

if(!class_exists($fqcn)) {
	ShowErrorPage::printError($LNG['page_doesnt_exist']);
}

$pageObj	= new $fqcn;
// PHP 5.2 FIX
// can't use $pageObj::$requireModule
$pageProps	= get_class_vars(get_class($pageObj));

if(isset($pageProps['requireModule']) && $pageProps['requireModule'] !== 0 && !isModuleAvailable($pageProps['requireModule'])) {
	ShowErrorPage::printError($LNG['sys_module_inactive']);
}

if(!is_callable(array($pageObj, $mode))) {
	if(!isset($pageProps['defaultController']) || !is_callable(array($pageObj, $pageProps['defaultController']))) {
		ShowErrorPage::printError($LNG['page_doesnt_exist']);
	}
	$mode	= $pageProps['defaultController'];
}

$pageObj->{$mode}();
