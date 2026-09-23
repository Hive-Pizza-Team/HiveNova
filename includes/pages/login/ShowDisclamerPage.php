<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Config;
use HiveNova\Core\PublicContactService;

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


class ShowDisclamerPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
	}
	
	function show() 
	{
		global $LNG;

		$config	= Config::get();
		$contact = PublicContactService::resolve([
			'address'        => (string) $config->disclamerAddress,
			'phone'          => (string) $config->disclamerPhone,
			'mail'           => (string) $config->disclamerMail,
			'notice'         => (string) $config->disclamerNotice,
			'noticeFallback' => isset($LNG['disclamerNoticeFallback'])
				? (string) $LNG['disclamerNoticeFallback']
				: PublicContactService::DEFAULT_NOTICE_FALLBACK,
		]);
		$this->seoAllowIndex = $contact['hasContent'];
		$this->assign(array(
			'disclamerAddress'		=> makebr($contact['address']),
			'disclamerPhone'		=> $contact['phone'],
			'disclamerMail'			=> $contact['mail'],
			'disclamerMailHref'		=> $contact['mailHref'],
			'disclamerNotice'		=> makebr($contact['notice']),
			'disclamerDiscordUrl'	=> $contact['discordUrl'],
		));
		
		$this->display('page.disclamer.default.tpl');
	}
}
