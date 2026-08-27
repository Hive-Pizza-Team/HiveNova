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
use HiveNova\Core\Template;


if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowClearCachePage()
{
	global $LNG;

	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
		ClearCache();
		$template = new Template();
		$template->message($LNG['cc_cache_clear']);
		return;
	}

	$template = new Template();
	$template->assign_vars([
		'admin_csrf' => AdminCsrf::token(),
		'cc_cache_clear' => $LNG['cc_cache_clear'] ?? 'Clear cache',
		'button_submit' => $LNG['button_submit'] ?? 'Submit',
		'mu_clear_cache' => $LNG['mu_clear_cache'] ?? 'Clear cache',
	]);
	$template->show('ClearCacheConfirm.tpl');
}
