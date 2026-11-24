<?php

namespace HiveNova\Core;

/**
 * Persist lobby / explicit language choice onto the user record used by in-game UI.
 *
 * In-game pages read `$USER['lang']`, while the lobby uses `?lang=` + the `lang`
 * cookie. This service bridges those on login/register when the player made an
 * intentional lobby choice. Cookie / Accept-Language alone is not treated as intent,
 * so Account Options language is not overwritten on every login.
 *
 * In-game `?lang=` is a display-only override for the current request; persistence
 * stays on the tokenised Settings POST.
 */
class UserLanguageService
{
	/**
	 * Whether the lobby language was chosen intentionally on this page view
	 * (`?lang=` from a flag click, or the posted `lang_explicit=1` marker).
	 *
	 * Accept-Language and the lang cookie are never treated as intent, so a
	 * later Account Options choice is not overwritten on every login.
	 *
	 * @param array<string, mixed>|null $request
	 * @param array<string, mixed>|null $cookie
	 * @param array<string, mixed>|null $get
	 */
	public static function hasExplicitLobbyLanguageChoice(
		?array $request = null,
		?array $cookie = null,
		?array $get = null
	): bool {
		$request ??= $_REQUEST;
		$cookie ??= $_COOKIE;
		$get ??= $_GET;

		if (isset($get['lang']) && (string) $get['lang'] !== '') {
			return true;
		}

		if (isset($request['lang_explicit']) && (string) $request['lang_explicit'] === '1') {
			return true;
		}

		return false;
	}

	/**
	 * Resolve an allowed language from request and/or cookie (lobby plumbing).
	 *
	 * Priority: explicit `lang` request param, then (optionally) `lang` cookie.
	 *
	 * @param array<string, mixed>|null $request
	 * @param array<string, mixed>|null $cookie
	 * @param list<string>|null $allowed
	 */
	public static function resolveFromRequest(
		?array $request = null,
		?array $cookie = null,
		bool $includeCookie = true,
		?array $allowed = null
	): ?string {
		$request ??= $_REQUEST;
		$cookie ??= $_COOKIE;
		$allowed ??= Language::getAllowedLangs();
		$allowedLookup = array_fill_keys($allowed, true);

		$fromRequest = isset($request['lang']) ? (string) $request['lang'] : '';
		if ($fromRequest !== '' && isset($allowedLookup[$fromRequest])) {
			return $fromRequest;
		}

		if ($includeCookie) {
			$fromCookie = isset($cookie['lang']) ? (string) $cookie['lang'] : '';
			if ($fromCookie !== '' && isset($allowedLookup[$fromCookie])) {
				return $fromCookie;
			}
		}

		return null;
	}

	/**
	 * Pick a valid account language for registration (form → lobby cookie → default).
	 *
	 * @param array<string, mixed>|null $request
	 * @param array<string, mixed>|null $cookie
	 * @param list<string>|null $allowed
	 */
	public static function resolveForRegister(
		string $candidate,
		string $default,
		?array $request = null,
		?array $cookie = null,
		?array $allowed = null
	): string {
		$allowed ??= Language::getAllowedLangs();
		$allowedLookup = array_fill_keys($allowed, true);

		if ($candidate !== '' && isset($allowedLookup[$candidate])) {
			return $candidate;
		}

		$resolved = self::resolveFromRequest($request, $cookie, true, $allowed);
		if ($resolved !== null) {
			return $resolved;
		}

		if ($default !== '' && isset($allowedLookup[$default])) {
			return $default;
		}

		return isset($allowedLookup['en']) ? 'en' : ($allowed[0] ?? 'en');
	}

	/**
	 * Write language to users.lang and keep the lobby lang cookie aligned.
	 */
	public static function applyToUser(int $userId, string $lang, ?string $currentLang = null, ?DatabaseInterface $db = null): bool
	{
		if ($userId <= 0 || !in_array($lang, Language::getAllowedLangs(), true)) {
			return false;
		}

		if ($currentLang !== null && $currentLang === $lang) {
			return false;
		}

		HTTP::sendCookie('lang', $lang, 2147483647);

		($db ?? Database::get())->update(
			'UPDATE %%USERS%% SET lang = :lang WHERE id = :userId;',
			[
				':lang'   => $lang,
				':userId' => $userId,
			]
		);

		return true;
	}

	/**
	 * After login: copy intentional lobby language onto the account.
	 *
	 * Requires an explicit choice marker (`?lang=` or posted `lang_explicit=1`).
	 * Never treats Accept-Language or the lang cookie as intent.
	 *
	 * @param array<string, mixed>|null $request
	 * @param array<string, mixed>|null $cookie
	 * @param array<string, mixed>|null $get
	 */
	public static function syncFromLobbyOnLogin(
		int $userId,
		?string $currentLang = null,
		?DatabaseInterface $db = null,
		?array $request = null,
		?array $cookie = null,
		?array $get = null
	): ?string {
		$request ??= $_REQUEST;
		$cookie ??= $_COOKIE;
		$get ??= $_GET;

		if (!self::hasExplicitLobbyLanguageChoice($request, $cookie, $get)) {
			return null;
		}

		$lang = self::resolveFromRequest($request, $cookie, true);
		if ($lang === null) {
			return null;
		}

		self::applyToUser($userId, $lang, $currentLang, $db);

		return $lang;
	}

	/**
	 * In-game: honor explicit `?lang=` as a display-only override for this request.
	 * Does not write users.lang (Settings POST owns persistence) and ignores the
	 * lang cookie so mid-session preference stays stable.
	 *
	 * @param array<string, mixed> $user
	 * @param array<string, mixed>|null $request
	 */
	public static function applyExplicitRequestToUser(
		array &$user,
		?DatabaseInterface $db = null,
		?array $request = null
	): bool {
		$lang = self::resolveFromRequest($request, null, false);
		if ($lang === null || !isset($user['id'])) {
			return false;
		}

		$current = isset($user['lang']) && is_string($user['lang']) ? $user['lang'] : null;
		if ($current === $lang) {
			return false;
		}

		// Display-only for this request; $db intentionally unused (Settings POST persists).
		$user['lang'] = $lang;

		return true;
	}
}
