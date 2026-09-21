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

	/** @var list<int> */
	public array $loggedOut = [];

	/** @var list<int> game_disable value observed at each wipeProgress call */
	public array $gameDisableAtWipe = [];

	/** @var list<array{units: int, result: string, attacker: string, defender: string}> */
	public array $hallOfFame = [];

	/** @var list<array{feat_key: string, username: string, hive_account: string, claimed_at: int}> */
	public array $feats = [];

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

	public function rankingRows(int $universe, int $seasonId): array
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

	public function compareAndSetPayout(int $id, string $fromStatus, string $toStatus, string $trxId): void
	{
		foreach ($this->payouts as $i => $row) {
			if ((int) $row['id'] === $id && (string) $row['status'] === $fromStatus) {
				$this->payouts[$i]['status'] = $toStatus;
				$this->payouts[$i]['trx_id'] = $trxId;
			}
		}
	}

	public function findPayout(int $universe, int $seasonId, int $userId): ?array
	{
		foreach ($this->payouts as $row) {
			if ((int) $row['universe'] === $universe && (int) $row['season_id'] === $seasonId && (int) $row['user_id'] === $userId) {
				return $this->mapPayout($row);
			}
		}

		return null;
	}

	public function payoutsWithStatus(int $universe, int $seasonId, string $status): array
	{
		$out = [];
		foreach ($this->payouts as $row) {
			if ((int) $row['universe'] !== $universe || (int) $row['season_id'] !== $seasonId) {
				continue;
			}
			if ((string) $row['status'] !== $status) {
				continue;
			}
			$out[] = $this->mapPayout($row);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{id: int, user_id: int, hive_account: string, pizza_amount: float, status: string, trx_id: string, points: int, rank: int}
	 */
	private function mapPayout(array $row): array
	{
		return [
			'id'           => (int) $row['id'],
			'user_id'      => (int) $row['user_id'],
			'hive_account' => (string) $row['hive_account'],
			'pizza_amount' => (float) $row['pizza_amount'],
			'status'       => (string) $row['status'],
			'trx_id'       => (string) ($row['trx_id'] ?? ''),
			'points'       => (int) ($row['points'] ?? 0),
			'rank'         => (int) ($row['rank'] ?? 0),
		];
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

	public function reportRanking(int $universe, int $seasonId, int $limit = 20): array
	{
		$out = [];
		foreach ($this->snapshots as $row) {
			if ((int) ($row['universe'] ?? 0) !== $universe || (int) ($row['season_id'] ?? 0) !== $seasonId) {
				continue;
			}
			$userId = (int) ($row['user_id'] ?? 0);
			$username = (string) ($this->users[$userId]['username'] ?? '');
			$pizza = null;
			$prizeState = '';
			foreach ($this->payouts as $payout) {
				if ((int) $payout['universe'] !== $universe
					|| (int) $payout['season_id'] !== $seasonId
					|| (int) $payout['user_id'] !== $userId
				) {
					continue;
				}
				$status = (string) ($payout['status'] ?? '');
				if ($status === 'sent') {
					$pizza = (float) $payout['pizza_amount'];
					$prizeState = 'sent';
				} elseif (in_array($status, ['pending_claim', 'forfeited', 'claiming'], true)) {
					$prizeState = 'unclaimed';
				}
				break;
			}
			$out[] = [
				'rank'         => (int) $row['rank'],
				'username'     => $username,
				'hive_account' => (string) $row['hive_account'],
				'points'       => (int) $row['points'],
				'pizza_amount' => $pizza,
				'prize_state'  => $prizeState,
			];
		}
		usort($out, static fn ($a, $b) => $a['rank'] <=> $b['rank']);

		return array_slice($out, 0, max(1, $limit));
	}

	public function reportHallOfFame(int $universe, int $limit = 10): array
	{
		return array_slice($this->hallOfFame, 0, max(1, $limit));
	}

	public function reportFeats(int $universe, int $startsAt, int $closesAt): array
	{
		$out = [];
		foreach ($this->feats as $feat) {
			$at = (int) ($feat['claimed_at'] ?? 0);
			if ($at < $startsAt || ($closesAt > 0 && $at > $closesAt)) {
				continue;
			}
			$out[] = $feat;
		}

		return $out;
	}

	public function countEntries(int $universe, int $seasonId): int
	{
		$count = 0;
		foreach ($this->entries as $row) {
			if ((int) $row['universe'] === $universe && (int) $row['season_id'] === $seasonId) {
				$count++;
			}
		}

		return $count;
	}

	public function logoutUniverse(int $universe): void
	{
		$this->loggedOut[] = $universe;
	}

	public function wipeProgress(int $universe, Config $config): void
	{
		$this->wiped[] = $universe;
		$this->gameDisableAtWipe[] = (int) ($config->game_disable ?? -1);
	}

	private function entryKey(int $universe, int $seasonId, int $userId): string
	{
		return $universe . ':' . $seasonId . ':' . $userId;
	}
}
