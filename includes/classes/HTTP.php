<?php

namespace HiveNova\Core;

use HiveNova\Core\Universe;

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

class HTTP {
	
	static public function redirectTo($URL, $external = false)
	{
		if($external)
		{
			self::sendHeader('Location', $URL);
		}
		else
		{
			self::sendHeader('Location', HTTP_PATH.$URL);
		}
		exit;
	}

	static public function sendHeader($name, $value = NULL)
	{
		header($name.(!is_null($value) ? ': '.$value : ''));
	}

	static public function redirectToUniverse($universe)
	{
		HTTP::redirectTo(PROTOCOL.HTTP_HOST.HTTP_BASE."uni".$universe."/".HTTP_FILE, true);
	}

	static public function sendCookie($name, $value = "", $toTime = NULL)
	{
		$secure = defined('HTTPS') ? (bool) HTTPS : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
		setcookie((string) $name, (string) $value, [
			'expires'  => $toTime ?? 0,
			'path'     => '/',
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}
	
	static public function _GP($name, $default, $multibyte = false, $highnum = false)
	{
		// Prefer POST over GET (PHP request_order "GP"). Never read cookies:
		// with empty request_order + variables_order=EGPCS, $_REQUEST lets
		// cookies override query/body and break routing (e.g. a stray "mode").
		if (array_key_exists($name, $_POST)) {
			$value = $_POST[$name];
		} elseif (array_key_exists($name, $_GET)) {
			$value = $_GET[$name];
		} else {
			return $default;
		}

		if(is_float($default) || $highnum)
		{
			return (float) $value;
		}
		
		if(is_int($default))
		{
			return (int) $value;
		}

		if(is_string($default))
		{
			return self::_quote($value, $multibyte);
		}
		
		if(is_array($default) && is_array($value))
		{
			return self::_quoteArray($value, $multibyte, !empty($default) && $default[0] === 0);
		}
		
		return $default;
	}

	private static function _quoteArray($var, $multibyte, $onlyNumbers = false)
	{
		$data	= array();
		foreach($var as $key => $value)
		{
			if(is_array($value))
			{
				$data[$key]	= self::_quoteArray($value, $multibyte);
			}
			elseif($onlyNumbers)
			{
				$data[$key]	= (int) $value;
			}
			else
			{
				$data[$key]	= self::_quote($value, $multibyte);
			}
		}

		return $data;
	}

	private static function _quote($var, $multibyte)
	{
		$var	= str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $var);
		$var	= htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
		$var	= trim($var);

		if ($multibyte) {
			if (!preg_match('/^./u', $var))
			{
				$var = '';
			}
		}
		else
		{
			$var = preg_replace('/[\x80-\xFF]/', '?', $var); // no multibyte, allow only ASCII (0-127)
		}

		return $var;
	}
}