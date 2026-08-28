<?php

namespace HiveNova\Core;

class FrequentLocationService
{
	public const MAX_RECENT = 20;

	public const MISSION_RECYCLE = 8;

	public const TYPE_DEBRIS = 2;

	/**
	 * @param list<array{galaxy:int,system:int,planet:int}> $ownBodies
	 */
	public static function record(
		int $ownerId,
		int $galaxy,
		int $system,
		int $planet,
		int $type,
		int $mission,
		array $ownBodies,
		int $maxPlanets,
		?int $now = null
	): bool {
		$now ??= TIMESTAMP;

		if ($planet === $maxPlanets + 1 || $planet === $maxPlanets + 2) {
			return false;
		}

		if (self::isOwnBody($galaxy, $system, $planet, $ownBodies)) {
			return false;
		}

		if ($mission === self::MISSION_RECYCLE) {
			$type = self::TYPE_DEBRIS;
		}

		$db = Database::get();
		$db->insert(
			'INSERT INTO %%FREQUENT_LOCATIONS%% SET ownerID = :ownerID, galaxy = :galaxy, `system` = :system, planet = :planet, type = :type, lastUsed = :lastUsed
			ON DUPLICATE KEY UPDATE lastUsed = :lastUsedUpdate;',
			[
				':ownerID'         => $ownerId,
				':galaxy'          => $galaxy,
				':system'          => $system,
				':planet'          => $planet,
				':type'            => $type,
				':lastUsed'        => $now,
				':lastUsedUpdate'  => $now,
			]
		);

		self::trimToCap($ownerId);

		return true;
	}

	public static function recordFromFleet(
		int $ownerId,
		int $galaxy,
		int $system,
		int $planet,
		int $type,
		int $mission
	): bool {
		$rows = Database::get()->select(
			'SELECT galaxy, `system`, planet FROM %%PLANETS%% WHERE id_owner = :userId AND destruyed = :destruyed;',
			[
				':userId'    => $ownerId,
				':destruyed' => 0,
			]
		);

		return self::record(
			$ownerId,
			$galaxy,
			$system,
			$planet,
			$type,
			$mission,
			self::ownBodiesFromPlanets($rows ?: []),
			(int) Config::get()->max_planets
		);
	}

	public static function tryRecordFromFleet(
		int $ownerId,
		int $galaxy,
		int $system,
		int $planet,
		int $type,
		int $mission
	): void {
		try {
			self::recordFromFleet($ownerId, $galaxy, $system, $planet, $type, $mission);
		} catch (\Throwable) {
		}
	}

	/**
	 * @param list<array<string, mixed>> $planets
	 * @return list<array{galaxy:int,system:int,planet:int}>
	 */
	public static function ownBodiesFromPlanets(array $planets): array
	{
		$bodies = [];
		foreach ($planets as $planet) {
			$bodies[] = [
				'galaxy' => (int) ($planet['galaxy'] ?? 0),
				'system' => (int) ($planet['system'] ?? 0),
				'planet' => (int) ($planet['planet'] ?? 0),
			];
		}

		return $bodies;
	}

	/**
	 * @param list<array{galaxy:int,system:int,planet:int}> $ownBodies
	 * @return list<array{galaxy:int,system:int,planet:int,type:int,lastUsed:int}>
	 */
	public static function listForUser(int $ownerId, array $ownBodies): array
	{
		// Fetch only enough rows to fill MAX_RECENT after filtering own colonies.
		$limit = self::MAX_RECENT + count($ownBodies);
		$rows = Database::get()->select(
			'SELECT galaxy, `system`, planet, type, lastUsed FROM %%FREQUENT_LOCATIONS%% WHERE ownerID = :ownerID ORDER BY lastUsed DESC, id DESC LIMIT :limit;',
			[
				':ownerID' => $ownerId,
				':limit'   => $limit,
			]
		);

		$locations = [];
		foreach ($rows ?: [] as $row) {
			$galaxy = (int) $row['galaxy'];
			$system = (int) $row['system'];
			$planet = (int) $row['planet'];
			if (self::isOwnBody($galaxy, $system, $planet, $ownBodies)) {
				continue;
			}

			$locations[] = [
				'galaxy'   => $galaxy,
				'system'   => $system,
				'planet'   => $planet,
				'type'     => (int) $row['type'],
				'lastUsed' => (int) $row['lastUsed'],
			];

			if (count($locations) >= self::MAX_RECENT) {
				break;
			}
		}

		return $locations;
	}

	/**
	 * @param list<array{galaxy:int,system:int,planet:int}> $ownBodies
	 */
	private static function isOwnBody(int $galaxy, int $system, int $planet, array $ownBodies): bool
	{
		foreach ($ownBodies as $body) {
			if ((int) $body['galaxy'] === $galaxy
				&& (int) $body['system'] === $system
				&& (int) $body['planet'] === $planet
			) {
				return true;
			}
		}

		return false;
	}

	private static function trimToCap(int $ownerId): void
	{
		$db = Database::get();
		$rows = $db->select(
			'SELECT id FROM %%FREQUENT_LOCATIONS%% WHERE ownerID = :ownerID ORDER BY lastUsed DESC, id DESC;',
			[':ownerID' => $ownerId]
		);

		if (count($rows) <= self::MAX_RECENT) {
			return;
		}

		$keep = array_slice($rows, 0, self::MAX_RECENT);
		$keepIds = array_map(static fn (array $row): int => (int) $row['id'], $keep);
		$allIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
		$dropIds = array_diff($allIds, $keepIds);

		foreach ($dropIds as $id) {
			$db->delete(
				'DELETE FROM %%FREQUENT_LOCATIONS%% WHERE id = :id AND ownerID = :ownerID;',
				[
					':id'      => $id,
					':ownerID' => $ownerId,
				]
			);
		}
	}
}
