<?php

namespace HiveNova\Core;

/**
 * CSRF protection for the admin panel.
 *
 * Uses a dedicated session token submitted as `admin_csrf` (POST or GET).
 */
class AdminCsrf
{
	public const SESSION_KEY = 'admin_csrf';
	public const FIELD = 'admin_csrf';

	/** Admin pages that mutate state on GET without a POST body. */
	private const MUTATING_GET_PAGES = [];

	public static function token(): string
	{
		self::ensureSession();

		$token = $_SESSION[self::SESSION_KEY] ?? '';
		if (!is_string($token) || $token === '') {
			$token = bin2hex(random_bytes(16));
			$_SESSION[self::SESSION_KEY] = $token;
		}

		return $token;
	}

	public static function isValid(?string $token): bool
	{
		if (!is_string($token) || $token === '') {
			return false;
		}

		$expected = self::token();
		return hash_equals($expected, $token);
	}

	public static function requestToken(): string
	{
		$candidates = [
			$_POST[self::FIELD] ?? null,
			$_GET[self::FIELD] ?? null,
		];

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && $candidate !== '') {
				return $candidate;
			}
		}

		return '';
	}

	public static function isValidRequest(): bool
	{
		return self::isValid(self::requestToken());
	}

	public static function isMutatingRequest(string $page): bool
	{
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		if ($method === 'POST') {
			return true;
		}

		if ($method !== 'GET') {
			return false;
		}

		return in_array($page, self::MUTATING_GET_PAGES, true);
	}

	public static function rejectInvalid(): never
	{
		global $LNG;

		$message = $LNG['ad_csrf_invalid'] ?? 'Invalid security token. Reload the admin page and try again.';
		$template = new Template();
		$template->message($message, 'admin.php?page=overview', 3, true);
		exit;
	}

	public static function enforce(string $page): void
	{
		if (!self::isMutatingRequest($page)) {
			return;
		}

		if (!self::isValidRequest()) {
			self::rejectInvalid();
		}
	}

	private static function ensureSession(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}

		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
	}
}
