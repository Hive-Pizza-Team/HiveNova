<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\ResourceUpdate;

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */


class ShowImperiumPage extends AbstractGamePage
{
	public static $requireModule = MODULE_IMPERIUM;

	function __construct() 
	{
		parent::__construct();
	}

	function show()
	{
		global $USER, $PLANET, $resource, $reslist, $LNG;

		$planets = $this->loadPlanets(false);
		$planetList = $this->buildHeaderPlanetList($planets, $USER, $resource, $reslist);

		$this->assign(array(
			'colspan'    => count($planets) + 2,
			'planetList' => $planetList,
		));

		$this->display('page.empire.default.tpl');
	}

	/**
	 * AJAX: build/fleet/defense/missile/tech matrix for <details> expand.
	 */
	public function matrix()
	{
		global $USER, $resource, $reslist, $LNG;

		$planets = $this->loadPlanets(true);
		$payload = self::buildMatrixPayload($planets, $USER, $resource, $reslist, $LNG['tech'] ?? []);

		$this->sendJSON($payload);
	}

	/**
	 * @param bool $includeMatrixColumns fleet/defense/missile counts for the expand matrix
	 * @return list<array<string, mixed>>
	 */
	private function loadPlanets(bool $includeMatrixColumns): array
	{
		global $USER, $PLANET, $resource, $reslist;

		$db = Database::get();
		$order = $USER['planet_sort_order'] == 1 ? 'DESC' : 'ASC';

		$selectColumns = self::imperiumPlanetSelectColumns($resource, $reslist, $includeMatrixColumns);
		$selectList = implode(', ', array_map(
			static fn(string $col): string => $col === 'system' ? '`system`' : $col,
			$selectColumns
		));

		$sql = 'SELECT ' . $selectList . ' FROM %%PLANETS%% WHERE id_owner = :userID AND destruyed = \'0\' ORDER BY ';

		match ($USER['planet_sort']) {
			2 => $sql .= 'name '.$order,
			1 => $sql .= 'galaxy '.$order.', `system` '.$order.', planet '.$order.', planet_type '.$order,
			default => $sql .= 'id '.$order,
		};

		$PlanetsRAW = $db->select($sql, array(
			':userID' => $USER['id'],
		));

		$planets = array();
		$PlanetRessFull = new ResourceUpdate(true, true);
		$PlanetRessFull->setResourceData($resource, $reslist);
		$PlanetRessLite = new ResourceUpdate(false, false);
		$PlanetRessLite->setResourceData($resource, $reslist);

		foreach ($PlanetsRAW as $CPLANET) {
			if ((int) $CPLANET['id'] === (int) $PLANET['id']) {
				$planets[] = array_merge($CPLANET, $PLANET);
				continue;
			}

			$shouldSave = self::planetEcoNeedsPersist($USER, $CPLANET);
			$eco = $shouldSave ? $PlanetRessFull : $PlanetRessLite;
			list($USER, $CPLANET) = $eco->CalcResource($USER, $CPLANET, $shouldSave);

			$planets[] = $CPLANET;
		}

		return $planets;
	}

	/**
	 * @param list<array<string, mixed>> $planets
	 * @param array<string, mixed> $user
	 * @param array<int|string, string> $resource
	 * @param array<string, list<int>> $reslist
	 * @return array<string, mixed>
	 */
	private function buildHeaderPlanetList(array $planets, array $user, array $resource, array $reslist): array
	{
		$config = Config::get($user['universe']);
		$planetList = array(
			'image'           => array(),
			'name'            => array(),
			'coords'          => array(),
			'field'           => array(),
			'resource'        => array(),
			'resourcePerHour' => array(),
			'planet_type'     => array(),
		);

		foreach ($planets as $Planet) {
			$planetList['name'][$Planet['id']] = $Planet['name'];
			$planetList['image'][$Planet['id']] = $Planet['image'];

			$planetList['coords'][$Planet['id']]['galaxy'] = $Planet['galaxy'];
			$planetList['coords'][$Planet['id']]['system'] = $Planet['system'];
			$planetList['coords'][$Planet['id']]['planet'] = $Planet['planet'];

			$planetList['field'][$Planet['id']]['current'] = $Planet['field_current'];
			$planetList['field'][$Planet['id']]['max'] = CalculateMaxPlanetFields($Planet);

			$planetList['resource'][901][$Planet['id']] = $Planet['metal'];
			$planetList['resource'][902][$Planet['id']] = $Planet['crystal'];
			$planetList['resource'][903][$Planet['id']] = $Planet['deuterium'];
			$planetList['resource'][911][$Planet['id']] = $Planet['energy'];

			if ($Planet['planet_type'] == 1) {
				$basic901 = $config->metal_basic_income * $config->resource_multiplier;
				$basic902 = $config->crystal_basic_income * $config->resource_multiplier;
				$basic903 = $config->deuterium_basic_income * $config->resource_multiplier;
			} else {
				$basic901 = 0;
				$basic902 = 0;
				$basic903 = 0;
			}

			$planetList['resourcePerHour'][901][$Planet['id']] = $basic901 + $Planet['metal_perhour'];
			$planetList['resourcePerHour'][902][$Planet['id']] = $basic902 + $Planet['crystal_perhour'];
			$planetList['resourcePerHour'][903][$Planet['id']] = $basic903 + $Planet['deuterium_perhour'];

			$planetList['planet_type'][$Planet['id']] = $Planet['planet_type'];
		}

		return $planetList;
	}

	/**
	 * @param list<array<string, mixed>> $planets
	 * @param array<string, mixed> $user
	 * @param array<int|string, string> $resource
	 * @param array<string, list<int>> $reslist
	 * @param array<int|string, string> $techNames
	 * @return array{colspan: int, sections: array<string, list<array<string, mixed>>>}
	 */
	public static function buildMatrixPayload(
		array $planets,
		array $user,
		array $resource,
		array $reslist,
		array $techNames
	): array {
		$buckets = array(
			'build'    => array(),
			'fleet'    => array(),
			'defense'  => array(),
			'missiles' => array(),
			'tech'     => array(),
		);

		foreach ($planets as $Planet) {
			foreach ($reslist['build'] as $elementID) {
				$buckets['build'][$elementID][$Planet['id']] = (int) ($Planet[$resource[$elementID]] ?? 0);
			}
			foreach ($reslist['fleet'] as $elementID) {
				$buckets['fleet'][$elementID][$Planet['id']] = (int) ($Planet[$resource[$elementID]] ?? 0);
			}
			foreach ($reslist['defense'] as $elementID) {
				$buckets['defense'][$elementID][$Planet['id']] = (int) ($Planet[$resource[$elementID]] ?? 0);
			}
			$buckets['missiles'][502][$Planet['id']] = (int) ($Planet[$resource[502]] ?? 0);
			$buckets['missiles'][503][$Planet['id']] = (int) ($Planet[$resource[503]] ?? 0);
		}

		foreach ($reslist['tech'] as $elementID) {
			$buckets['tech'][$elementID] = (int) ($user[$resource[$elementID]] ?? 0);
		}

		foreach (array('build', 'fleet', 'defense', 'missiles') as $bucket) {
			foreach ($buckets[$bucket] as $elementID => $values) {
				if (array_sum($values) <= 0) {
					unset($buckets[$bucket][$elementID]);
				}
			}
		}
		foreach ($buckets['tech'] as $elementID => $tech) {
			if ($tech <= 0) {
				unset($buckets['tech'][$elementID]);
			}
		}

		$sections = array();
		foreach (array('build', 'fleet', 'defense', 'missiles') as $section) {
			$sections[$section] = array();
			foreach ($buckets[$section] as $elementID => $values) {
				$sections[$section][] = array(
					'id'     => (int) $elementID,
					'name'   => $techNames[$elementID] ?? (string) $elementID,
					'total'  => array_sum($values),
					'values' => self::compactMatrixValues($values),
				);
			}
		}
		$sections['tech'] = array();
		foreach ($buckets['tech'] as $elementID => $tech) {
			$sections['tech'][] = array(
				'id'     => (int) $elementID,
				'name'   => $techNames[$elementID] ?? (string) $elementID,
				'total'  => (int) $tech,
				'values' => array(),
			);
		}

		return array(
			'colspan'   => count($planets) + 2,
			'planetIds' => array_map(static fn(array $p): string => (string) $p['id'], $planets),
			'sections'  => $sections,
		);
	}

	/**
	 * Drop zero planet cells from matrix rows (client treats missing as 0).
	 *
	 * @param array<int|string, int|float> $values
	 * @return array<int|string, int|float>
	 */
	public static function compactMatrixValues(array $values): array
	{
		$compact = array();
		foreach ($values as $planetId => $amount) {
			if ((float) $amount != 0.0) {
				$compact[$planetId] = $amount;
			}
		}

		return $compact;
	}

	/**
	 * Planet columns required for the empire overview: template fields, optional
	 * matrix buckets, CalcResource production, and queue/persist gates.
	 *
	 * @param array<int|string, string> $resource
	 * @param array<string, list<int>> $reslist
	 * @return list<string>
	 */
	public static function imperiumPlanetSelectColumns(
		array $resource,
		array $reslist,
		bool $includeMatrixColumns = true
	): array {
		$columns = [
			'id', 'name', 'image',
			'galaxy', 'system', 'planet', 'planet_type',
			'field_current', 'field_max', 'temp_max',
			'metal', 'crystal', 'deuterium', 'energy', 'energy_used',
			'metal_perhour', 'crystal_perhour', 'deuterium_perhour',
			'metal_max', 'crystal_max', 'deuterium_max',
			'last_update', 'eco_hash',
			'b_hangar_id', 'b_hangar', 'b_building', 'b_building_id',
		];

		$buckets = $includeMatrixColumns
			? ['build', 'fleet', 'defense', 'missile', 'storage', 'prod']
			: ['build', 'storage', 'prod'];

		foreach ($buckets as $bucket) {
			if (empty($reslist[$bucket]) || !is_array($reslist[$bucket])) {
				continue;
			}
			foreach ($reslist[$bucket] as $elementId) {
				$col = $resource[$elementId] ?? null;
				if ($col !== null && $col !== '') {
					$columns[] = $col;
				}
			}
		}

		if (!empty($reslist['prod']) && is_array($reslist['prod'])) {
			foreach ($reslist['prod'] as $elementId) {
				$col = $resource[$elementId] ?? null;
				if ($col !== null && $col !== '') {
					$columns[] = $col . '_porcent';
				}
			}
		}

		foreach ([33, 41] as $elementId) {
			$col = $resource[$elementId] ?? null;
			if ($col !== null && $col !== '') {
				$columns[] = $col;
			}
		}

		return array_values(array_unique($columns));
	}

	/**
	 * True when CalcResource may mutate queues / levels that must be written back.
	 * Pure resource accrual can stay in-memory for the empire matrix.
	 *
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 */
	public static function planetEcoNeedsPersist(array $user, array $planet): bool
	{
		if (!empty($planet['b_hangar_id'])) {
			return true;
		}

		if (!empty($planet['b_building']) && (int) $planet['b_building'] <= TIMESTAMP) {
			return true;
		}

		if (!empty($user['b_tech']) && (int) $user['b_tech'] <= TIMESTAMP) {
			return true;
		}

		return false;
	}
}
