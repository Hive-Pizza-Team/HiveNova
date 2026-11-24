<?php

namespace HiveNova\Core;

/**
 * Admin-panel re-login password check.
 *
 * Game passwords are hashed after HTTP::_GP() (htmlspecialchars + trim).
 * The original admin form verified the raw $_REQUEST value, so passwords
 * containing & < > " ' or surrounding whitespace always failed re-auth.
 * Accept the normalized value first, then the raw value as a fallback so
 * hashes created either way still work.
 */
class AdminReloginPassword
{
	public static function matches(string $storedHash): bool
	{
		$normalized = (string) HTTP::_GP('admin_pw', '', true);
		if ($normalized !== '' && password_verify($normalized, $storedHash)) {
			return true;
		}

		$raw = isset($_REQUEST['admin_pw']) ? (string) $_REQUEST['admin_pw'] : '';
		return $raw !== '' && password_verify($raw, $storedHash);
	}
}
