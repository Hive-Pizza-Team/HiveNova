<?php

namespace HiveNova\Core;

use Throwable;

/**
 * File + memory cache for linked-wallet Hive Power and staked PIZZA.
 *
 * Successful lookups stay fresh for TTL_SECONDS. A failed refresh keeps the
 * last good value until STALE_SECONDS, then omits it. Repeated failures cool
 * down for FAIL_TTL_SECONDS so a dead API does not stall every profile view.
 */
class PrestigeChainCache
{
	public const TTL_SECONDS = 1800;
	public const STALE_SECONDS = 21600;
	public const FAIL_TTL_SECONDS = 60;

	/** @var array<string, array<string, mixed>> */
	private static array $memory = [];

	public static function reset(): void
	{
		self::$memory = [];
	}

	public static function defaultCacheDir(): string
	{
		$root = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir().'/';

		return rtrim($root, '/').'/prestige-chain/';
	}

	/**
	 * @param callable(): array{hp?: float|int|null, pizza?: float|int|null} $fetch
	 * @return array{hp: float|null, pizza: float|null}
	 */
	public static function resolve(string $account, callable $fetch, ?string $cacheDir = null, ?int $now = null): array
	{
		$now ??= time();
		$account = strtolower(trim($account));
		$cacheDir ??= self::defaultCacheDir();
		$record = self::load($account, $cacheDir);

		$hpHold = self::isFresh($record, 'hp', $now) || self::isCoolingDown($record, 'hp', $now);
		$pizzaHold = self::isFresh($record, 'pizza', $now) || self::isCoolingDown($record, 'pizza', $now);

		if (!$hpHold || !$pizzaHold) {
			$fetched = self::callFetch($fetch);
			if (!$hpHold) {
				$record = self::apply($record, 'hp', $fetched['hp'] ?? null, $now);
			}
			if (!$pizzaHold) {
				$record = self::apply($record, 'pizza', $fetched['pizza'] ?? null, $now);
			}
			self::store($account, $cacheDir, $record);
		}

		return [
			'hp'    => self::readable($record, 'hp', $now),
			'pizza' => self::readable($record, 'pizza', $now),
		];
	}

	/**
	 * @param callable(): mixed $fetch
	 * @return array{hp: mixed, pizza: mixed}
	 */
	private static function callFetch(callable $fetch): array
	{
		try {
			$fetched = $fetch();
		} catch (Throwable) {
			return ['hp' => null, 'pizza' => null];
		}

		if (!is_array($fetched)) {
			return ['hp' => null, 'pizza' => null];
		}

		return $fetched;
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array<string, mixed>
	 */
	private static function apply(array $record, string $metric, mixed $value, int $now): array
	{
		$amount = self::amount($value);
		if ($amount === null) {
			$record[$metric.'_fail_at'] = $now;

			return $record;
		}

		$record[$metric] = $amount;
		$record[$metric.'_at'] = $now;
		unset($record[$metric.'_fail_at']);

		return $record;
	}

	/**
	 * @param array<string, mixed> $record
	 */
	private static function isFresh(array $record, string $metric, int $now): bool
	{
		if (!array_key_exists($metric, $record) || !isset($record[$metric.'_at'])) {
			return false;
		}

		$age = $now - (int) $record[$metric.'_at'];

		return $age >= -5 && $age < self::TTL_SECONDS;
	}

	/**
	 * @param array<string, mixed> $record
	 */
	private static function isCoolingDown(array $record, string $metric, int $now): bool
	{
		if (!isset($record[$metric.'_fail_at'])) {
			return false;
		}

		$age = $now - (int) $record[$metric.'_fail_at'];

		return $age >= 0 && $age < self::FAIL_TTL_SECONDS;
	}

	/**
	 * @param array<string, mixed> $record
	 */
	private static function readable(array $record, string $metric, int $now): ?float
	{
		$amount = self::amount($record[$metric] ?? null);
		if ($amount === null || !isset($record[$metric.'_at'])) {
			return null;
		}

		$age = $now - (int) $record[$metric.'_at'];
		if ($age < -5 || $age >= self::STALE_SECONDS) {
			return null;
		}

		return $amount;
	}

	private static function amount(mixed $value): ?float
	{
		if (is_bool($value) || (!is_int($value) && !is_float($value))) {
			return null;
		}

		$number = (float) $value;

		return is_finite($number) ? $number : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function load(string $account, string $cacheDir): array
	{
		if ($account === '') {
			return [];
		}

		if (isset(self::$memory[$account]) && is_array(self::$memory[$account])) {
			return self::$memory[$account];
		}

		$path = self::filePath($cacheDir, $account);
		if (!is_file($path)) {
			return [];
		}

		$raw = @file_get_contents($path);
		if (!is_string($raw) || $raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		self::$memory[$account] = $decoded;

		return $decoded;
	}

	/**
	 * @param array<string, mixed> $record
	 */
	private static function store(string $account, string $cacheDir, array $record): void
	{
		if ($account === '') {
			return;
		}

		self::$memory[$account] = $record;
		if ($cacheDir === '' || !self::ensureDir($cacheDir)) {
			return;
		}

		$payload = json_encode($record);
		if (!is_string($payload)) {
			return;
		}

		@file_put_contents(self::filePath($cacheDir, $account), $payload, LOCK_EX);
	}

	private static function ensureDir(string $cacheDir): bool
	{
		if (is_dir($cacheDir)) {
			return true;
		}

		return @mkdir($cacheDir, 0777, true) || is_dir($cacheDir);
	}

	private static function filePath(string $cacheDir, string $account): string
	{
		return rtrim($cacheDir, '/').'/'.hash('sha256', $account).'.json';
	}
}
