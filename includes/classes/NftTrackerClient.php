<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Soft-fail HTTP client for HAF nft_tracker PostgREST reads (#628 / #630).
 *
 * Live public nodes may still be early; failures omit verification rather than
 * blocking the inventory page.
 */
class NftTrackerClient
{
	public const DEFAULT_BASE_URL = 'https://api.hive.blog/nft-tracker-api';

	/** @var callable|null fn(string $url): string|false */
	private static $fetcher = null;

	public function __construct(
		private readonly string $baseUrl = self::DEFAULT_BASE_URL,
	) {
	}

	public static function setFetcher(?callable $fetcher): void
	{
		self::$fetcher = $fetcher;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function nftsForHolder(string $namespace, string $symbol, string $holder): array
	{
		$namespace = strtolower(trim($namespace));
		$symbol = strtoupper(trim($symbol));
		$holder = strtolower(trim($holder));
		if ($namespace === '' || $symbol === '' || $holder === '' || !HiveUtil::isAccountValid($holder)) {
			return [];
		}
		if (!preg_match('/^[a-z][a-z0-9]{0,15}$/', $namespace)) {
			return [];
		}
		if (!preg_match('/^[A-Z][A-Z0-9]{0,9}$/', $symbol)) {
			return [];
		}

		$url = rtrim($this->baseUrl, '/')
			.'/nfts/'.rawurlencode($namespace).'/'.rawurlencode($symbol)
			.'?holder='.rawurlencode($holder);

		$raw = $this->fetch($url);
		if ($raw === null) {
			return [];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		// PostgREST may wrap rows or return a bare list.
		if (isset($decoded['data']) && is_array($decoded['data'])) {
			$decoded = $decoded['data'];
		}
		if (!array_is_list($decoded)) {
			return [];
		}

		$out = [];
		foreach ($decoded as $row) {
			if (is_array($row)) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * True when the holder has at least one instance for the type.
	 */
	public function holderHasType(string $namespace, string $symbol, string $holder): ?bool
	{
		try {
			$rows = $this->nftsForHolder($namespace, $symbol, $holder);
		} catch (Throwable) {
			return null;
		}

		// Empty can mean none or API soft-fail; distinguish via fetch miss below.
		return $rows !== [];
	}

	private function fetch(string $url): ?string
	{
		try {
			$fn = self::$fetcher;
			if ($fn !== null) {
				$raw = $fn($url);
				return is_string($raw) && $raw !== '' ? $raw : null;
			}

			$ctx = stream_context_create([
				'http' => [
					'timeout'       => 6,
					'ignore_errors' => true,
					'header'        => "User-Agent: HiveNova\r\nAccept: application/json\r\n",
				],
			]);
			$raw = @file_get_contents($url, false, $ctx);

			return is_string($raw) && $raw !== '' ? $raw : null;
		} catch (Throwable) {
			return null;
		}
	}
}
