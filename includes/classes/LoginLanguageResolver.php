<?php

namespace HiveNova\Core;

/**
 * Resolve UI language for login/public (and install) pages.
 *
 * Order: explicit ?lang= → lang_choice cookie (explicit only) →
 * Accept-Language best match → English if allowed → config/default fallback.
 *
 * Auto-detection never persists a cookie. Legacy auto-written `lang` cookies are
 * ignored so they no longer override Accept-Language; ?lang= writes `lang_choice`
 * and clears the legacy cookie.
 */
class LoginLanguageResolver
{
	public const EXPLICIT_COOKIE = 'lang_choice';
	public const LEGACY_COOKIE = 'lang';
	public const COOKIE_EXPIRES = 2147483647;

	/**
	 * @param list<string>             $allowed
	 * @param array<string|int, mixed> $cookies
	 * @return array{language: string, persistExplicit: ?string, clearLegacy: bool}
	 */
	public static function resolve(
		?string $requestLang,
		array $cookies,
		string $acceptLanguageHeader,
		array $allowed,
		string $configFallback,
		bool $honorExplicitCookie = true
	): array {
		$allowedLookup = array_fill_keys($allowed, true);

		if ($requestLang !== null && $requestLang !== '' && isset($allowedLookup[$requestLang])) {
			return [
				'language'        => $requestLang,
				'persistExplicit' => $requestLang,
				'clearLegacy'     => true,
			];
		}

		if ($honorExplicitCookie) {
			$explicit = (string) ($cookies[self::EXPLICIT_COOKIE] ?? '');
			if ($explicit !== '' && isset($allowedLookup[$explicit])) {
				return [
					'language'        => $explicit,
					'persistExplicit' => null,
					'clearLegacy'     => false,
				];
			}
		}

		$detected = Language::preferredFromAcceptLanguage($acceptLanguageHeader, $allowed);
		if ($detected !== null) {
			return [
				'language'        => $detected,
				'persistExplicit' => null,
				'clearLegacy'     => false,
			];
		}

		if (isset($allowedLookup['en'])) {
			return [
				'language'        => 'en',
				'persistExplicit' => null,
				'clearLegacy'     => false,
			];
		}

		$fallback = isset($allowedLookup[$configFallback])
			? $configFallback
			: (string) ($allowed[0] ?? $configFallback);

		return [
			'language'        => $fallback,
			'persistExplicit' => null,
			'clearLegacy'     => false,
		];
	}

	/**
	 * Build same-page language switch URLs that keep other query params.
	 *
	 * @param array<string, string>    $languages langKey => display name
	 * @param array<string|int, mixed> $query     current request query
	 * @return array<string, string>   langKey => relative index.php?... URL
	 */
	public static function switchUrls(array $languages, array $query): array
	{
		$urls = [];
		foreach (array_keys($languages) as $langKey) {
			$params = $query;
			$params['lang'] = $langKey;
			$urls[$langKey] = 'index.php?'.http_build_query($params);
		}

		return $urls;
	}
}
