<?php

namespace HiveNova\Core;

class DatabaseUni3ClaimStore implements Uni3ClaimStore
{
	public function findHiveLinkByAccount(int $universe, int $seasonId, string $hive): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SEASON_HIVE_LINKS%% WHERE `universe` = :uni AND `season_id` = :sid AND `hive_account` = :hive LIMIT 1',
			[':uni' => $universe, ':sid' => $seasonId, ':hive' => $hive]
		);

		return is_array($row) ? $row : null;
	}

	public function findHiveLinkByUser(int $universe, int $seasonId, int $userId): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SEASON_HIVE_LINKS%% WHERE `universe` = :uni AND `season_id` = :sid AND `user_id` = :uid LIMIT 1',
			[':uni' => $universe, ':sid' => $seasonId, ':uid' => $userId]
		);

		return is_array($row) ? $row : null;
	}

	public function insertHiveLink(array $row): bool
	{
		try {
			Database::get()->insert(
				'INSERT INTO %%SEASON_HIVE_LINKS%% SET
				`universe` = :uni, `season_id` = :sid, `user_id` = :uid,
				`hive_account` = :hive, `origin` = :origin, `linked_at` = :ts',
				[
					':uni'    => $row['universe'],
					':sid'    => $row['season_id'],
					':uid'    => $row['user_id'],
					':hive'   => $row['hive_account'],
					':origin' => $row['origin'],
					':ts'     => $row['linked_at'],
				]
			);

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	public function hiveOwnerId(int $universe, string $hive): ?int
	{
		$id = Database::get()->selectSingle(
			'SELECT `id` FROM %%USERS%% WHERE `universe` = :uni AND `hive_account` = :hive LIMIT 1',
			[':uni' => $universe, ':hive' => $hive],
			'id'
		);
		if ($id === false || $id === null || $id === '') {
			return null;
		}

		return (int) $id;
	}

	public function setUserHiveAccount(int $universe, int $userId, string $hive): void
	{
		Database::get()->update(
			'UPDATE %%USERS%% SET `hive_account` = :hive WHERE `id` = :uid AND `universe` = :uni',
			[':hive' => $hive, ':uid' => $userId, ':uni' => $universe]
		);
	}

	public function upsertMedal(array $row): void
	{
		$existing = $this->findMedal((int) $row['universe'], (int) $row['season_id'], (int) $row['user_id']);
		if ($existing !== null) {
			return;
		}
		try {
			Database::get()->insert(
				'INSERT INTO %%SEASON_MEDALS%% SET
				`universe` = :uni, `season_id` = :sid, `user_id` = :uid, `hive_account` = :hive,
				`tier` = :tier, `status` = :status, `points` = :points, `claimed_at` = :claimed',
				[
					':uni'     => $row['universe'],
					':sid'     => $row['season_id'],
					':uid'     => $row['user_id'],
					':hive'    => $row['hive_account'],
					':tier'    => $row['tier'],
					':status'  => $row['status'],
					':points'  => $row['points'],
					':claimed' => $row['claimed_at'],
				]
			);
		} catch (\Throwable $e) {
			return;
		}
	}

	public function findMedal(int $universe, int $seasonId, int $userId): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SEASON_MEDALS%% WHERE `universe` = :uni AND `season_id` = :sid AND `user_id` = :uid LIMIT 1',
			[':uni' => $universe, ':sid' => $seasonId, ':uid' => $userId]
		);

		return is_array($row) ? $row : null;
	}

	public function markMedal(int $universe, int $seasonId, int $userId, string $status, int $claimedAt, string $hive): void
	{
		Database::get()->update(
			'UPDATE %%SEASON_MEDALS%% SET `status` = :status, `claimed_at` = :claimed, `hive_account` = :hive
			WHERE `universe` = :uni AND `season_id` = :sid AND `user_id` = :uid',
			[
				':status'  => $status,
				':claimed' => $claimedAt,
				':hive'    => $hive,
				':uni'     => $universe,
				':sid'     => $seasonId,
				':uid'     => $userId,
			]
		);
	}
}
