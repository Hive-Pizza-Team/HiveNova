<?php

namespace HiveNova\Core;

class FleetPlanetDeduction
{
	/**
	 * @param array<int, float|int|string> $fleetArray
	 * @param array<int, string> $resource
	 * @param array<string, mixed> $params
	 * @param list<string> $planetQuery
	 */
	public static function deductShipsAndDeuterium(
		$db,
		int $fleetStartPlanetID,
		array $fleetArray,
		array $resource,
		array $params,
		array $planetQuery,
		float $consumption
	): void {
		if ($fleetStartPlanetID <= 0) {
			return;
		}

		$handle = $db->getHandle();
		if ($handle instanceof \PDO) {
			$ownsTransaction = !$handle->inTransaction();

			if ($ownsTransaction) {
				$db->beginTransaction();
			}

			try {
				$selectCols = array();
				foreach (array_keys($fleetArray) as $shipId) {
					$selectCols[] = $resource[$shipId];
				}
				if ($consumption > 0) {
					$selectCols[] = $resource[RESOURCE_DEUTERIUM];
				}
				$selectCols = array_unique($selectCols);

				$lockedPlanet = $db->selectSingle(
					'SELECT '.implode(', ', $selectCols).' FROM %%PLANETS%% WHERE id = :planetId FOR UPDATE',
					array(':planetId' => $fleetStartPlanetID)
				);

				if (!is_array($lockedPlanet)) {
					throw new \RuntimeException('Planet not found for fleet dispatch');
				}

				foreach ($fleetArray as $shipId => $shipCount) {
					$col = $resource[$shipId];
					if ((float) ($lockedPlanet[$col] ?? 0) < (float) $shipCount) {
						throw new \RuntimeException('Insufficient ships on planet');
					}
				}
				if ($consumption > 0 && (float) ($lockedPlanet[$resource[RESOURCE_DEUTERIUM]] ?? 0) < (float) $consumption) {
					throw new \RuntimeException('Insufficient deuterium on planet');
				}

				$whereGuards = array();
				foreach ($fleetArray as $shipId => $shipCount) {
					$whereGuards[] = $resource[$shipId].' >= :'.$resource[$shipId];
				}
				if ($consumption > 0) {
					$whereGuards[] = $resource[RESOURCE_DEUTERIUM].' >= :'.$resource[RESOURCE_DEUTERIUM];
				}

				$sql = 'UPDATE %%PLANETS%% SET '.implode(', ', $planetQuery).' WHERE id = :planetId AND '.implode(' AND ', $whereGuards).';';
				$db->update($sql, $params);

				if ($db->rowCount() < 1) {
					throw new \RuntimeException('Insufficient ships or deuterium on planet');
				}

				if ($ownsTransaction) {
					$db->commit();
				}
			} catch (\Throwable $e) {
				if ($ownsTransaction) {
					$db->rollback();
				}
				throw $e;
			}

			return;
		}

		$sql = 'UPDATE %%PLANETS%% SET '.implode(', ', $planetQuery).' WHERE id = :planetId;';
		$db->update($sql, $params);
	}
}
