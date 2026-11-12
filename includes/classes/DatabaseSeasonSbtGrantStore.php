<?php

namespace HiveNova\Core;

use Throwable;

/**
 * PDO-backed season_sbt_grants store. Soft-fails when the table is missing.
 */
class DatabaseSeasonSbtGrantStore implements SeasonSbtGrantStore
{
	/** DB version that introduced season_sbt_grants (install/migrations/migration_54.sql). */
	public const SCHEMA_DB_VERSION = 54;

	/** @var bool|null */
	private static $schemaReadyCache = null;

	public function __construct(
		private readonly ?DatabaseInterface $db = null,
	) {
	}

	public static function resetSchemaReadyCache(): void
	{
		self::$schemaReadyCache = null;
	}

	public static function isSchemaReady(?DatabaseInterface $db = null): bool
	{
		if (self::$schemaReadyCache !== null) {
			return self::$schemaReadyCache;
		}

		try {
			$database = $db ?? Database::get();
			$version = (int) $database->selectSingle(
				'SELECT dbVersion FROM %%SYSTEM%% LIMIT 1;',
				[],
				'dbVersion'
			);
			if ($version < self::SCHEMA_DB_VERSION) {
				self::$schemaReadyCache = false;
			} else {
				$tables = $database->nativeQuery(
					"SHOW TABLES LIKE '" . DB_PREFIX . "season_sbt_grants'"
				);
				self::$schemaReadyCache = !empty($tables);
			}
		} catch (Throwable) {
			self::$schemaReadyCache = false;
		}

		return self::$schemaReadyCache;
	}

	public function listForUser(int $userId): array
	{
		if ($userId <= 0 || !self::isSchemaReady($this->db)) {
			return [];
		}

		try {
			$rows = $this->database()->select(
				'SELECT * FROM %%SEASON_SBT_GRANTS%%
				WHERE `user_id` = :uid
				ORDER BY `season_id` ASC, `tier` ASC, `rank` ASC, `id` ASC;',
				[':uid' => $userId]
			);
		} catch (Throwable) {
			return [];
		}

		return $this->mapRows(is_array($rows) ? $rows : []);
	}

	public function listForHive(string $hiveUser): array
	{
		$hive = strtolower(trim($hiveUser));
		if ($hive === '' || !self::isSchemaReady($this->db)) {
			return [];
		}

		try {
			$rows = $this->database()->select(
				'SELECT * FROM %%SEASON_SBT_GRANTS%%
				WHERE `hive_user` = :hive
				ORDER BY `season_id` ASC, `tier` ASC, `rank` ASC, `id` ASC;',
				[':hive' => $hive]
			);
		} catch (Throwable) {
			return [];
		}

		return $this->mapRows(is_array($rows) ? $rows : []);
	}

	public function exists(string $seasonId, string $hiveUser, string $tier, string $featId = ''): bool
	{
		if (!self::isSchemaReady($this->db)) {
			return false;
		}

		try {
			$id = $this->database()->selectSingle(
				'SELECT `id` FROM %%SEASON_SBT_GRANTS%%
				WHERE `season_id` = :season AND `hive_user` = :hive AND `tier` = :tier AND `feat_id` = :feat
				LIMIT 1;',
				[
					':season' => trim($seasonId),
					':hive'   => strtolower(trim($hiveUser)),
					':tier'   => strtolower(trim($tier)),
					':feat'   => trim($featId),
				],
				'id'
			);
		} catch (Throwable) {
			return false;
		}

		return !empty($id);
	}

	public function insert(array $grant): bool
	{
		$normalized = SeasonSbtGrantNormalizer::normalize($grant);
		if ($normalized === null || !self::isSchemaReady($this->db)) {
			return false;
		}
		if ($this->exists(
			$normalized['season_id'],
			$normalized['hive_user'],
			$normalized['tier'],
			$normalized['feat_id']
		)) {
			return false;
		}

		try {
			$this->database()->insert(
				'INSERT INTO %%SEASON_SBT_GRANTS%% SET
				`universe` = :universe,
				`user_id` = :user_id,
				`hive_user` = :hive_user,
				`season_id` = :season_id,
				`tier` = :tier,
				`rank` = :rank,
				`feat_id` = :feat_id,
				`nft_namespace` = :nft_namespace,
				`nft_symbol` = :nft_symbol,
				`instance_id` = :instance_id,
				`trx_id` = :trx_id,
				`recap_url` = :recap_url,
				`image_url` = :image_url,
				`game_name` = :game_name,
				`data_json` = :data_json,
				`retroactive` = :retroactive,
				`issued_at` = :issued_at;',
				[
					':universe'      => $normalized['universe'],
					':user_id'       => $normalized['user_id'],
					':hive_user'     => $normalized['hive_user'],
					':season_id'     => $normalized['season_id'],
					':tier'          => $normalized['tier'],
					':rank'          => $normalized['rank'],
					':feat_id'       => $normalized['feat_id'],
					':nft_namespace' => $normalized['nft_namespace'],
					':nft_symbol'    => $normalized['nft_symbol'],
					':instance_id'   => $normalized['instance_id'],
					':trx_id'        => $normalized['trx_id'],
					':recap_url'     => $normalized['recap_url'],
					':image_url'     => $normalized['image_url'],
					':game_name'     => $normalized['game_name'],
					':data_json'     => $normalized['data_json'],
					':retroactive'   => $normalized['retroactive'],
					':issued_at'     => $normalized['issued_at'],
				]
			);

			return true;
		} catch (Throwable) {
			return false;
		}
	}

	private function database(): DatabaseInterface
	{
		return $this->db ?? Database::get();
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private function mapRows(array $rows): array
	{
		$out = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$normalized = SeasonSbtGrantNormalizer::normalize($row);
			if ($normalized === null) {
				continue;
			}
			if (isset($row['id'])) {
				$normalized['id'] = (int) $row['id'];
			}
			$out[] = $normalized;
		}

		return $out;
	}
}
