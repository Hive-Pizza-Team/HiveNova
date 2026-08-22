<?php

use HiveNova\Core\Config;
use HiveNova\Core\SeasonStore;

class InMemorySeasonStore implements SeasonStore
{
	/** @var array<int, array<string, mixed>> */
	public array $users = [];

	/** @var array<string, array<string, mixed>> */
	public array $entries = [];

	/** @var list<array<string, mixed>> */
	public array $snapshots = [];

	/** @var list<array<string, mixed>> */
	public array $payouts = [];

	/** @var array<string, array<string, mixed>> */
	public array $weeks = [];

	/** @var list<array{user_id: int, hive_account: string, authlevel: int, points: int, rank: int}> */
	public array $ranking = [];

	/** @var list<array{id: int, hive_account: string, lang: string}> */
	public array $players = [];

	/** @var list<int> */
	public array $wiped = [];

	public int $nextPayoutId = 1;

	public function findUser(int $userId): ?array
	{
		return $this->users[$userId] ?? null;
	}

	public function findEntry(int $universe, int $seasonId, int $userId): ?array
	{
		return $this->entries[$this->entryKey($universe, $seasonId, $userId)] ?? null;
	}

	public function hasTrx(int $universe, string $trxId): bool
	{
		if ($trxId === '') {
			return false;
		}
		foreach ($this->entries as $row) {
			if ((int) $row['universe'] === $universe && (string) $row['trx_id'] === $trxId) {
				return true;
			}
		}

		return false;
	}

	public function insertEntry(array $row): bool
	{
		$key = $this->entryKey((int) $row['universe'], (int) $row['season_id'], (int) $row['user_id']);
		if (isset($this->entries[$key])) {
			return false;
		}
		$this->entries[$key] = $row;

		return true;
	}

	public function sumPool(int $universe, int $seasonId): float
	{
		$sum = 0.0;
		foreach ($this->entries as $row) {
			if ((int) $row['universe'] === $universe && (int) $row['season_id'] === $seasonId) {
				$sum += (float) $row['pizza_amount'];
			}
		}

		return $sum;
	}

	public function rankingRows(int $universe): array
	{
		return $this->ranking;
	}

	public function replaceSnapshots(int $universe, int $seasonId, array $rows): void
	{
		$this->snapshots = array_values(array_filter(
			$this->snapshots,
			static fn ($row) => (int) $row['universe'] !== $universe || (int) $row['season_id'] !== $seasonId
		));
		foreach ($rows as $row) {
			$this->snapshots[] = $row + ['universe' => $universe, 'season_id' => $seasonId];
		}
	}

	public function insertPayouts(array $rows): void
	{
		foreach ($rows as $row) {
			$row['id'] = $this->nextPayoutId++;
			$this->payouts[] = $row;
		}
	}

	public function openPayouts(int $universe, int $seasonId): array
	{
		$out = [];
		foreach ($this->payouts as $row) {
			if ((int) $row['universe'] !== $universe || (int) $row['season_id'] !== $seasonId) {
				continue;
			}
			if (!in_array($row['status'], ['pending', 'failed'], true)) {
				continue;
			}
			$out[] = [
				'id'           => (int) $row['id'],
				'user_id'      => (int) $row['user_id'],
				'hive_account' => (string) $row['hive_account'],
				'pizza_amount' => (float) $row['pizza_amount'],
				'status'       => (string) $row['status'],
				'trx_id'       => (string) ($row['trx_id'] ?? ''),
			];
		}

		return $out;
	}

	public function markPayout(int $id, string $status, string $trxId): void
	{
		foreach ($this->payouts as $i => $row) {
			if ((int) $row['id'] === $id) {
				$this->payouts[$i]['status'] = $status;
				$this->payouts[$i]['trx_id'] = $trxId;
			}
		}
	}

	public function playersInUniverse(int $universe): array
	{
		return $this->players;
	}

	public function upsertWeek(array $row): void
	{
		$key = $row['universe'] . ':' . $row['season_id'];
		$this->weeks[$key] = array_merge($this->weeks[$key] ?? [], $row);
	}

	public function getWeek(int $universe, int $seasonId): ?array
	{
		return $this->weeks[$universe . ':' . $seasonId] ?? null;
	}

	public function updateWeek(int $universe, int $seasonId, array $fields): void
	{
		$key = $universe . ':' . $seasonId;
		$this->weeks[$key] = array_merge($this->weeks[$key] ?? [], $fields);
	}

	public function wipeProgress(int $universe, Config $config): void
	{
		$this->wiped[] = $universe;
	}

	private function entryKey(int $universe, int $seasonId, int $userId): string
	{
		return $universe . ':' . $seasonId . ':' . $userId;
	}
}
