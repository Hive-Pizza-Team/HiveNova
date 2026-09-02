<?php

namespace HiveNova\Core;

/**
 * Fleet snapshot for the in-game Map and public lobby hero.
 *
 * Systems are always jittered. Public lobby snapshots also jitter galaxy and
 * planet and omit mission / flight duration / size so logged-out visitors
 * cannot reconstruct real targets from `#lobby-viz-config`.
 */
class FleetVizSnapshotService
{
	public const LIMIT_INGAME = 100;

	public const LIMIT_PER_UNI_LOBBY = 40;

	/**
	 * Single-universe snapshot (in-game Map by default).
	 *
	 * @return array{id: int, name: string, maxGalaxy: int, maxSystem: int, maxPlanets: int, fleets: list<array<string, mixed>>}
	 */
	public function forUniverse(int $universeId, int $limit = self::LIMIT_INGAME, bool $publicSafe = false): array
	{
		$config = Config::get($universeId);
		$limit = max(1, min(100, $limit));

		return [
			'id'         => $universeId,
			'name'       => (string) $config->uni_name,
			'maxGalaxy'  => (int) $config->max_galaxy,
			'maxSystem'  => (int) $config->max_system,
			'maxPlanets' => (int) $config->max_planets,
			'fleets'     => $this->fetchFleets(
				$universeId,
				(int) $config->max_system,
				(int) $config->max_galaxy,
				(int) $config->max_planets,
				$limit,
				$publicSafe
			),
		];
	}

	/**
	 * Open universes for the public lobby hero (strict anonymization).
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
			$universes[] = $this->forUniverse($uniId, $perUniLimit, true);
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
	private function fetchFleets(
		int $universeId,
		int $maxSystem,
		int $maxGalaxy,
		int $maxPlanets,
		int $limit,
		bool $publicSafe
	): array {
		$maxGalaxy = max(1, $maxGalaxy);
		$maxPlanets = max(1, $maxPlanets);

		if ($publicSafe) {
			$select = 'GREATEST(1, LEAST(:maxGalaxy, fleet_start_galaxy + FLOOR(RAND() * 3) - 1)) as startGroup,
				CASE
					WHEN fleet_start_system < 6 THEN fleet_start_system + FLOOR(RAND() * 6)
					WHEN fleet_start_system > :maxSystem - 6 THEN fleet_start_system - FLOOR(RAND() * 6)
					ELSE fleet_start_system + FLOOR(RAND() * 11) - 5
				END AS startCircle,
				1 + FLOOR(RAND() * :maxPlanets) as startPoint,
				GREATEST(1, LEAST(:maxGalaxy, fleet_end_galaxy + FLOOR(RAND() * 3) - 1)) as endGroup,
				CASE
					WHEN fleet_end_system < 6 THEN fleet_end_system + FLOOR(RAND() * 6)
					WHEN fleet_end_system > :maxSystem - 6 THEN fleet_end_system - FLOOR(RAND() * 6)
					ELSE fleet_end_system + FLOOR(RAND() * 11) - 5
				END AS endCircle,
				1 + FLOOR(RAND() * :maxPlanets) as endPoint';
		} else {
			$select = 'fleet_start_galaxy as startGroup,
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
				GREATEST(1, LEAST(5, CEIL(LOG10(GREATEST(fleet_amount, 1) + 1)))) AS sizeClass';
		}

		$params = [
			':maxSystem'      => $maxSystem,
			':fleet_universe' => $universeId,
		];
		if ($publicSafe) {
			$params[':maxGalaxy'] = $maxGalaxy;
			$params[':maxPlanets'] = $maxPlanets;
		}

		try {
			$rows = Database::get()->select(
				'SELECT ' . $select . '
				FROM %%FLEETS%%
				WHERE fleet_universe = :fleet_universe
				ORDER BY fleet_id
				LIMIT ' . $limit,
				$params
			);
		} catch (\Throwable $e) {
			error_log('FleetVizSnapshotService: ' . $e->getMessage());
			return [];
		}

		$out = [];
		foreach ($rows as $row) {
			$fleet = [
				'startGroup'  => (int) ($row['startGroup'] ?? 0),
				'startCircle' => (int) ($row['startCircle'] ?? 0),
				'startPoint'  => (int) ($row['startPoint'] ?? 0),
				'endGroup'    => (int) ($row['endGroup'] ?? 0),
				'endCircle'   => (int) ($row['endCircle'] ?? 0),
				'endPoint'    => (int) ($row['endPoint'] ?? 0),
			];
			if (!$publicSafe) {
				$fleet['duration'] = (float) ($row['duration'] ?? 5);
				$fleet['mission'] = (int) ($row['mission'] ?? 0);
				$fleet['sizeClass'] = (int) ($row['sizeClass'] ?? 1);
			}
			$out[] = $fleet;
		}

		return $out;
	}
}
