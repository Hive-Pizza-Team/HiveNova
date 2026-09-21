<?php

namespace HiveNova\Core;

/**
 * Season-scoped Hive seat and in-game season medal persistence.
 */
interface Uni3ClaimStore
{
	/**
	 * @return array<string, mixed>|null
	 */
	public function findHiveLinkByAccount(int $universe, int $seasonId, string $hive): ?array;

	/**
	 * @return array<string, mixed>|null
	 */
	public function findHiveLinkByUser(int $universe, int $seasonId, int $userId): ?array;

	/**
	 * @param array{universe: int, season_id: int, user_id: int, hive_account: string, origin: string, linked_at: int} $row
	 */
	public function insertHiveLink(array $row): bool;

	public function hiveOwnerId(int $universe, string $hive): ?int;

	public function setUserHiveAccount(int $universe, int $userId, string $hive): void;

	/**
	 * @param array{universe: int, season_id: int, user_id: int, hive_account: string, tier: string, status: string, points: int, claimed_at: int} $row
	 */
	public function upsertMedal(array $row): void;

	/**
	 * @return array<string, mixed>|null
	 */
	public function findMedal(int $universe, int $seasonId, int $userId): ?array;

	public function markMedal(int $universe, int $seasonId, int $userId, string $status, int $claimedAt, string $hive): void;
}
