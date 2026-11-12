<?php

namespace HiveNova\Core;

/**
 * Shared grant row validation for DB and in-memory stores.
 */
final class SeasonSbtGrantNormalizer
{
	private const ALLOWED_TIERS = [
		SeasonSbtGrantStore::TIER_PARTICIPANT => true,
		SeasonSbtGrantStore::TIER_RANK => true,
		SeasonSbtGrantStore::TIER_FEAT => true,
	];

	/**
	 * @param array<string, mixed> $grant
	 * @return array{
	 *     universe: int,
	 *     user_id: int,
	 *     hive_user: string,
	 *     season_id: string,
	 *     tier: string,
	 *     rank: int|null,
	 *     feat_id: string,
	 *     nft_namespace: string,
	 *     nft_symbol: string,
	 *     instance_id: string,
	 *     trx_id: string,
	 *     recap_url: string,
	 *     image_url: string,
	 *     game_name: string,
	 *     data_json: string,
	 *     retroactive: int,
	 *     issued_at: int
	 * }|null
	 */
	public static function normalize(array $grant): ?array
	{
		$seasonId = trim((string) ($grant['season_id'] ?? ''));
		$hiveUser = strtolower(trim((string) ($grant['hive_user'] ?? '')));
		$tier = strtolower(trim((string) ($grant['tier'] ?? '')));
		if ($seasonId === '' || $hiveUser === '' || !isset(self::ALLOWED_TIERS[$tier])) {
			return null;
		}
		if (!HiveUtil::isAccountValid($hiveUser)) {
			return null;
		}

		$rank = $grant['rank'] ?? null;
		if ($rank !== null && $rank !== '') {
			$rank = (int) $rank;
			if ($rank < 1) {
				$rank = null;
			}
		} else {
			$rank = null;
		}

		$featId = trim((string) ($grant['feat_id'] ?? ''));
		if ($tier !== SeasonSbtGrantStore::TIER_FEAT) {
			$featId = '';
		}

		$symbol = strtoupper(trim((string) ($grant['nft_symbol'] ?? '')));
		if ($symbol !== '' && !preg_match('/^[A-Z][A-Z0-9]{0,9}$/', $symbol)) {
			return null;
		}

		$dataJson = $grant['data_json'] ?? '';
		if (is_array($dataJson)) {
			$encoded = json_encode($dataJson, JSON_UNESCAPED_SLASHES);
			$dataJson = is_string($encoded) ? $encoded : '';
		} else {
			$dataJson = (string) $dataJson;
		}

		return [
			'universe'      => max(0, (int) ($grant['universe'] ?? 0)),
			'user_id'       => max(0, (int) ($grant['user_id'] ?? 0)),
			'hive_user'     => $hiveUser,
			'season_id'     => $seasonId,
			'tier'          => $tier,
			'rank'          => $rank,
			'feat_id'       => $featId,
			'nft_namespace' => strtolower(trim((string) ($grant['nft_namespace'] ?? ''))),
			'nft_symbol'    => $symbol,
			'instance_id'   => trim((string) ($grant['instance_id'] ?? '')),
			'trx_id'        => trim((string) ($grant['trx_id'] ?? '')),
			'recap_url'     => self::sanitizeHttpUrl((string) ($grant['recap_url'] ?? '')),
			'image_url'     => self::sanitizeHttpUrl((string) ($grant['image_url'] ?? '')),
			'game_name'     => trim((string) ($grant['game_name'] ?? '')),
			'data_json'     => $dataJson,
			'retroactive'   => !empty($grant['retroactive']) ? 1 : 0,
			'issued_at'     => max(0, (int) ($grant['issued_at'] ?? 0)),
		];
	}

	/**
	 * Allowlist http(s) only; blank javascript:/data:/relative/etc. for href/src safety.
	 */
	public static function sanitizeHttpUrl(string $url): string
	{
		$url = trim($url);
		if ($url === '') {
			return '';
		}
		if (!preg_match('#^https?://#i', $url)) {
			return '';
		}

		return $url;
	}
}
