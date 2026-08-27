<?php

namespace HiveNova\Core;

/**
 * Public (login) SEO URL helpers for canonical and hreflang tags.
 */
class PublicSeo
{
	/** @var array<string, string> page => Language siteTitle* key (index uses metaTitleHome) */
	public const PAGE_TITLE_KEYS = [
		'index'        => 'metaTitleHome',
		'news'         => 'siteTitleNews',
		'rules'        => 'siteTitleRules',
		'screens'      => 'siteTitleScreens',
		'battleHall'   => 'siteTitleBattleHall',
		'banList'      => 'siteTitleBanList',
		'disclamer'    => 'siteTitleDisclamer',
		'register'     => 'siteTitleRegister',
		'lostPassword' => 'siteTitleLostPassword',
	];

	/** Pages that should be indexed when they have useful content. */
	public const INDEXABLE_PAGES = [
		'index',
		'news',
		'rules',
		'screens',
		'battleHall',
		'banList',
		'disclamer',
		'register',
	];

	/** Pages listed in sitemap.xml (public marketing surface). */
	public const SITEMAP_PAGES = [
		'index',
		'register',
		'rules',
		'screens',
		'battleHall',
		'news',
		'disclamer',
		'banList',
	];

	/**
	 * Normalize the login page id from the request.
	 */
	public static function normalizePage(string $page): string
	{
		$page = trim($page);
		if ($page === '' || $page === 'overview') {
			return 'index';
		}

		return $page;
	}

	/**
	 * Absolute canonical URL for a public login page.
	 *
	 * Homepage (default language) uses the bare base path. Other pages and
	 * non-default languages use index.php with query params.
	 *
	 * @param string $basePath Trailing-slash site root, e.g. https://moon.hive.pizza/
	 */
	public static function canonicalUrl(string $basePath, string $page, string $lang, string $defaultLang = 'en'): string
	{
		$page = self::normalizePage($page);
		$query = [];

		if ($page !== 'index') {
			$query['page'] = $page;
		}

		if ($lang !== '' && $lang !== $defaultLang) {
			$query['lang'] = $lang;
		}

		if ($query === []) {
			return $basePath;
		}

		return $basePath.'index.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Map of hreflang code => absolute URL, including x-default (default language homepage/page).
	 *
	 * @param array<string, string> $languages langKey => display name
	 * @return array<string, string>
	 */
	public static function hreflangUrls(string $basePath, string $page, array $languages, string $defaultLang = 'en'): array
	{
		$page = self::normalizePage($page);
		$urls = [];

		foreach (array_keys($languages) as $langKey) {
			$urls[$langKey] = self::canonicalUrl($basePath, $page, (string) $langKey, $defaultLang);
		}

		$urls['x-default'] = self::canonicalUrl($basePath, $page, $defaultLang, $defaultLang);

		return $urls;
	}

	/**
	 * Document title for <title> / og:title.
	 *
	 * @param object $LNG ArrayAccess language bag
	 */
	public static function documentTitle(string $page, string $gameName, $LNG): string
	{
		$page = self::normalizePage($page);
		$key = self::PAGE_TITLE_KEYS[$page] ?? null;

		if ($page === 'index' || $key === 'metaTitleHome') {
			$template = isset($LNG['metaTitleHome']) ? (string) $LNG['metaTitleHome'] : '%s — Free browser space strategy game';
			return sprintf($template, $gameName);
		}

		if ($key !== null && isset($LNG[$key])) {
			return (string) $LNG[$key].' - '.$gameName;
		}

		return $gameName;
	}

	/**
	 * Short heading (no brand suffix) for in-page H1 on light layouts.
	 *
	 * @param object $LNG ArrayAccess language bag
	 */
	public static function pageHeading(string $page, string $gameName, $LNG): string
	{
		$page = self::normalizePage($page);

		if ($page === 'index') {
			return sprintf(isset($LNG['loginWelcome']) ? (string) $LNG['loginWelcome'] : 'Welcome to %s', $gameName);
		}

		$key = self::PAGE_TITLE_KEYS[$page] ?? null;
		if ($key !== null && $key !== 'metaTitleHome' && isset($LNG[$key])) {
			return (string) $LNG[$key];
		}

		return $gameName;
	}

	/**
	 * Meta description for the page.
	 *
	 * @param object $LNG ArrayAccess language bag
	 */
	public static function metaDescription(string $page, string $gameName, $LNG): string
	{
		$page = self::normalizePage($page);
		$key = 'metaDescription'.self::metaKeySuffix($page);

		if (isset($LNG[$key])) {
			$text = (string) $LNG[$key];
			return strpos($text, '%s') !== false ? sprintf($text, $gameName) : $text;
		}

		$fallback = isset($LNG['metaDescriptionIndex'])
			? (string) $LNG['metaDescriptionIndex']
			: 'Multiplayer Orbiting Optimization Network (MOON) game. Space themed empire building game in the browser. Free-to-play.';

		return strpos($fallback, '%s') !== false ? sprintf($fallback, $gameName) : $fallback;
	}

	public static function metaKeySuffix(string $page): string
	{
		$page = self::normalizePage($page);
		if ($page === 'index') {
			return 'Index';
		}

		return ucfirst($page);
	}

	public static function robotsContent(string $page, bool $allowIndex = true): string
	{
		$page = self::normalizePage($page);
		if (!$allowIndex || !in_array($page, self::INDEXABLE_PAGES, true)) {
			return 'noindex, follow';
		}

		return 'index, follow';
	}
}
