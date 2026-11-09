<?php

namespace HiveNova\Core;

/**
 * Persist lobby / explicit language choice onto the user record used by in-game UI.
 */
class UserLanguageService
{
	/**
	 * Resolve an allowed language from request and/or cookie (lobby plumbing).
	 *
	 * @param array<string, mixed>|null $request
	 * @param array<string, mixed>|null $cookie
	 */
	public static function resolveFromRequest(?array $request = null, ?array $cookie = null, bool $includeCookie = true): ?string
	{
		$request ??= $_REQUEST;
		$cookie ??= $_COOKIE;
		$allowed = Language::getAllowedLangs();

		if (isset($request['lang']) && is_string($request['lang']) && in_array($request['lang'], $allowed, true)) {
			return $request['lang'];
		}

		if ($includeCookie && isset($cookie['lang']) && is_string($cookie['lang']) && in_array($cookie['lang'], $allowed, true)) {
			return $cookie['lang'];
		}

		return null;
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
	 * After login: copy lobby language (cookie / ?lang=) onto the account.
	 */
	public static function syncFromLobbyOnLogin(int $userId, ?string $currentLang = null, ?DatabaseInterface $db = null): ?string
	{
		$lang = self::resolveFromRequest(includeCookie: true);
		if ($lang === null) {
			return null;
		}

		self::applyToUser($userId, $lang, $currentLang, $db);

		return $lang;
	}

	/**
	 * In-game: honor explicit ?lang= by updating the loaded user row and DB.
	 *
	 * @param array<string, mixed> $user
	 */
	public static function applyExplicitRequestToUser(array &$user, ?DatabaseInterface $db = null): bool
	{
		$lang = self::resolveFromRequest(includeCookie: false);
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
