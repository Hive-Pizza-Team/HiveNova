<?php

namespace HiveNova\Core;

/**
 * First-session Tech Tree tip on Buildings / Research / Shipyard.
 * Cookie-only — no DB migration. Same cookie covers dismiss and first visit.
 */
class TechTreeNudgeService
{
	public const COOKIE = 'hn_techtree_seen';
	public const COOKIE_VALUE = '1';
	public const TTL_SECONDS = 365 * 86400;

	/** @var list<string> */
	public const PAGES = ['buildings', 'research', 'shipyard'];

	/**
	 * @param array<int|string, mixed> $cookies
	 */
	public static function isSeen(array $cookies): bool
	{
		return (string) ($cookies[self::COOKIE] ?? '') === self::COOKIE_VALUE;
	}

	/**
	 * @param array<int|string, mixed> $cookies
	 */
	public static function shouldShow(array $cookies, string $page): bool
	{
		if (self::isSeen($cookies)) {
			return false;
		}

		return in_array($page, self::PAGES, true);
	}

	/**
	 * Options for a JS-readable cookie (dismiss happens in the browser).
	 *
	 * @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string}
	 */
	public static function cookieOptions(int $now, bool $secure = false): array
	{
		return [
			'expires'  => $now + self::TTL_SECONDS,
			'path'     => '/',
			'secure'   => $secure,
			'httponly' => false,
			'samesite' => 'Lax',
		];
	}

	public static function isSecureRequest(): bool
	{
		if (defined('HTTPS')) {
			return (bool) HTTPS;
		}

		return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
	}
}
