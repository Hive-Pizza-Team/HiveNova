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

use HiveNova\Core\AdminMultiIpList;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;
use HiveNova\Core\Template;


if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowMultiIPPage()
{
	global $LNG;
	
	if(!isset($_POST['action'])) { $_POST['action'] = ''; }
	switch($_POST['action'])
	{
		case 'known':
			$GLOBALS['DATABASE']->query("INSERT INTO ".MULTI." SET userID = ".((int) $_POST['id']).";");
			HTTP::redirectTo("admin.php?page=multiips");
		break;
		case 'unknown':
			$GLOBALS['DATABASE']->query("DELETE FROM ".MULTI." WHERE userID = ".((int) $_POST['id']).";");
			HTTP::redirectTo("admin.php?page=multiips");
		break;
	}

	$universe = Universe::getEmulated();
	$query = $GLOBALS['DATABASE']->query(
		"SELECT u.id, u.username, u.email, u.register_time, u.onlinetime, u.user_lastip,"
		." IFNULL(m.multiID, 0) as isKnown"
		." FROM ".USERS." u"
		." LEFT JOIN ".MULTI." m ON m.userID = u.id"
		." WHERE u.`universe` = '".$universe."'"
		." AND u.user_lastip IN ("
		."   SELECT user_lastip FROM ".USERS
		."   WHERE `universe` = '".$universe."'"
		."   GROUP BY user_lastip HAVING COUNT(*) > 1"
		." )"
		." ORDER BY u.user_lastip, u.id ASC;"
	);

	$rows = [];
	while ($data = $GLOBALS['DATABASE']->fetch_array($query)) {
		$rows[] = $data;
	}

	$missingFormat = $LNG['mip_missing_user'] ?? 'Deleted / unknown (#%s)';
	$IPs = AdminMultiIpList::groupByIp(
		$rows,
		static function (int $timestamp) use ($LNG): string {
			return _date($LNG['php_tdformat'], $timestamp);
		},
		$missingFormat
	);
	
	$template	= new Template();
	$template->assign_vars(array(
		'multiGroups'	=> $IPs,
	));
	$template->show('MultiIPs.tpl');
}
