<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\HTTP;
use HiveNova\Core\PublicSeo;

/**
 * Correct-spelling alias for the 2Moons `disclamer` login route.
 *
 * Admin Contact settings stay on `admin.php?page=disclamer` (legacy).
 */
class ShowDisclaimerPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		$lang = HTTP::_GP('lang', '');
		$location = PublicSeo::aliasRedirectLocation('disclamer', $lang);
		header('Location: '.$location, true, 301);
		exit;
	}
}
