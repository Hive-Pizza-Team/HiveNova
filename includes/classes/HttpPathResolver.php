<?php

namespace HiveNova\Core;

/**
 * Derive the application HTTP directory from SCRIPT_NAME, never REQUEST_URI.
 *
 * Front-controller rewrites (e.g. /lobby.php → index.php) leave REQUEST_URI as
 * /lobby.php while SCRIPT_FILENAME is index.php. Stripping the filename from
 * the URI then leaves HTTP_ROOT as /lobby.php and concatenates redirects as
 * /lobby.phpinstall/...
 */
class HttpPathResolver
{
	/**
	 * Directory of the executed script, always with a trailing slash.
	 * Root scripts yield '/'.
	 */
	public static function rootFromScriptName(string $scriptName): string
	{
		$normalized = str_replace('\\', '/', $scriptName);
		if ($normalized === '' || $normalized === '/') {
			return '/';
		}

		$dir = str_replace('\\', '/', dirname($normalized));
		if ($dir === '/' || $dir === '.' || $dir === '') {
			return '/';
		}

		return rtrim($dir, '/') . '/';
	}

	/**
	 * Join an HTTP base path with a relative URL. Absolute URLs (with a scheme)
	 * are returned unchanged. Always inserts a slash between base and relative
	 * so a mistaken filename in $httpPath cannot glue onto the next segment.
	 */
	public static function absolute(string $httpPath, string $relativeUrl): string
	{
		if (parse_url($relativeUrl, PHP_URL_SCHEME) !== null) {
			return $relativeUrl;
		}

		return rtrim($httpPath, '/') . '/' . ltrim($relativeUrl, '/');
	}
}
