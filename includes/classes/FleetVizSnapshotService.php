<?php

namespace HiveNova\Core;

/**
 * Privacy-safe fleet snapshot for the in-game Map and public lobby hero.
 *
 * Exact systems are jittered (same as ShowVizPage). No names, IDs, or ship lists.
 */
class FleetVizSnapshotService
{
	public const LIMIT_INGAME = 100;

	public const LIMIT_PER_UNI_LOBBY = 40;

	/**
	 * Single-universe snapshot (in-game Map).
	 *
	 * @return array{id: int, name: string, maxGalaxy: int, maxSystem: int, maxPlanets: int, fleets: list<array<string, mixed>>}
	 */
	public function forUniverse(int $universeId, int $limit = self::LIMIT_INGAME): array
	{
		$config = Config::get($universeId);
		$limit = max(1, min(100, $limit));

		return [
			'id'         => $universeId,
			'name'       => (string) $config->uni_name,
			'maxGalaxy'  => (int) $config->max_galaxy,
			'maxSystem'  => (int) $config->max_system,
			'maxPlanets' => (int) $config->max_planets,
			'fleets'     => $this->fetchFleets($universeId, (int) $config->max_system, $limit),
		];
	}

	/**
	 * Open universes for the public lobby hero.
	 *
	 * @param list<int>|null $universeIds
	 * @return array{threeSrc: string, universes: list<array{id: int, name: string, maxGalaxy: int, maxSystem: int, maxPlanets: int, fleets: list<array<string, mixed>>}>}
	 */
	public function forOpenUniverses(?array $universeIds = null, int $perUniLimit = self::LIMIT_PER_UNI_LOBBY): array
	{
		$ids = $universeIds;
		if ($ids === null) {
			$ids = [];
			foreach (Universe::availableUniverses() as $uniId) {
				$uniId = (int) $uniId;
				if ((int) Config::get($uniId)->game_disable === 1) {
					$ids[] = $uniId;
				}
			}
		}

		$universes = [];
		foreach ($ids as $uniId) {
			$uniId = (int) $uniId;
			if ($uniId <= 0) {
				continue;
			}
			$config = Config::get($uniId);
			if ((int) $config->game_disable === 0) {
				continue;
			}
			$universes[] = $this->forUniverse($uniId, $perUniLimit);
		}

		$version = (string) (Config::get()->VERSION ?? '');

		return [
			'threeSrc'  => './scripts/threejs/three.min.js?v=' . substr($version, -4),
			'universes' => $universes,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function fetchFleets(int $universeId, int $maxSystem, int $limit): array
	{
		try {
			$rows = Database::get()->select(
				'SELECT fleet_start_galaxy as startGroup,
				CASE
					WHEN fleet_start_system < 6 THEN fleet_start_system + FLOOR(RAND() * 6)
					WHEN fleet_start_system > :maxSystem - 6 THEN fleet_start_system - FLOOR(RAND() * 6)
					ELSE fleet_start_system + FLOOR(RAND() * 11) - 5
				END AS startCircle,
				fleet_start_planet as startPoint,
				fleet_end_galaxy as endGroup,
				CASE
					WHEN fleet_end_system < 6 THEN fleet_end_system + FLOOR(RAND() * 6)
					WHEN fleet_end_system > :maxSystem - 6 THEN fleet_end_system - FLOOR(RAND() * 6)
					ELSE fleet_end_system + FLOOR(RAND() * 11) - 5
				END AS endCircle,
				fleet_end_planet as endPoint,
				(fleet_end_time - fleet_start_time)/100 as duration,
				fleet_mission as mission,
				GREATEST(1, LEAST(5, CEIL(LOG10(GREATEST(fleet_amount, 1) + 1)))) AS sizeClass
				FROM %%FLEETS%%
				WHERE fleet_universe = :fleet_universe
				ORDER BY fleet_id
				LIMIT ' . $limit,
				[
					':maxSystem'      => $maxSystem,
					':fleet_universe' => $universeId,
				]
			);
		} catch (\Throwable $e) {
			error_log('FleetVizSnapshotService: ' . $e->getMessage());
			return [];
		}

		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'startGroup'  => (int) ($row['startGroup'] ?? 0),
				'startCircle' => (int) ($row['startCircle'] ?? 0),
				'startPoint'  => (int) ($row['startPoint'] ?? 0),
				'endGroup'    => (int) ($row['endGroup'] ?? 0),
				'endCircle'   => (int) ($row['endCircle'] ?? 0),
				'endPoint'    => (int) ($row['endPoint'] ?? 0),
				'duration'    => (float) ($row['duration'] ?? 5),
				'mission'     => (int) ($row['mission'] ?? 0),
				'sizeClass'   => (int) ($row['sizeClass'] ?? 1),
			];
		}

		return $out;
	}
}
