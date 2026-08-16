<?php

namespace HiveNova\Core;

use HiveNova\Core\Config;
use HiveNova\Core\Database;

class PvePackageService
{
	/**
	 * @param array<string, mixed> $row
	 * @return array{metal: int, crystal: int}
	 */
	public static function currentLoot(array $row, ?int $now = null): array
	{
		$now = $now ?? TIMESTAMP;
		$ageHours = max(0, ($now - (int) $row['spawned_at']) / 3600);
		$growth = (int) floor($ageHours * PVE_PACKAGE_GROWTH_PER_HOUR);

		return [
			'metal'   => (int) min(PVE_PACKAGE_CAP_METAL, (int) $row['metal'] + $growth),
			'crystal' => (int) min(PVE_PACKAGE_CAP_CRYSTAL, (int) $row['crystal'] + $growth),
		];
	}

	public static function spawnBudget(int $onlineCount): int
	{
		return min(PVE_SPAWN_HARD_CAP, PVE_SPAWN_BASE + $onlineCount * PVE_SPAWN_PER_ONLINE);
	}

	public static function countOnline(int $universe, ?int $now = null): int
	{
		$now = $now ?? TIMESTAMP;
		$row = Database::get()->selectSingle(
			'SELECT COUNT(*) AS total FROM %%USERS%%
			WHERE universe = :universe AND urlaubs_modus = 0 AND onlinetime >= :since;',
			[
				':universe' => $universe,
				':since'    => $now - PVE_ONLINE_WINDOW,
			]
		);

		return (int) ($row['total'] ?? 0);
	}

	public static function expireOld(int $universe, ?int $now = null): void
	{
		Database::get()->delete(
			'DELETE FROM %%SALVAGE_PACKAGES%% WHERE universe = :universe AND expires_at <= :now;',
			[
				':universe' => $universe,
				':now'      => $now ?? TIMESTAMP,
			]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function findAt(int $universe, int $galaxy, int $system, int $planet): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SALVAGE_PACKAGES%%
			WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND planet = :planet
			AND expires_at > :now;',
			[
				':universe' => $universe,
				':galaxy'   => $galaxy,
				':system'   => $system,
				':planet'   => $planet,
				':now'      => TIMESTAMP,
			]
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function inSystem(int $universe, int $galaxy, int $system): array
	{
		$rows = Database::get()->select(
			'SELECT * FROM %%SALVAGE_PACKAGES%%
			WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND expires_at > :now;',
			[
				':universe' => $universe,
				':galaxy'   => $galaxy,
				':system'   => $system,
				':now'      => TIMESTAMP,
			]
		);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function lockAt(int $universe, int $galaxy, int $system, int $planet): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT * FROM %%SALVAGE_PACKAGES%%
			WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND planet = :planet
			AND expires_at > :now FOR UPDATE;',
			[
				':universe' => $universe,
				':galaxy'   => $galaxy,
				':system'   => $system,
				':planet'   => $planet,
				':now'      => TIMESTAMP,
			]
		);

		return is_array($row) ? $row : null;
	}

	public static function collect(int $id, int $metalTaken, int $crystalTaken): void
	{
		Database::get()->update(
			'UPDATE %%SALVAGE_PACKAGES%%
			SET metal = GREATEST(0, metal - :metal), crystal = GREATEST(0, crystal - :crystal)
			WHERE id = :id;',
			[
				':metal'   => $metalTaken,
				':crystal' => $crystalTaken,
				':id'      => $id,
			]
		);

		Database::get()->delete(
			'DELETE FROM %%SALVAGE_PACKAGES%% WHERE id = :id AND metal <= 0 AND crystal <= 0;',
			[':id' => $id]
		);
	}

	public static function attachToPlanet(int $universe, int $galaxy, int $system, int $planet, int $planetId): void
	{
		Database::get()->update(
			'UPDATE %%SALVAGE_PACKAGES%% SET planet_id = :planetId
			WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND planet = :planet;',
			[
				':planetId' => $planetId,
				':universe' => $universe,
				':galaxy'   => $galaxy,
				':system'   => $system,
				':planet'   => $planet,
			]
		);
	}

	public static function spyHint(array $row, int $spyTech): array
	{
		$loot = self::currentLoot($row);
		$hint = [
			'metal'   => $loot['metal'],
			'crystal' => $loot['crystal'],
		];
		if ($spyTech >= 4) {
			$hint['family'] = PveNpcFleetFactory::familyFromSeed((int) $row['encounter_seed']);
		}
		if ($spyTech >= 8) {
			$hint['tier'] = (int) $row['tier'];
		}

		return $hint;
	}

	public static function spawnTick(int $universe, ?int $now = null): int
	{
		if (!isModuleAvailable(MODULE_MISSION_SALVAGE)) {
			return 0;
		}

		$now = $now ?? TIMESTAMP;
		self::expireOld($universe, $now);

		$existing = (int) (Database::get()->selectSingle(
			'SELECT COUNT(*) AS total FROM %%SALVAGE_PACKAGES%% WHERE universe = :universe AND expires_at > :now;',
			[':universe' => $universe, ':now' => $now]
		)['total'] ?? 0);

		$budget = self::spawnBudget(self::countOnline($universe, $now));
		$toSpawn = max(0, $budget - $existing);
		if ($toSpawn === 0) {
			return 0;
		}

		$config = Config::get($universe);
		$accused = PushingAccusationQuery::accusedReceiverIds($universe, $now);
		$created = 0;

		for ($i = 0; $i < $toSpawn * 8 && $created < $toSpawn; $i++) {
			$galaxy = mt_rand(1, (int) $config->max_galaxy);
			$system = mt_rand(1, (int) $config->max_system);
			$planet = mt_rand(1, (int) $config->max_planets);

			if (self::findAt($universe, $galaxy, $system, $planet) !== null) {
				continue;
			}

			$occupied = Database::get()->selectSingle(
				'SELECT id, id_owner FROM %%PLANETS%%
				WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND planet = :planet
				AND planet_type = 1 AND destruyed = 0;',
				[
					':universe' => $universe,
					':galaxy'   => $galaxy,
					':system'   => $system,
					':planet'   => $planet,
				]
			);

			$planetId = null;
			if (!empty($occupied['id'])) {
				$overlayChance = PVE_OVERLAY_CHANCE;
				$ownerId = (int) $occupied['id_owner'];
				if (in_array($ownerId, $accused, true)) {
					$overlayChance += PVE_ACCUSED_OVERLAY_BONUS;
				}
				if (mt_rand(1, 100) > $overlayChance) {
					continue;
				}
				$owner = Database::get()->selectSingle(
					'SELECT u.id, u.urlaubs_modus, u.onlinetime, s.total_points FROM %%USERS%% u
					LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id AND s.stat_type = 1
					WHERE u.id = :id;',
					[':id' => $ownerId]
				);
				if (!empty($owner['urlaubs_modus'])) {
					continue;
				}
				$noobTime = (int) $config->noobprotectiontime;
				if (!empty($config->noobprotection) && $noobTime > 0
					&& (int) ($owner['total_points'] ?? 0) <= $noobTime) {
					continue;
				}
				$planetId = (int) $occupied['id'];
			}

			$tier = mt_rand(1, 3);
			Database::get()->insert(
				'INSERT INTO %%SALVAGE_PACKAGES%%
				(universe, galaxy, `system`, planet, planet_id, metal, crystal, spawned_at, expires_at, tier, encounter_seed)
				VALUES (:universe, :galaxy, :system, :planet, :planetId, :metal, :crystal, :spawned, :expires, :tier, :seed);',
				[
					':universe' => $universe,
					':galaxy'   => $galaxy,
					':system'   => $system,
					':planet'   => $planet,
					':planetId' => $planetId,
					':metal'    => PVE_PACKAGE_BASE_METAL * $tier,
					':crystal'  => PVE_PACKAGE_BASE_CRYSTAL * $tier,
					':spawned'  => $now,
					':expires'  => $now + PVE_PACKAGE_TTL,
					':tier'     => $tier,
					':seed'     => mt_rand(1, 1000000),
				]
			);
			$created++;
		}

		return $created;
	}
}
