<?php

namespace HiveNova\Core;

use ArrayAccess;

use HiveNova\Core\Config;
use HiveNova\Core\Cache;
use HiveNova\Core\HTTP;

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

class Language implements ArrayAccess {
    private $container = array();
    private $language = array();
    static private $allLanguages = array();

	static function getAllowedLangs($OnlyKey = true)
	{
		if(empty(self::$allLanguages))
		{
			$cache	= Cache::get();
			$cache->add('language', 'HiveNova\\Core\\Cache\\LanguageBuildCache');
			self::$allLanguages = $cache->getData('language');
		}

		if($OnlyKey)
		{
			return array_keys(self::$allLanguages);
		}
		else
		{
			return self::$allLanguages;
		}
	}

	/**
	 * Resolve login/install UI language.
	 *
	 * Priority: explicit ?lang= (override) → lang cookie (prior override) →
	 * Accept-Language → leave existing/default language (no sticky cookie).
	 */
	public function getUserAgentLanguage()
	{
   		if (isset($_REQUEST['lang']) && in_array($_REQUEST['lang'], self::getAllowedLangs()))
		{
			HTTP::sendCookie('lang', $_REQUEST['lang'], 2147483647);
			$this->setLanguage($_REQUEST['lang']);
			return true;
		}

   		if ((MODE === 'LOGIN' || MODE === 'INSTALL') && isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::getAllowedLangs()))
		{
			$this->setLanguage($_COOKIE['lang']);
			return true;
		}

		$detected = self::preferredFromAcceptLanguage(
			(string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
			self::getAllowedLangs()
		);

		if ($detected === null)
		{
			return false;
		}

		HTTP::sendCookie('lang', $detected, 2147483647);
		$this->setLanguage($detected);

		return $detected;
	}

	/**
	 * Pick the best allowed language from an Accept-Language header.
	 *
	 * @param list<string> $allowed
	 */
	public static function preferredFromAcceptLanguage(string $header, array $allowed): ?string
	{
		$header = trim($header);
		if ($header === '' || $allowed === [])
		{
			return null;
		}

		$allowedLookup = array_fill_keys($allowed, true);
		$candidates = [];

		foreach (preg_split('/,\s*/', $header) as $acceptedLanguage)
		{
			$isValid = preg_match(
				'!^([a-z]{1,8}(?:-[a-z]{1,8})*)(?:;\s*q=(0(?:\.[0-9]{1,3})?|1(?:\.0{1,3})?))?$!i',
				$acceptedLanguage,
				$matches
			);

			if ($isValid !== 1)
			{
				continue;
			}

			$code = strtolower(explode('-', $matches[1])[0]);
			if (!isset($allowedLookup[$code]))
			{
				continue;
			}

			$q = isset($matches[2]) && $matches[2] !== '' ? (float) $matches[2] : 1.0;
			$candidates[] = ['code' => $code, 'q' => $q];
		}

		if ($candidates === [])
		{
			return null;
		}

		usort($candidates, static function (array $a, array $b): int {
			if ($a['q'] === $b['q']) {
				return 0;
			}

			return ($a['q'] < $b['q']) ? 1 : -1;
		});

		return $candidates[0]['code'];
	}

    public function __construct($language = NULL)
	{
		$this->setLanguage($language);
    }

    public function setLanguage($language)
	{
		if(!is_null($language) && in_array($language, self::getAllowedLangs()))
		{
			$this->language = $language;
		}
		elseif(MODE !== 'INSTALL')
		{
			$this->language	= Config::get()->lang;
		}
		else
		{
			$this->language	= DEFAULT_LANG;
		}
    }

    public function addData($data) {
		$this->container = array_replace_recursive($this->container, $data);
    }

	public function getLanguage()
	{
		return $this->language;
	}

	public function getTemplate($templateName)
	{
		if(file_exists('language/'.$this->getLanguage().'/templates/'.$templateName.'.txt'))
		{
			return file_get_contents('language/'.$this->getLanguage().'/templates/'.$templateName.'.txt');
		}
		else
		{
			return '### Template "'.$templateName.'" on language "'.$this->getLanguage().'" not found! ###';
		}
	}


	public function includeData($files)
	{
		// Fixed BOM problems.
		ob_start();
		$LNG	= array();

		//FALLBACK
		$path	= 'language/en/';
        foreach($files as $file) {
			$filePath	= $path.$file.'.php';
			if(file_exists($filePath))
			{
				require $filePath;
			}
		}
		
		$DEFAULT = $LNG;

		// Get current client language
		$path	= 'language/'.$this->getLanguage().'/';
		foreach ($files as $file) {
			$filePath	= $path . $file . '.php';
			if (file_exists($filePath)) {
				require $filePath;
			}
		}
		
		// Build missing language data from English to client language
		foreach ($DEFAULT as $TextKey => $TextData) {
			if (is_array($TextData)) {
				foreach ($TextData as $Element => $ElementText) {
					if (array_key_exists($Element, $LNG[$TextKey])) continue;
					$LNG[$TextKey][$Element] = $ElementText;
				}
			}
		}

		ob_end_clean();

		$this->addData($LNG);
	}

	/** ArrayAccess Functions **/

    public function offsetSet(mixed $offset, mixed $value): void {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool {
        return isset($this->container[$offset]);
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->container[$offset]);
    }

    public function offsetGet(mixed $offset): mixed {
        return isset($this->container[$offset]) ? $this->container[$offset] : $offset;
    }
}
