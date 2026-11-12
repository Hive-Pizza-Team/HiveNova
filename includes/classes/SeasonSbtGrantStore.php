<?php

namespace HiveNova\Core;

/**
 * Read/write access to season_sbt_grants (#628 / #630).
 *
 * @phpstan-type GrantRow array{
 *     id?: int,
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
 * }
 */
interface SeasonSbtGrantStore
{
	public const TIER_PARTICIPANT = 'participant';
	public const TIER_RANK = 'rank';
	public const TIER_FEAT = 'feat';

	/**
	 * @return list<GrantRow>
	 */
	public function listForUser(int $userId): array;

	/**
	 * @return list<GrantRow>
	 */
	public function listForHive(string $hiveUser): array;

	public function exists(string $seasonId, string $hiveUser, string $tier, string $featId = ''): bool;

	/**
	 * @param GrantRow $grant
	 */
	public function insert(array $grant): bool;
}
