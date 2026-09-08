<?php

namespace HiveNova\Core;

/**
 * AUTH_ADM-only access for the universe admin page.
 *
 * Viewing does not require a session id in the query string. Mutations keep
 * the sid === session_id() CSRF check.
 */
class AdminUniverseAuth
{
	public static function canView(int $authlevel): bool
	{
		return $authlevel === AUTH_ADM;
	}

	public static function canMutate(int $authlevel, string $sid, string $sessionId): bool
	{
		if ($sessionId === '') {
			return false;
		}

		return self::canView($authlevel) && hash_equals($sessionId, $sid);
	}
}
