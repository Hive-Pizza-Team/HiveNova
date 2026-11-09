<?php

namespace HiveNova\Core;

/**
 * Persist the last successful login username in a long-lived cookie so the lobby
 * form can prefill after session expiry. Passwords are never stored.
 *
 * Kinds match scripts/login/remember-username.js ("email" = password-login name,
 * "hive" = Keychain account). Cookie values are mirrored into localStorage by JS
 * when the field is non-empty on load.
 */
class RememberUsernameService
{
	public const COOKIE_EMAIL = 'hn_last_username';
	public const COOKIE_HIVE = 'hn_last_hive_username';
	public const KIND_EMAIL = 'email';
	public const KIND_HIVE = 'hive';
	public const TTL_SECONDS = 365 * 86400;
	public const MAX_LENGTH = 25;

	/** @var callable(string, string, int): void */
	private $cookieWriter;

	/**
	 * @param callable(string $name, string $value, int $expire): void|null $cookieWriter
	 */
	public function __construct(?callable $cookieWriter = null)
	{
		$this->cookieWriter = $cookieWriter ?? static function (string $name, string $value, int $expire): void {
			HTTP::sendCookie($name, $value, $expire);
		};
	}

	public static function normalizeKind(string $kind): string
	{
		return ($kind === self::KIND_HIVE || $kind === self::KIND_EMAIL) ? $kind : '';
	}

	public static function cookieName(string $kind): string
	{
		return self::normalizeKind($kind) === self::KIND_HIVE
			? self::COOKIE_HIVE
			: self::COOKIE_EMAIL;
	}

	public static function normalizeUsername(string $kind, string $username): string
	{
		$kind = self::normalizeKind($kind);
		if ($kind === '') {
			return '';
		}

		$value = trim($username);
		if ($kind === self::KIND_HIVE) {
			$value = strtolower($value);
		}

		$value = self::clip($value);
		if ($value === '' || !PlayerUtil::isNameValid($value)) {
			return '';
		}

		return $value;
	}

	/**
	 * Password form posts username only; Keychain also posts hiveAccount.
	 *
	 * @param array<string, mixed> $post
	 */
	public static function kindFromLoginRequest(array $post): string
	{
		$hiveAccount = trim((string) ($post['hiveAccount'] ?? ''));

		return $hiveAccount !== '' ? self::KIND_HIVE : self::KIND_EMAIL;
	}

	/**
	 * @param array<string|int, mixed> $cookies
	 */
	public static function read(array $cookies, string $kind): string
	{
		$kind = self::normalizeKind($kind);
		if ($kind === '') {
			return '';
		}

		return self::normalizeUsername($kind, (string) ($cookies[self::cookieName($kind)] ?? ''));
	}

	/**
	 * @return array{email: string, hive: string}
	 *
	 * @param array<string|int, mixed> $cookies
	 */
	public static function readAll(array $cookies): array
	{
		return [
			self::KIND_EMAIL => self::read($cookies, self::KIND_EMAIL),
			self::KIND_HIVE  => self::read($cookies, self::KIND_HIVE),
		];
	}

	public function persist(string $kind, string $username, ?int $now = null): bool
	{
		$kind = self::normalizeKind($kind);
		$value = self::normalizeUsername($kind, $username);
		if ($kind === '' || $value === '') {
			return false;
		}

		$expire = ($now ?? (defined('TIMESTAMP') ? (int) TIMESTAMP : time())) + self::TTL_SECONDS;
		($this->cookieWriter)(self::cookieName($kind), $value, $expire);

		return true;
	}

	private static function clip(string $value): string
	{
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($value, 'UTF-8') > self::MAX_LENGTH) {
				return (string) mb_substr($value, 0, self::MAX_LENGTH, 'UTF-8');
			}

			return $value;
		}

		if (strlen($value) > self::MAX_LENGTH) {
			return substr($value, 0, self::MAX_LENGTH);
		}

		return $value;
	}
}
