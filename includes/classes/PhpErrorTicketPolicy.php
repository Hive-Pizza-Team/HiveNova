<?php

namespace HiveNova\Core;

/**
 * Gate for the legacy "debug via Support ticket" path in exceptionHandler.
 *
 * PHP warnings/notices and admin-panel diagnostics belong in error.log.
 * They must not open player Support tickets (subject WARNING, etc.).
 */
class PhpErrorTicketPolicy
{
	public const DEDUPE_WINDOW_SECONDS = 3600;

	/**
	 * Severities that are diagnostics only — never a Support ticket.
	 */
	public static function isDiagnosticOnly(int $errno): bool
	{
		$mask = E_WARNING | E_NOTICE | E_CORE_WARNING | E_COMPILE_WARNING
			| E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED;

		if (defined('E_STRICT')) {
			$mask |= (int) constant('E_STRICT');
		}

		return ($errno & $mask) !== 0;
	}

	/**
	 * Whether exceptionHandler may open a Support ticket for this fault.
	 *
	 * Admin mode is always log-only. Warnings/notices are always log-only.
	 * Remaining fatals in player-facing modes may still ticket, subject to
	 * {@see claimFingerprint()} rate-limiting.
	 */
	public static function shouldOpenSupportTicket(int $errno, string $mode): bool
	{
		if (self::isDiagnosticOnly($errno)) {
			return false;
		}

		if (strcasecmp($mode, 'ADMIN') === 0) {
			return false;
		}

		return true;
	}

	public static function fingerprint(int $errno, string $file, int $line, string $message): string
	{
		return hash('sha256', $errno . "\0" . $file . "\0" . $line . "\0" . $message);
	}

	/**
	 * File-backed dedupe. Returns true once per fingerprint per window.
	 *
	 * Fail-open on unreadable cache so a real fatal can still notify;
	 * fail-closed on lock/write errors to avoid a ticket flood.
	 */
	public static function claimFingerprint(
		string $fingerprint,
		int $now,
		string $cacheFile,
		int $windowSeconds = self::DEDUPE_WINDOW_SECONDS
	): bool {
		if ($fingerprint === '' || $cacheFile === '') {
			return false;
		}

		$dir = dirname($cacheFile);
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			return false;
		}

		$handle = @fopen($cacheFile, 'c+');
		if ($handle === false) {
			return false;
		}

		try {
			if (!flock($handle, LOCK_EX)) {
				return false;
			}

			$raw = stream_get_contents($handle);
			$seen = [];
			if (is_string($raw) && $raw !== '') {
				$decoded = json_decode($raw, true);
				if (is_array($decoded)) {
					$seen = $decoded;
				}
			}

			$cutoff = $now - $windowSeconds;
			$fresh = [];
			foreach ($seen as $key => $timestamp) {
				if (!is_string($key) || !is_int($timestamp) && !is_float($timestamp) && !is_string($timestamp)) {
					continue;
				}
				$ts = (int) $timestamp;
				if ($ts >= $cutoff) {
					$fresh[$key] = $ts;
				}
			}

			if (isset($fresh[$fingerprint]) && $fresh[$fingerprint] >= $cutoff) {
				return false;
			}

			$fresh[$fingerprint] = $now;
			$encoded = json_encode($fresh);
			if ($encoded === false) {
				return false;
			}

			rewind($handle);
			if (ftruncate($handle, 0) === false) {
				return false;
			}
			if (fwrite($handle, $encoded) === false) {
				return false;
			}

			return true;
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}
}
