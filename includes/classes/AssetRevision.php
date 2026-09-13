<?php

namespace HiveNova\Core;

/**
 * Cache-buster for CSS/JS query strings. VERSION stays at 2.0 across deploys,
 * so {$REV} also tracks mtime of assets loaded as ?v={$REV}.
 */
class AssetRevision
{
	/**
	 * Paths relative to ROOT_PATH whose mtime feeds {$REV}.
	 * JS-only deploys must be listed so browsers/CDN do not keep a stale
	 * versioned body. Include every deferred loadscript() page file that
	 * changes often — {$REV} is the only cache-buster on those tags.
	 *
	 * @return list<string>
	 */
	public static function fingerprintPaths(): array
	{
		return [
			'styles/resource/css/ingame/main.css',
			'scripts/game/push-subscribe.js',
			'scripts/game/base.js',
			'scripts/game/pwa-install.js',
			'scripts/game/message.js',
			'scripts/game/battlesim.js',
			'scripts/game/search.js',
			'scripts/game/flotten.js',
			'scripts/game/galaxy.js',
			'scripts/game/overview.js',
		];
	}

	public static function forVersion(string $version, ?int $assetMtime = null): string
	{
		$rev = substr($version, -4);
		if ($assetMtime !== null && $assetMtime > 0) {
			return $rev . '.' . $assetMtime;
		}

		return $rev;
	}

	public static function fromFilesystem(string $version, string $rootPath = ROOT_PATH): string
	{
		return self::forVersion($version, self::maxAssetMtime($rootPath));
	}

	public static function maxAssetMtime(string $rootPath = ROOT_PATH): ?int
	{
		$mtime = 0;
		foreach (self::fingerprintPaths() as $relative) {
			$path = $rootPath . $relative;
			if (is_file($path)) {
				$mtime = max($mtime, (int) filemtime($path));
			}
		}

		return $mtime > 0 ? $mtime : null;
	}
}
