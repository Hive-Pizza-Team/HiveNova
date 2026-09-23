<?php

namespace HiveNova\Core;

/**
 * Screenshot gallery alt text for the public screens page.
 */
class PublicScreensService
{
	/**
	 * Language key for a screenshot filename (`galaxy.jpg` → `screenAltGalaxy`).
	 */
	public static function altKey(string $filename): string
	{
		$base = strtolower((string) pathinfo($filename, PATHINFO_FILENAME));
		$base = preg_replace('/[^a-z0-9]+/', '', $base) ?? '';

		return $base === '' ? 'screenAltScreenshot' : 'screenAlt'.ucfirst($base);
	}

	/**
	 * Descriptive alt; falls back to a cleaned filename when the key is missing.
	 *
	 * @param array<string, string>|object $LNG
	 */
	public static function altText(string $filename, $LNG): string
	{
		$key = self::altKey($filename);
		if (is_array($LNG) && isset($LNG[$key]) && (string) $LNG[$key] !== '') {
			return (string) $LNG[$key];
		}
		if (is_object($LNG) && isset($LNG[$key]) && (string) $LNG[$key] !== '') {
			return (string) $LNG[$key];
		}

		$base = (string) pathinfo($filename, PATHINFO_FILENAME);
		$base = trim(str_replace(['-', '_'], ' ', $base));

		return $base !== '' ? ucfirst($base) : 'Screenshot';
	}
}
