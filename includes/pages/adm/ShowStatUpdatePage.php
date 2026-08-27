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

use HiveNova\Core\AdminCsrf;
use HiveNova\Core\StatBuilder;
use HiveNova\Core\Template;


if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowStatUpdatePage() {
	global $LNG;

	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		$template = new Template();
		$template->assign_vars([
			'admin_csrf' => AdminCsrf::token(),
			'mu_manual_points_update' => $LNG['mu_manual_points_update'] ?? 'Update stats',
			'mu_mpu_confirmation' => $LNG['mu_mpu_confirmation'] ?? 'Run stats update?',
			'button_submit' => $LNG['button_submit'] ?? 'Submit',
		]);
		$template->show('StatUpdateConfirm.tpl');
		return;
	}

	$stat			= new StatBuilder();
	$result			= $stat->MakeStats();
	$memory_p		= str_replace(array("%p", "%m"), $result['memory_peak'], $LNG['sb_top_memory']);
	$memory_e		= str_replace(array("%e", "%m"), $result['end_memory'], $LNG['sb_final_memory']);
	$memory_i		= str_replace(array("%i", "%m"), $result['initial_memory'], $LNG['sb_start_memory']);
	$stats_end_time	= sprintf($LNG['sb_stats_update'], $result['totaltime']);
	$stats_sql		= sprintf($LNG['sb_sql_counts'], $result['sql_count']);

	$template = new Template();
	$template->message($LNG['sb_stats_updated'].$stats_end_time.$memory_i.$memory_e.$memory_p.$stats_sql, false, 0, true);
}
