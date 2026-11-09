<?php

namespace HiveNova\Core;

/**
 * Public (login) SEO URL helpers for canonical and hreflang tags.
 */
class PublicSeo
{
	/**
	 * Single public lobby host for canonical, hreflang, og:url, sitemap, and robots.
	 * Request host may differ (moon.hive.pizza, www); SEO tags always use this.
	 */
	public const CANONICAL_HOST = 'moonsiege.com';

	/** Trailing-slash HTTPS root used in all public SEO absolute URLs. */
	public const CANONICAL_BASE = 'https://moonsiege.com/';

	/**
	 * Hosts that 301 to CANONICAL_HOST on the public lobby (index.php) only.
	 * game.php must not redirect: session cookies are host-scoped (domain '').
	 *
	 * @var list<string>
	 */
	public const ALIAS_HOSTS = [
		'moon.hive.pizza',
		'www.moonsiege.com',
	];

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
		'disclamer',
		'register',
	];

	/** Pages linked from the public footer (internal linking for crawlers and visitors). */
	public const FOOTER_PAGES = [
		'register',
		'rules',
		'screens',
		'battleHall',
	];

	/** Twitter card @site handle (public lobby head tags). */
	public const TWITTER_SITE = '@PizzaOnHive';

	/**
	 * Pages listed in sitemap.xml (public marketing surface).
	 * Omit empty/noindex URLs (news) until they have indexable content.
	 * `disclamer` stays once Contact Admin has Discord fallback content.
	 */
	public const SITEMAP_PAGES = [
		'index',
		'register',
		'rules',
		'screens',
		'battleHall',
		'disclamer',
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
	 * Correct-spelling alias → 2Moons login page id.
	 * Admin `?page=disclamer` is intentional legacy and is not redirected.
	 */
	public static function loginPageAlias(string $page): ?string
	{
		$page = trim($page);
		if ($page === 'disclaimer') {
			return 'disclamer';
		}

		return null;
	}

	/**
	 * Relative Location for a known login-page alias, or null if $page is canonical.
	 */
	public static function loginAliasRedirectTarget(string $page, string $lang = '', string $defaultLang = 'en'): ?string
	{
		$canonical = self::loginPageAlias($page);
		if ($canonical === null) {
			return null;
		}

		return self::aliasRedirectLocation($canonical, $lang, $defaultLang);
	}

	/**
	 * Relative Location target for a login-page alias (301).
	 */
	public static function aliasRedirectLocation(string $canonicalPage, string $lang = '', string $defaultLang = 'en'): string
	{
		$query = ['page' => $canonicalPage];
		if ($lang !== '' && $lang !== $defaultLang) {
			$query['lang'] = $lang;
		}

		return 'index.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Absolute canonical URL for a public login page.
	 *
	 * Homepage (default language) uses the bare base path. Other pages and
	 * non-default languages use index.php with query params (not /?lang=).
	 *
	 * @param string $basePath Trailing-slash site root, e.g. https://moonsiege.com/
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
	 * Absolute Location for a lobby host/HTTPS consolidation redirect, or null.
	 *
	 * Preserves path and query (ref, src, lang, page). Only for LOGIN/index.php
	 * callers — never game.php / admin.php.
	 *
	 * @param string $host        HTTP_HOST (port stripped)
	 * @param string $requestUri  REQUEST_URI including query string
	 * @param bool   $https       Whether the request is already HTTPS
	 */
	public static function lobbyCanonicalRedirectLocation(string $host, string $requestUri, bool $https): ?string
	{
		$host = strtolower(explode(':', $host, 2)[0]);
		$isAlias = in_array($host, self::ALIAS_HOSTS, true);
		$isCanonical = $host === self::CANONICAL_HOST;
		if (!$isAlias && !$isCanonical) {
			return null;
		}

		if (!$isAlias && $https) {
			return null;
		}

		$path = $requestUri !== '' ? $requestUri : '/';
		if ($path[0] !== '/') {
			$path = '/'.$path;
		}

		return 'https://'.self::CANONICAL_HOST.$path;
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
			$template = isset($LNG['metaTitleHome']) ? (string) $LNG['metaTitleHome'] : '%s — Free browser space strategy — Uni 1 frontier';
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

	/**
	 * sitemap.xml body: one <url> per page per language, each carrying the full
	 * hreflang alternate set (including x-default) so translations are discoverable.
	 *
	 * @param list<string> $langKeys
	 */
	public static function sitemapXml(string $basePath, array $langKeys, string $lastmod, string $defaultLang = 'en'): string
	{
		$esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$languages = array_fill_keys($langKeys, '');

		$xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
			.'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

		foreach (self::SITEMAP_PAGES as $page) {
			$alternates = self::hreflangUrls($basePath, $page, $languages, $defaultLang);
			foreach ($langKeys as $langKey) {
				$xml .= "\t<url>\n"
					."\t\t<loc>".$esc(self::canonicalUrl($basePath, $page, $langKey, $defaultLang))."</loc>\n"
					."\t\t<lastmod>".$esc($lastmod)."</lastmod>\n";
				foreach ($alternates as $code => $href) {
					$xml .= "\t\t".'<xhtml:link rel="alternate" hreflang="'.$esc((string) $code).'" href="'.$esc($href).'"/>'."\n";
				}
				$xml .= "\t</url>\n";
			}
		}

		return $xml."</urlset>\n";
	}

	/**
	 * Footer links to the public marketing pages, in the visitor's language.
	 *
	 * @param object $LNG ArrayAccess language bag
	 * @return list<array{url: string, label: string}>
	 */
	public static function footerLinks(string $basePath, string $lang, $LNG, string $defaultLang = 'en'): array
	{
		$links = [];
		foreach (self::FOOTER_PAGES as $page) {
			$key = self::PAGE_TITLE_KEYS[$page] ?? null;
			if ($key === null || !isset($LNG[$key])) {
				continue;
			}
			$links[] = [
				'url'   => self::canonicalUrl($basePath, $page, $lang, $defaultLang),
				'label' => (string) $LNG[$key],
			];
		}

		return $links;
	}

	/**
	 * Genuine public entity URLs for Google sameAs (lobby VideoGame / publisher).
	 *
	 * @return list<string>
	 */
	public static function entitySameAs(): array
	{
		$discord = defined('DISCORD_URL') && DISCORD_URL !== ''
			? (string) DISCORD_URL
			: 'https://discord.gg/bP6ksCeEUk';

		return [
			$discord,
			'https://github.com/Hive-Pizza-Team/HiveNova',
			'https://peakd.com/@hive.pizza',
			'https://hive.pizza/',
		];
	}

	/**
	 * FAQPage JSON-LD. Only emit for Q&A pairs that are also visible on the page.
	 *
	 * @param list<array{q: string, a: string}> $items
	 */
	public static function faqJsonLd(array $items): string
	{
		if ($items === []) {
			return '';
		}

		return json_encode([
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(static fn (array $item): array => [
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
			], $items),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
	}

	/**
	 * JSON-LD for the public lobby homepage (VideoGame + nested publisher).
	 */
	public static function indexJsonLd(string $gameName, string $canonicalUrl, string $description, string $imageUrl): string
	{
		$sameAs = self::entitySameAs();

		return json_encode([
			'@context'    => 'https://schema.org',
			'@type'       => 'VideoGame',
			'name'        => $gameName,
			'url'         => $canonicalUrl,
			'description' => $description,
			'image'       => $imageUrl,
			'genre'       => 'Strategy',
			'applicationCategory' => 'Game',
			'operatingSystem' => 'Any',
			'sameAs'      => $sameAs,
			'offers'      => [
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'USD',
			],
			'publisher'   => [
				'@type'  => 'Organization',
				'name'   => 'Hive Pizza Team',
				'url'    => 'https://hive.pizza/',
				'sameAs' => $sameAs,
			],
			'contributor' => [
				'@type'  => 'Organization',
				'name'   => 'Team Mithril',
				'url'    => 'https://github.com/srbde',
			],
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
