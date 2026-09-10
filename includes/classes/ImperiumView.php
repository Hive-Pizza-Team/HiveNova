<?php

namespace HiveNova\Core;

use ArrayAccess;

/**
 * Imperium / Empire overview presentation helpers (header energy, matrix JSON).
 */
class ImperiumView
{
	/**
	 * Available energy as shown in the top bar: produced + used (used is ≤ 0).
	 */
	public static function energyAvailable(float|int|string $energy, float|int|string $energyUsed): float
	{
		return (float) $energy + (float) $energyUsed;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function encodeMatrixJson(array $payload): string
	{
		$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
		if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}

		$json = json_encode($payload, $flags);

		return is_string($json) ? $json : '{"colspan":2,"planetIds":[],"sections":{}}';
	}

	/**
	 * @return array<int|string, string>
	 */
	public static function techNamesFromLanguage(mixed $lng): array
	{
		if (is_array($lng)) {
			$tech = $lng['tech'] ?? null;
			return is_array($tech) ? $tech : [];
		}

		if ($lng instanceof ArrayAccess && isset($lng['tech']) && is_array($lng['tech'])) {
			return $lng['tech'];
		}

		return [];
	}
}
