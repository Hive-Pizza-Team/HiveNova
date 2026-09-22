<?php

use HiveNova\Core\Uni3ClaimStore;

class InMemoryUni3ClaimStore implements Uni3ClaimStore
{
	/** @var array<string, array<string, mixed>> */
	public array $linksByHive = [];

	/** @var array<string, string> */
	public array $hiveByUser = [];

	/** @var array<string, int> */
	public array $owners = [];

	/** @var array<string, array<string, mixed>> */
	public array $medals = [];

	/** @var list<array{0: int, 1: int, 2: string}> */
	public array $hiveWrites = [];

	public function findHiveLinkByAccount(int $universe, int $seasonId, string $hive): ?array
	{
		return $this->linksByHive[$this->hiveKey($universe, $seasonId, $hive)] ?? null;
	}

	public function findHiveLinkByUser(int $universe, int $seasonId, int $userId): ?array
	{
		$hive = $this->hiveByUser[$this->userKey($universe, $seasonId, $userId)] ?? null;
		if ($hive === null) {
			return null;
		}

		return $this->findHiveLinkByAccount($universe, $seasonId, $hive);
	}

	public function insertHiveLink(array $row): bool
	{
		$hive = strtolower((string) $row['hive_account']);
		$hiveKey = $this->hiveKey((int) $row['universe'], (int) $row['season_id'], $hive);
		$userKey = $this->userKey((int) $row['universe'], (int) $row['season_id'], (int) $row['user_id']);
		if (isset($this->linksByHive[$hiveKey]) || isset($this->hiveByUser[$userKey])) {
			return false;
		}
		$row['hive_account'] = $hive;
		$this->linksByHive[$hiveKey] = $row;
		$this->hiveByUser[$userKey] = $hive;
		$this->owners[(int) $row['universe'] . ':' . $hive] = (int) $row['user_id'];

		return true;
	}

	public function hiveOwnerId(int $universe, string $hive): ?int
	{
		$key = $universe . ':' . strtolower($hive);

		return $this->owners[$key] ?? null;
	}

	public function setUserHiveAccount(int $universe, int $userId, string $hive): void
	{
		$hive = strtolower($hive);
		$this->hiveWrites[] = [$universe, $userId, $hive];
		$this->owners[$universe . ':' . $hive] = $userId;
	}

	public function upsertMedal(array $row): void
	{
		$key = $this->userKey((int) $row['universe'], (int) $row['season_id'], (int) $row['user_id']);
		if (isset($this->medals[$key])) {
			return;
		}
		$this->medals[$key] = $row;
	}

	public function findMedal(int $universe, int $seasonId, int $userId): ?array
	{
		return $this->medals[$this->userKey($universe, $seasonId, $userId)] ?? null;
	}

	public function markMedal(int $universe, int $seasonId, int $userId, string $status, int $claimedAt, string $hive): void
	{
		$key = $this->userKey($universe, $seasonId, $userId);
		if (!isset($this->medals[$key])) {
			return;
		}
		$this->medals[$key]['status'] = $status;
		$this->medals[$key]['claimed_at'] = $claimedAt;
		$this->medals[$key]['hive_account'] = $hive;
	}

	private function hiveKey(int $universe, int $seasonId, string $hive): string
	{
		return $universe . ':' . $seasonId . ':' . strtolower($hive);
	}

	private function userKey(int $universe, int $seasonId, int $userId): string
	{
		return $universe . ':' . $seasonId . ':' . $userId;
	}
}
