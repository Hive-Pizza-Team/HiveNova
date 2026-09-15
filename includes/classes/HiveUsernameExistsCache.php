<?php

namespace HiveNova\Core;

/**
 * Short-lived memory + file cache for Hive account existence lookups.
 *
 * Register username checks debounce in the browser but still hit the server
 * per keystroke burst; caching avoids repeating condenser_api.get_accounts.
 */
class HiveUsernameExistsCache
{
	public const TTL_SECONDS = 60;

	/** @var array<string, array{exists: bool, exp: int}> */
	private static array $memory = [];

	public static function reset(): void
	{
		self::$memory = [];
	}

	/**
	 * @param list<string> $names
	 * @param callable(list<string>): array<string, bool> $fetch
	 * @return array<string, bool> lowercase name => exists
	 */
	public static function lookup(array $names, callable $fetch, ?string $cacheDir = null, ?int $now = null): array
	{
		$now ??= time();
		$cacheDir ??= self::defaultCacheDir();

		$result = [];
		$missing = [];
		$seen = [];

		foreach ($names as $name) {
			$key = strtolower(trim((string) $name));
			if ($key === '' || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;

			if (isset(self::$memory[$key]) && self::$memory[$key]['exp'] > $now) {
				$result[$key] = self::$memory[$key]['exists'];
				continue;
			}

			$fromFile = self::readFile($cacheDir, $key, $now);
			if ($fromFile !== null) {
				self::$memory[$key] = $fromFile;
				$result[$key] = $fromFile['exists'];
				continue;
			}

			$missing[] = $key;
		}

		if ($missing === []) {
			return $result;
		}

		$fetched = $fetch($missing);
		if (!is_array($fetched)) {
			$fetched = [];
		}

		foreach ($missing as $key) {
			$exists = !empty($fetched[$key]);
			$entry = ['exists' => $exists, 'exp' => $now + self::TTL_SECONDS];
			self::$memory[$key] = $entry;
			self::writeFile($cacheDir, $key, $entry);
			$result[$key] = $exists;
		}

		return $result;
	}

	public static function defaultCacheDir(): string
	{
		$root = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . '/';

		return rtrim($root, '/').'/reg-hive-user/';
	}

	/**
	 * @return array{exists: bool, exp: int}|null
	 */
	private static function readFile(string $cacheDir, string $key, int $now): ?array
	{
		$path = self::filePath($cacheDir, $key);
		if (!is_file($path)) {
			return null;
		}

		$raw = @file_get_contents($path);
		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded) || !isset($decoded['t'])) {
			return null;
		}

		$exp = (int) $decoded['t'];
		if ($exp <= $now) {
			return null;
		}

		return ['exists' => !empty($decoded['e']), 'exp' => $exp];
	}

	/**
	 * @param array{exists: bool, exp: int} $entry
	 */
	private static function writeFile(string $cacheDir, string $key, array $entry): void
	{
		if ($cacheDir === '' || !self::ensureDir($cacheDir)) {
			return;
		}

		$path = self::filePath($cacheDir, $key);
		$payload = json_encode([
			'e' => $entry['exists'] ? 1 : 0,
			't' => $entry['exp'],
		]);
		if (!is_string($payload)) {
			return;
		}

		@file_put_contents($path, $payload, LOCK_EX);
	}

	private static function ensureDir(string $cacheDir): bool
	{
		if (is_dir($cacheDir)) {
			return true;
		}

		return @mkdir($cacheDir, 0777, true) || is_dir($cacheDir);
	}

	private static function filePath(string $cacheDir, string $key): string
	{
		return rtrim($cacheDir, '/').'/'.hash('sha256', $key).'.json';
	}
}
