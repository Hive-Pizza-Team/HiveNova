<?php

namespace HiveNova\Core;

/**
 * Detects whether /uniN/ path routing is in place before creating another universe.
 *
 * Apache with REQUEST_URI preserved often 302s /uni1/ back to the unprefixed URL.
 * Caddy and nginx typically rewrite internally and return 200. The old check
 * required exactly 302, so a working Caddy setup was treated as unconfigured.
 */
class UniverseRewriteProbe
{
	/**
	 * Wildcard subdomains (uni1.example.com) do not use /uniN/ path rewrites.
	 * Two or more universes already prove the install can route them.
	 */
	public static function isRequired(int $universeCount, bool $wildcardSubdomains): bool
	{
		if ($wildcardSubdomains) {
			return false;
		}

		return $universeCount < 2;
	}

	public static function url(string $protocol, string $host, string $base, int $rootUni): string
	{
		return $protocol.$host.$base.'uni'.$rootUni.'/';
	}

	/**
	 * After following HTTPS/canonical redirects, a configured rewrite serves the
	 * application (200). 404 means /uniN/ is not routed.
	 */
	public static function rewriteLooksConfigured(int $finalHttpCode): bool
	{
		return $finalHttpCode === 200;
	}

	/**
	 * @param callable(string):int|null $fetcher
	 */
	public static function fetchStatus(string $url, ?callable $fetcher = null): int
	{
		if ($fetcher !== null) {
			return (int) $fetcher($url);
		}

		if (!function_exists('curl_init')) {
			return 0;
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_HTTPGET => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_USERAGENT => 'HiveNova-UniverseRewriteProbe',
		]);
		curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $code;
	}
}
