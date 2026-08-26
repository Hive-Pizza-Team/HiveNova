<?php

namespace HiveNova\Core;

interface SeasonStore
{
	/**
	 * @return array<string, mixed>|null
	 */
	public function findUser(int $userId): ?array;

	/**
	 * @return array<string, mixed>|null
	 */
	public function findEntry(int $universe, int $seasonId, int $userId): ?array;

	public function hasTrx(int $universe, string $trxId): bool;

	/**
	 * @param array{universe: int, season_id: int, user_id: int, hive_account: string, pizza_amount: float, trx_id: string, created_at: int} $row
	 */
	public function insertEntry(array $row): bool;

	public function sumPool(int $universe, int $seasonId): float;

	/**
	 * @return list<array{user_id: int, hive_account: string, authlevel: int, points: int, rank: int}>
	 */
	public function rankingRows(int $universe, int $seasonId): array;

	/**
	 * @param list<array{user_id: int, hive_account: string, rank: int, points: int}> $rows
	 */
	public function replaceSnapshots(int $universe, int $seasonId, array $rows): void;

	/**
	 * @param list<array{universe: int, season_id: int, user_id: int, hive_account: string, rank: int, points: int, pizza_amount: float, trx_id: string, status: string}> $rows
	 */
	public function insertPayouts(array $rows): void;

	/**
	 * @return list<array{id: int, user_id: int, hive_account: string, pizza_amount: float, status: string, trx_id: string}>
	 */
	public function openPayouts(int $universe, int $seasonId): array;

	public function markPayout(int $id, string $status, string $trxId): void;

	/**
	 * @return list<array{id: int, hive_account: string, lang: string}>
	 */
	public function playersInUniverse(int $universe): array;

	/**
	 * @param array{universe: int, season_id: int, starts_at: int, closes_at: int, status: string, pool_pizza: float, house_cut_pizza: float, payout_budget: float} $row
	 */
	public function upsertWeek(array $row): void;

	/**
	 * @return array<string, mixed>|null
	 */
	public function getWeek(int $universe, int $seasonId): ?array;

	public function updateWeek(int $universe, int $seasonId, array $fields): void;

	/**
	 * Top ranking rows for the season blog (username + prize when sent).
	 *
	 * @return list<array{rank: int, username: string, hive_account: string, points: int, pizza_amount: float|null}>
	 */
	public function reportRanking(int $universe, int $seasonId, int $limit = 20): array;

	/**
	 * Top combat Hall of Fame rows for the season blog.
	 *
	 * @return list<array{units: int, result: string, attacker: string, defender: string}>
	 */
	public function reportHallOfFame(int $universe, int $limit = 10): array;

	/**
	 * Feats claimed in the season time window.
	 *
	 * @return list<array{feat_key: string, username: string, hive_account: string, claimed_at: int}>
	 */
	public function reportFeats(int $universe, int $startsAt, int $closesAt): array;

	public function countEntries(int $universe, int $seasonId): int;

	public function wipeProgress(int $universe, Config $config): void;
}
