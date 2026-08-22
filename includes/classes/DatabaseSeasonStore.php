<?php

namespace HiveNova\Core;

class DatabaseSeasonStore implements SeasonStore
{
	public function __construct(
		private readonly ?SeasonWipeService $wipe = null,
	) {
	}

	public function findUser(int $userId): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT `id`, `hive_account`, `universe`, `authlevel` FROM %%USERS%% WHERE `id` = :id LIMIT 1',
			[':id' => $userId]
		);

		return is_array($row) ? $row : null;
	}

	public function findEntry(int $universe, int $seasonId, int $userId): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SEASON_ENTRIES%% WHERE `universe` = :uni AND `season_id` = :sid AND `user_id` = :uid LIMIT 1',
			[':uni' => $universe, ':sid' => $seasonId, ':uid' => $userId]
		);

		return is_array($row) ? $row : null;
	}

	public function hasTrx(int $universe, string $trxId): bool
	{
		if ($trxId === '') {
			return false;
		}
		$id = Database::get()->selectSingle(
			'SELECT `id` FROM %%SEASON_ENTRIES%% WHERE `universe` = :uni AND `trx_id` = :trx LIMIT 1',
			[':uni' => $universe, ':trx' => $trxId],
			'id'
		);

		return !empty($id);
	}

	public function insertEntry(array $row): bool
	{
		try {
			Database::get()->insert(
				'INSERT INTO %%SEASON_ENTRIES%% SET
				`universe` = :uni, `season_id` = :sid, `user_id` = :uid, `hive_account` = :hive,
				`pizza_amount` = :amt, `trx_id` = :trx, `created_at` = :ts',
				[
					':uni'  => $row['universe'],
					':sid'  => $row['season_id'],
					':uid'  => $row['user_id'],
					':hive' => $row['hive_account'],
					':amt'  => $row['pizza_amount'],
					':trx'  => $row['trx_id'],
					':ts'   => $row['created_at'],
				]
			);

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	public function sumPool(int $universe, int $seasonId): float
	{
		$sum = Database::get()->selectSingle(
			'SELECT COALESCE(SUM(`pizza_amount`), 0) AS `pool` FROM %%SEASON_ENTRIES%% WHERE `universe` = :uni AND `season_id` = :sid',
			[':uni' => $universe, ':sid' => $seasonId],
			'pool'
		);

		return (float) $sum;
	}

	public function rankingRows(int $universe): array
	{
		$rows = Database::get()->select(
			'SELECT u.`id` AS `user_id`, u.`hive_account`, u.`authlevel`,
				COALESCE(s.`total_points`, 0) AS `points`, COALESCE(s.`total_rank`, 0) AS `rank`
			FROM %%USERS%% u
			LEFT JOIN %%STATPOINTS%% s ON s.`id_owner` = u.`id` AND s.`stat_type` = 1 AND s.`universe` = u.`universe`
			WHERE u.`universe` = :uni AND u.`authlevel` = :auth
			ORDER BY `points` DESC, u.`id` ASC',
			[':uni' => $universe, ':auth' => AUTH_USR]
		);

		$out = [];
		foreach ($rows as $i => $row) {
			$out[] = [
				'user_id'      => (int) $row['user_id'],
				'hive_account' => (string) $row['hive_account'],
				'authlevel'    => (int) $row['authlevel'],
				'points'       => (int) $row['points'],
				'rank'         => $i + 1,
			];
		}

		return $out;
	}

	public function replaceSnapshots(int $universe, int $seasonId, array $rows): void
	{
		$db = Database::get();
		$db->delete(
			'DELETE FROM %%SEASON_SNAPSHOTS%% WHERE `universe` = :uni AND `season_id` = :sid',
			[':uni' => $universe, ':sid' => $seasonId]
		);
		foreach ($rows as $row) {
			$db->insert(
				'INSERT INTO %%SEASON_SNAPSHOTS%% SET
				`universe` = :uni, `season_id` = :sid, `user_id` = :uid, `hive_account` = :hive,
				`rank` = :rank, `points` = :points',
				[
					':uni'    => $universe,
					':sid'    => $seasonId,
					':uid'    => $row['user_id'],
					':hive'   => $row['hive_account'],
					':rank'   => $row['rank'],
					':points' => $row['points'],
				]
			);
		}
	}

	public function insertPayouts(array $rows): void
	{
		$db = Database::get();
		foreach ($rows as $row) {
			$db->insert(
				'INSERT INTO %%SEASON_PAYOUTS%% SET
				`universe` = :uni, `season_id` = :sid, `user_id` = :uid, `hive_account` = :hive,
				`rank` = :rank, `points` = :points, `pizza_amount` = :amt, `trx_id` = :trx, `status` = :status',
				[
					':uni'    => $row['universe'],
					':sid'    => $row['season_id'],
					':uid'    => $row['user_id'],
					':hive'   => $row['hive_account'],
					':rank'   => $row['rank'],
					':points' => $row['points'],
					':amt'    => $row['pizza_amount'],
					':trx'    => $row['trx_id'],
					':status' => $row['status'],
				]
			);
		}
	}

	public function openPayouts(int $universe, int $seasonId): array
	{
		$rows = Database::get()->select(
			'SELECT `id`, `user_id`, `hive_account`, `pizza_amount`, `status`, `trx_id`
			FROM %%SEASON_PAYOUTS%%
			WHERE `universe` = :uni AND `season_id` = :sid AND `status` IN (\'pending\', \'failed\')',
			[':uni' => $universe, ':sid' => $seasonId]
		);
		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'id'           => (int) $row['id'],
				'user_id'      => (int) $row['user_id'],
				'hive_account' => (string) $row['hive_account'],
				'pizza_amount' => (float) $row['pizza_amount'],
				'status'       => (string) $row['status'],
				'trx_id'       => (string) $row['trx_id'],
			];
		}

		return $out;
	}

	public function markPayout(int $id, string $status, string $trxId): void
	{
		Database::get()->update(
			'UPDATE %%SEASON_PAYOUTS%% SET `status` = :status, `trx_id` = :trx WHERE `id` = :id',
			[':status' => $status, ':trx' => $trxId, ':id' => $id]
		);
	}

	public function playersInUniverse(int $universe): array
	{
		$rows = Database::get()->select(
			'SELECT `id`, `hive_account`, `lang` FROM %%USERS%% WHERE `universe` = :uni AND `authlevel` = :auth',
			[':uni' => $universe, ':auth' => AUTH_USR]
		);
		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'id'           => (int) $row['id'],
				'hive_account' => (string) $row['hive_account'],
				'lang'         => (string) $row['lang'],
			];
		}

		return $out;
	}

	public function upsertWeek(array $row): void
	{
		$db = Database::get();
		$existing = $this->getWeek((int) $row['universe'], (int) $row['season_id']);
		if ($existing !== null) {
			$this->updateWeek((int) $row['universe'], (int) $row['season_id'], $row);
			return;
		}
		$db->insert(
			'INSERT INTO %%SEASON_WEEKS%% SET
			`universe` = :uni, `season_id` = :sid, `starts_at` = :start, `closes_at` = :close,
			`status` = :status, `pool_pizza` = :pool, `house_cut_pizza` = :cut, `payout_budget` = :budget',
			[
				':uni'    => $row['universe'],
				':sid'    => $row['season_id'],
				':start'  => $row['starts_at'],
				':close'  => $row['closes_at'],
				':status' => $row['status'],
				':pool'   => $row['pool_pizza'],
				':cut'    => $row['house_cut_pizza'],
				':budget' => $row['payout_budget'],
			]
		);
	}

	public function getWeek(int $universe, int $seasonId): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SEASON_WEEKS%% WHERE `universe` = :uni AND `season_id` = :sid LIMIT 1',
			[':uni' => $universe, ':sid' => $seasonId]
		);

		return is_array($row) ? $row : null;
	}

	public function updateWeek(int $universe, int $seasonId, array $fields): void
	{
		$set = [];
		$params = [':uni' => $universe, ':sid' => $seasonId];
		$map = [
			'starts_at'        => 'starts_at',
			'closes_at'        => 'closes_at',
			'status'           => 'status',
			'pool_pizza'       => 'pool_pizza',
			'house_cut_pizza'  => 'house_cut_pizza',
			'payout_budget'    => 'payout_budget',
		];
		foreach ($map as $field => $col) {
			if (array_key_exists($field, $fields)) {
				$set[] = '`' . $col . '` = :' . $col;
				$params[':' . $col] = $fields[$field];
			}
		}
		if ($set === []) {
			return;
		}
		Database::get()->update(
			'UPDATE %%SEASON_WEEKS%% SET ' . implode(', ', $set) . ' WHERE `universe` = :uni AND `season_id` = :sid',
			$params
		);
	}

	public function wipeProgress(int $universe, Config $config): void
	{
		$wipe = $this->wipe ?? SeasonWipeService::fromGlobals(null, null, $config);
		$wipe->wipe($universe, $config);
	}
}
