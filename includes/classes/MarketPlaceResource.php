<?php

namespace HiveNova\Core;

/**
 * Maps marketplace ex_resource_type (1–3) onto tech IDs 901–903.
 */
class MarketPlaceResource
{
	public static function techId(int $exResourceType): ?int
	{
		return match ($exResourceType) {
			1 => 901,
			2 => 902,
			3 => 903,
			default => null,
		};
	}

	/**
	 * @param array<int|string, string> $techNames $LNG['tech']
	 */
	public static function label(int $exResourceType, array $techNames): string
	{
		$id = self::techId($exResourceType);
		if ($id === null || !isset($techNames[$id])) {
			return '';
		}

		return (string) $techNames[$id];
	}

	/**
	 * @return array{901: int|float, 902: int|float, 903: int|float}
	 */
	public static function amounts(int $exResourceType, $amount): array
	{
		return [
			901 => $exResourceType === 1 ? $amount : 0,
			902 => $exResourceType === 2 ? $amount : 0,
			903 => $exResourceType === 3 ? $amount : 0,
		];
	}

	public static function historyLimit(?int $requested = null): int
	{
		$limit = $requested ?? MARKET_TRADE_HISTORY_LIMIT;
		return max(1, min(200, $limit));
	}
}
