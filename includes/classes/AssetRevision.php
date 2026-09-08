<?php

namespace HiveNova\Core;

/**
 * Cache-buster for CSS/JS query strings. VERSION alone stays at 2.0 across CSS-only deploys.
 */
class AssetRevision
{
	public static function forVersion(string $version, ?int $stylesheetMtime = null): string
	{
		$rev = substr($version, -4);
		if ($stylesheetMtime !== null && $stylesheetMtime > 0) {
			return $rev . '.' . $stylesheetMtime;
		}

		return $rev;
	}

	public static function fromFilesystem(string $version, string $rootPath = ROOT_PATH): string
	{
		$css = $rootPath . 'styles/resource/css/ingame/main.css';
		$mtime = is_file($css) ? (int) filemtime($css) : 0;

		return self::forVersion($version, $mtime > 0 ? $mtime : null);
	}
}
