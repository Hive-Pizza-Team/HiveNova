<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\AssetRevision;
use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\Language;
use HiveNova\Core\LoginUniverseDefaults;
use HiveNova\Core\PublicSeo;
use HiveNova\Core\ReferralCaptureService;
use HiveNova\Core\Template as template;
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

abstract class AbstractLoginPage
{

	/**
	 * reference of the template object
	 * @var template
	 */
	protected $tplObj = null;
	protected $window;
	public $defaultWindow = 'normal';
	/** @var bool When false, public pages emit noindex (e.g. empty news). */
	protected $seoAllowIndex = true;
	/**
	 * Optional SEO field overrides applied in display() (title, description, canonical, OG…).
	 *
	 * @var array<string, mixed>
	 */
	protected $seoOverrides = [];
	
	protected function __construct() {
		
		if(!AJAX_REQUEST)
		{
			$this->setWindow($this->defaultWindow);
			$this->initTemplate();
		} else {
			$this->setWindow('ajax');
		}
	}

	protected function getUniverseSelector()
	{
		$universeSelect	= array();
		foreach(array_reverse(Universe::availableUniverses()) as $uniId)
		{
			$universeSelect[$uniId]	= Config::get($uniId)->uni_name;
		}

		return $universeSelect;
	}

	/**
	 * Newest open universe for login/register dropdown default.
	 * Skips closed universes (and registration-closed when registering).
	 */
	protected function getDefaultUniverseId($forRegistration = false)
	{
		return LoginUniverseDefaults::newestOpen((bool) $forRegistration);
	}

	/**
	 * Email/password flows default to Universe 1 when it is an open
	 * non-seasonal universe, otherwise the newest non-seasonal open universe.
	 */
	protected function getDefaultEmailUniverseId($forRegistration = false)
	{
		return LoginUniverseDefaults::forEmail((bool) $forRegistration);
	}

	/**
	 * Hive Keychain flows default to the busiest open universe.
	 */
	protected function getDefaultHiveUniverseId($forRegistration = false)
	{
		return LoginUniverseDefaults::forHive((bool) $forRegistration);
	}

	protected function initTemplate()
	{
		if(isset($this->tplObj))
			return true;
			
		$this->tplObj	= new template;
		list($tplDir)	= $this->tplObj->getTemplateDir();
		$this->tplObj->setTemplateDir($tplDir.'login/');
		return true;
	}
	
	protected function setWindow($window) {
		$this->window	= $window;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	protected function overrideSeo(array $overrides): void
	{
		$this->seoOverrides = array_merge($this->seoOverrides, $overrides);
	}

	/**
	 * Public absolute base path used for canonical / OG URLs (trailing slash).
	 */
	protected function publicBasePath(): string
	{
		if (UNIS_WILDCAST) {
			$hostParts = explode('.', HTTP_HOST);
			if (preg_match('/uni[0-9]+/', $hostParts[0])) {
				array_shift($hostParts);
			}
			$host = implode('.', $hostParts);

			return PROTOCOL.$host.HTTP_BASE;
		}

		return PROTOCOL.HTTP_HOST.HTTP_BASE;
	}
		
	protected function getWindow() {
		return $this->window;
	}
	
	protected function getQueryString() {
		$queryString	= array();
		$page			= HTTP::_GP('page', '');
		
		if(!empty($page)) {
			$queryString['page']	= $page;
		}
		
		$mode			= HTTP::_GP('mode', '');
		if(!empty($mode)) {
			$queryString['mode']	= $mode;
		}
		
		return http_build_query($queryString);
	}
	
	protected function getPageData() 
    {		
		global $LNG;

		$config	= Config::get();

        $this->tplObj->assign_vars(array(
			'recaptchaEnable'		=> $config->capaktiv,
			'recaptchaPublicKey'	=> $config->cappublic,
			'gameName' 				=> $config->game_name,
			'facebookEnable'		=> $config->fb_on,
			'fb_key' 				=> $config->fb_apikey,
			'mailEnable'			=> $config->mail_active,
			'reg_close'				=> $config->reg_closed,
			'referralEnable'		=> ReferralCaptureService::anyUniverseHasReferralsActive() ? 1 : 0,
			'analyticsEnable'		=> $config->ga_active,
			'analyticsUID'			=> $config->ga_key,
			'lang'					=> $LNG->getLanguage(),
			'UNI'					=> Universe::current(),
			'VERSION'				=> $config->VERSION,
			'REV'					=> AssetRevision::fromFilesystem((string) $config->VERSION),
			'languages'				=> Language::getAllowedLangs(false),
		));
	}
	
	protected function printMessage($message, $redirectButtons = null, $redirect = null, $fullSide = true)
	{
		$this->assign(array(
			'message'			=> $message,
			'redirectButtons'	=> $redirectButtons,
		));
		
		if(isset($redirect) && is_array($redirect)) {
			$this->tplObj->gotoside($redirect[0], $redirect[1]);
		}
		
		if(!$fullSide) {
			$this->setWindow('popup');
		}
		
		$this->display('error.default.tpl');
	}
	
	protected function save() {
		
	}

	protected function assign($array, $nocache = true) {
		$this->tplObj->assign_vars($array, $nocache);
	}
	
	protected function display($file) {
		global $LNG;
		
		$this->save();
		
		if($this->getWindow() !== 'ajax') {
			$this->getPageData();
		}

		$basePath = $this->publicBasePath();
		$seoBasePath = PublicSeo::canonicalBasePath($basePath);

		$config			= Config::get();
		$lang			= $LNG->getLanguage();
		$seoPage		= PublicSeo::normalizePage(HTTP::_GP('page', ''));
		$gameName		= (string) $config->game_name;
		$languages		= Language::getAllowedLangs(false);
		$documentTitle		= PublicSeo::documentTitle($seoPage, $gameName, $LNG);
		$metaDescription	= PublicSeo::metaDescription($seoPage, $gameName, $LNG);
		$canonicalUrl		= PublicSeo::canonicalUrl($seoBasePath, $seoPage, $lang);
		$hreflangUrls		= PublicSeo::hreflangUrls($seoBasePath, $seoPage, $languages);
		$pageHeading		= PublicSeo::pageHeading($seoPage, $gameName, $LNG);
		$robotsContent		= PublicSeo::robotsContent($seoPage, $this->seoAllowIndex);
		$ogImageUrl			= $seoBasePath.'styles/resource/images/login/HiveNova.png';
		$ogImageWidth		= 1024;
		$ogImageHeight		= 768;
		$jsonLd				= '';
		if ($seoPage === 'index') {
			$jsonLd = PublicSeo::indexJsonLd($gameName, $canonicalUrl, $metaDescription, $ogImageUrl);
		}

		$seo = array_merge([
			'documentTitle'		=> $documentTitle,
			'metaDescription'	=> $metaDescription,
			'canonicalUrl'		=> $canonicalUrl,
			'hreflangUrls'		=> $hreflangUrls,
			'pageHeading'		=> $pageHeading,
			'robotsContent'		=> $robotsContent,
			'ogImageUrl'		=> $ogImageUrl,
			'ogImageWidth'		=> $ogImageWidth,
			'ogImageHeight'		=> $ogImageHeight,
			'jsonLd'			=> $jsonLd,
			'seoPage'			=> $seoPage,
		], $this->seoOverrides);
		
		$this->assign(array(
            'lang'    			=> $lang,
			'bodyclass'			=> $this->getWindow(),
			'basepath'			=> $basePath,
			'isMultiUniverse'	=> count(Universe::availableUniverses()) > 1,
			'unisWildcast'		=> UNIS_WILDCAST,
			'documentTitle'		=> $seo['documentTitle'],
			'metaDescription'	=> $seo['metaDescription'],
			'canonicalUrl'		=> $seo['canonicalUrl'],
			'hreflangUrls'		=> $seo['hreflangUrls'],
			'pageHeading'		=> $seo['pageHeading'],
			'robotsContent'		=> $seo['robotsContent'],
			'ogImageUrl'		=> $seo['ogImageUrl'],
			'ogImageWidth'		=> $seo['ogImageWidth'],
			'ogImageHeight'		=> $seo['ogImageHeight'],
			'twitterSite'		=> PublicSeo::TWITTER_SITE,
			'seoPage'			=> $seo['seoPage'],
			'footerLinks'		=> PublicSeo::footerLinks($seoBasePath, $lang, $LNG),
			'jsonLd'			=> $seo['jsonLd'],
		));

		$this->assign(array(
			'LNG'			=> $LNG,
		), false);
		
		$this->tplObj->display('extends:layout.'.$this->getWindow().'.tpl|'.$file);
		exit;
	}
	
	protected function sendJSON($data) {
		$this->save();
		echo json_encode($data);
		exit;
	}
	
	protected function redirectTo($url) {
		$this->save();
		HTTP::redirectTo($url);
		exit;
	}
	
	protected function redirectPost($url, $postFields) {
		$this->save();
		$this->assign(array(
            'url'    		=> $url,
			'postFields'	=> $postFields,
		));
		
		$this->display('info.redirectPost.tpl');
	}
}