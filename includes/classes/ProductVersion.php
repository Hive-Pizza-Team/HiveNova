<?php

namespace HiveNova\Core;

/**
 * Product version shown to players and admins.
 * The file matches the SemVer release tag and the newest changelog/vX.Y.Z.md.
 * DB_VERSION_REQUIRED is the schema counter and is not this value.
 */
final class ProductVersion
{
	public static function current(?string $root = null): string
	{
		$root = $root ?? (defined('ROOT_PATH') ? ROOT_PATH : '');
		$path = rtrim($root, '/\\') . '/install/VERSION';
		if ($path === '/install/VERSION' || !is_file($path)) {
			return '';
		}

		return trim((string) file_get_contents($path));
	}

	public static function displayed(?string $stored = null, ?string $root = null): string
	{
		$fromFile = self::current($root);
		if ($fromFile !== '') {
			return $fromFile;
		}

		return trim((string) $stored);
	}
}
