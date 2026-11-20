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

use HiveNova\Core\AdminReloginPassword;
use HiveNova\Core\AuthLevel;
use HiveNova\Core\HTTP;
use HiveNova\Core\Session;
use HiveNova\Core\Template;


if (!AuthLevel::canEnterAdmin((int) $USER['authlevel']))
{
	throw new PagePermissionException("Permission error!");
}

function ShowLoginPage()
{
	global $USER, $LNG;

	$session	= Session::create();
	if($session->adminAccess == 1)
	{
		HTTP::redirectTo('admin.php');
	}

	if(isset($_REQUEST['access_token'])) // SteemConnectv2 callback
	{
		require 'includes/classes/extauth/externalAuth.interface.php';
		require 'includes/classes/extauth/steemconnect.class.php';
		$authObj = new SteemconnectAuth;
		if($authObj->isActiveMode() && $authObj->isValid() && !empty($authObj->getLoginData()))
		{
			$session->adminAccess	= 1;
			HTTP::redirectTo('admin.php');
		}
	}

	$loginError	= '';
	if(isset($_REQUEST['admin_pw']))
	{
		if(AdminReloginPassword::matches((string) $USER['password'])) {
			$session->adminAccess	= 1;
			HTTP::redirectTo('admin.php');
		}

		$loginError	= $LNG['adm_bad_password'];
	}

	$template	= new Template();

	require 'includes/libs/steemconnect/steemconnect.php';
	$steemconnectUrl = Steemconnect::getAdminUrl();

	$template->assign_vars(array(	
		'bodyclass'	=> 'standalone',
		'username'	=> $USER['username'],
		'steemconnectUrl' => $steemconnectUrl,
		'loginError'	=> $loginError,
	));
	$template->show('LoginPage.tpl');
}