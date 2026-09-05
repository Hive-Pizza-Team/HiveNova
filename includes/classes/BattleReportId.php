<?php

namespace HiveNova\Core;

/**
 * Primary keys for %%RW%% battle reports.
 *
 * Legacy IDs are 32-char MD5 hex strings. New IDs use 128-bit random bytes
 * encoded as unpadded base64url (22 ASCII characters).
 */
class BattleReportId
{
	public const LEGACY_HEX_LENGTH = 32;
	public const NEW_ID_BYTE_LENGTH = 16;
	public const NEW_ID_LENGTH = 22;

	public static function generate(): string
	{
		$encoded = base64_encode(random_bytes(self::NEW_ID_BYTE_LENGTH));

		return rtrim(strtr($encoded, '+/', '-_'), '=');
	}

	public static function isValid(string $id): bool
	{
		$id = trim($id);
		if ($id === '') {
			return false;
		}

		if (strlen($id) === self::LEGACY_HEX_LENGTH && ctype_xdigit($id)) {
			return true;
		}

		return strlen($id) === self::NEW_ID_LENGTH
			&& preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
	}
}
