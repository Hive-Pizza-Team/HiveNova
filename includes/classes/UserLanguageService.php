<?php

namespace HiveNova\Core;

/**
 * Persist lobby / explicit language choice onto the user record used by in-game UI.
 *
 * In-game pages read `$USER['lang']`, while the lobby uses `?lang=` + the `lang`
 * cookie. This service bridges those on login/register and when `?lang=` is set
 * on game.php. Cookie alone is not applied while already in-game, so an existing
 * account preference is not overwritten by a stale lobby cookie mid-session.
 */
class UserLanguageService
{
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
	 * After login: copy lobby language (`?lang=` / lang cookie) onto the account.
	 *
	 * @param array<string, mixed>|null $request
	 * @param array<string, mixed>|null $cookie
	 */
	public static function syncFromLobbyOnLogin(
		int $userId,
		?string $currentLang = null,
		?DatabaseInterface $db = null,
		?array $request = null,
		?array $cookie = null
	): ?string {
		$lang = self::resolveFromRequest($request, $cookie, true);
		if ($lang === null) {
			return null;
		}

		self::applyToUser($userId, $lang, $currentLang, $db);

		return $lang;
	}

	/**
	 * In-game: honor explicit `?lang=` by updating the loaded user row and DB.
	 * Does not use the lang cookie, so mid-session preference stays stable.
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
		if (!self::applyToUser((int) $user['id'], $lang, $current, $db)) {
			return false;
		}

		$user['lang'] = $lang;

		return true;
	}
}
