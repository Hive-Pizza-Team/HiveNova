<?php

namespace HiveNova\Core;

/**
 * CSRF tokens for What's New dismiss (session key isolated from Commander).
 */
class WhatsNewCsrf
{
	public const SESSION_KEY = 'whats_new_csrf';

	public static function issueCsrfToken(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE && session_status() !== PHP_SESSION_DISABLED) {
			@session_start();
		}
		$token = bin2hex(random_bytes(16));
		$_SESSION[self::SESSION_KEY] = $token;

		return $token;
	}

	public static function validateCsrfToken(?string $token): bool
	{
		$expected = $_SESSION[self::SESSION_KEY] ?? '';
		if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
			return false;
		}

		return hash_equals($expected, $token);
	}
}
