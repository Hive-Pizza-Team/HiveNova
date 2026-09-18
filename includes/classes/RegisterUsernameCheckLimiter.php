<?php

namespace HiveNova\Core;

/**
 * Per-IP (and optional session) rate limit for register live username checks.
 *
 * 45 requests per 60-second window leaves headroom for the register form's
 * 300ms debounce while blocking username enumeration and Hive RPC amplification.
 * Fail-closed: I/O or lock errors deny the request so flood cannot skip DB/Hive.
 */
class RegisterUsernameCheckLimiter
{
	public const MAX_REQUESTS = 45;
	public const WINDOW_SECONDS = 60;

	private string $cacheDir;
	private int $maxRequests;
	private int $windowSeconds;

	public function __construct(
		?string $cacheDir = null,
		int $maxRequests = self::MAX_REQUESTS,
		int $windowSeconds = self::WINDOW_SECONDS
	) {
		$this->cacheDir = $cacheDir ?? self::defaultCacheDir();
		$this->maxRequests = max(1, $maxRequests);
		$this->windowSeconds = max(1, $windowSeconds);
	}

	public static function defaultCacheDir(): string
	{
		$root = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . '/';

		return rtrim($root, '/').'/reg-username-rl/';
	}

	/**
	 * Record one check. Denied when the IP (or session, if given) is over limit.
	 *
	 * @return array{allowed: bool, retryAfter: int}
	 */
	public function consume(string $ip, ?string $sessionId = null, ?int $now = null): array
	{
		$now ??= time();
		$ip = trim($ip);
		if ($ip === '') {
			return ['allowed' => false, 'retryAfter' => $this->windowSeconds];
		}

		$ipResult = $this->consumeKey('ip:'.$ip, $now);
		if (!$ipResult['allowed']) {
			return $ipResult;
		}

		$sessionId = is_string($sessionId) ? trim($sessionId) : '';
		if ($sessionId === '') {
			return $ipResult;
		}

		$sessionResult = $this->consumeKey('sid:'.$sessionId, $now);
		if (!$sessionResult['allowed']) {
			return $sessionResult;
		}

		return ['allowed' => true, 'retryAfter' => 0];
	}

	/**
	 * @return array{allowed: bool, retryAfter: int}
	 */
	private function consumeKey(string $identity, int $now): array
	{
		$denied = ['allowed' => false, 'retryAfter' => $this->windowSeconds];
		if ($this->cacheDir === '' || !$this->ensureDir($this->cacheDir)) {
			return $denied;
		}

		$path = rtrim($this->cacheDir, '/').'/'.hash('sha256', $identity).'.json';
		$handle = @fopen($path, 'c+');
		if ($handle === false) {
			return $denied;
		}

		try {
			if (!flock($handle, LOCK_EX)) {
				return $denied;
			}

			$raw = stream_get_contents($handle);
			$windowStart = $now;
			$count = 0;
			if (is_string($raw) && $raw !== '') {
				$decoded = json_decode($raw, true);
				if (is_array($decoded) && isset($decoded['w'], $decoded['n'])) {
					$windowStart = (int) $decoded['w'];
					$count = (int) $decoded['n'];
				}
			}

			if ($now - $windowStart >= $this->windowSeconds) {
				$windowStart = $now;
				$count = 0;
			}

			$retryAfter = max(1, $this->windowSeconds - ($now - $windowStart));
			if ($count >= $this->maxRequests) {
				return ['allowed' => false, 'retryAfter' => $retryAfter];
			}

			$payload = json_encode([
				'w' => $windowStart,
				'n' => $count + 1,
			]);
			if (!is_string($payload)) {
				return $denied;
			}

			rewind($handle);
			if (ftruncate($handle, 0) === false || fwrite($handle, $payload) === false) {
				return $denied;
			}

			return ['allowed' => true, 'retryAfter' => 0];
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	private function ensureDir(string $cacheDir): bool
	{
		if (is_dir($cacheDir)) {
			return true;
		}

		return @mkdir($cacheDir, 0755, true) || is_dir($cacheDir);
	}
}
