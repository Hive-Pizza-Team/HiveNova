<?php

namespace HiveNova\Core;

/**
 * Session-id gated access for sensitive admin pages (rights, reset).
 *
 * Failed checks must never bare-exit to an empty HTTP 200 body; use deny()
 * for a permission message that redirects back to the admin overview.
 */
class AdminPageSidAuth
{
	public static function sidMatches(string $sid, string $sessionId): bool
	{
		if ($sessionId === '') {
			return false;
		}

		return hash_equals($sessionId, $sid);
	}

	public static function canAccessAllowed(bool $allowedTo, string $sid, string $sessionId): bool
	{
		return $allowedTo && self::sidMatches($sid, $sessionId);
	}

	public static function canAccessRoot(int $userId, int $rootUserId, string $sid, string $sessionId): bool
	{
		return $userId === $rootUserId && self::sidMatches($sid, $sessionId);
	}

	public static function deny(): never
	{
		global $LNG;

		$message = $LNG['404_page'] ?? 'Permission error!';
		$template = new Template();
		$template->message($message, 'admin.php?page=overview', 3, true);
		exit;
	}
}
