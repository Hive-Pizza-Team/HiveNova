<?php

namespace HiveNova\Core;

class ApiCsrf
{
	public const SESSION_KEY = 'api_csrf';
	public const HEADER = 'HTTP_X_CSRF_TOKEN';

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

	public static function headerToken(): string
	{
		$raw = $_SERVER[self::HEADER] ?? '';
		return is_string($raw) ? $raw : '';
	}

	public static function isValidHeader(): bool
	{
		return self::isValid(self::headerToken());
	}

	public static function isCrossSiteFetch(): bool
	{
		$site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
		return $site === 'cross-site';
	}

	public static function enforceMutate(): void
	{
		if (self::isCrossSiteFetch()) {
			ApiJsonResponse::sendError('csrf', 403, 'Cross-site requests are not allowed.');
		}

		if (!self::isValidHeader()) {
			ApiJsonResponse::sendError('csrf', 403, 'Invalid security token.');
		}
	}

	private static function ensureSession(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}

		@session_start();
	}
}
