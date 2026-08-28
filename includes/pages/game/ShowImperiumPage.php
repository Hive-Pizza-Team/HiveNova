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
		global $USER, $PLANET, $resource, $reslist;

        $db = Database::get();
		
		$order = $USER['planet_sort_order'] == 1 ? 'DESC' : 'ASC';

		$selectColumns = self::imperiumPlanetSelectColumns($resource, $reslist);
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
            ':userID'   => $USER['id']
        ));

        $PLANETS	= array();
		
		$PlanetRess	= new ResourceUpdate();
		$PlanetRess->setResourceData($resource, $reslist);

		foreach ($PlanetsRAW as $CPLANET)
		{
			// Only persist when a queue may complete — avoid N UPDATEs on a read-only empire view.
			$shouldSave = self::planetEcoNeedsPersist($USER, $CPLANET);
			list($USER, $CPLANET)	= $PlanetRess->CalcResource($USER, $CPLANET, $shouldSave);

			$PLANETS[]	= $CPLANET;
			unset($CPLANET);
		}

        $planetList	= array(
			'image'          => array(),
			'name'           => array(),
			'coords'         => array(),
			'field'          => array(),
			'resource'       => array(),
			'resourcePerHour'=> array(),
			'planet_type'    => array(),
			'build'          => array(),
			'fleet'          => array(),
			'defense'        => array(),
			'missiles'       => array(),
			'tech'           => array(),
		);
	$config		= Config::get($USER['universe']);
		foreach($PLANETS as $Planet)
		{
			$planetList['name'][$Planet['id']]					= $Planet['name'];
			$planetList['image'][$Planet['id']]					= $Planet['image'];
			
			$planetList['coords'][$Planet['id']]['galaxy']		= $Planet['galaxy'];
			$planetList['coords'][$Planet['id']]['system']		= $Planet['system'];
			$planetList['coords'][$Planet['id']]['planet']		= $Planet['planet'];
			
			$planetList['field'][$Planet['id']]['current']		= $Planet['field_current'];
			$planetList['field'][$Planet['id']]['max']			= CalculateMaxPlanetFields($Planet);
           
			$planetList['resource'][901][$Planet['id']]			= $Planet['metal'];
			$planetList['resource'][902][$Planet['id']]			= $Planet['crystal'];
			$planetList['resource'][903][$Planet['id']]			= $Planet['deuterium'];
			$planetList['resource'][911][$Planet['id']]			= $Planet['energy'];
			
			if($Planet['planet_type'] == 1){
				$basic[901][$Planet['id']] = $config->metal_basic_income * $config->resource_multiplier;
				$basic[902][$Planet['id']] = $config->crystal_basic_income * $config->resource_multiplier;
				$basic[903][$Planet['id']] = $config->deuterium_basic_income * $config->resource_multiplier;
			}else{
				$basic[901][$Planet['id']] = 0;
				$basic[902][$Planet['id']] = 0;
				$basic[903][$Planet['id']] = 0;
			}

			$planetList['resourcePerHour'][901][$Planet['id']]			= $basic[901][$Planet['id']] + $Planet['metal_perhour'];
			$planetList['resourcePerHour'][902][$Planet['id']]			= $basic[902][$Planet['id']] + $Planet['crystal_perhour'];
			$planetList['resourcePerHour'][903][$Planet['id']]			= $basic[903][$Planet['id']] + $Planet['deuterium_perhour'];
	
			$planetList['planet_type'][$Planet['id']] = $Planet['planet_type'];


			foreach($reslist['build'] as $elementID) {
				$planetList['build'][$elementID][$Planet['id']]	= $Planet[$resource[$elementID]];
			}
			
			foreach($reslist['fleet'] as $elementID) {
				$planetList['fleet'][$elementID][$Planet['id']]	= $Planet[$resource[$elementID]];
			}
			
			foreach($reslist['defense'] as $elementID) {
				$planetList['defense'][$elementID][$Planet['id']]	= $Planet[$resource[$elementID]];
			}
			
			$planetList['missiles'][502][$Planet['id']]         = $Planet[$resource[502]];
            		$planetList['missiles'][503][$Planet['id']]         = $Planet[$resource[503]];
		}

		foreach($reslist['tech'] as $elementID){
			$planetList['tech'][$elementID]	= $USER[$resource[$elementID]];
		}

		foreach (array('build', 'fleet', 'defense', 'missiles') as $bucket) {
			foreach ($planetList[$bucket] as $elementID => $values) {
				if (array_sum($values) <= 0) {
					unset($planetList[$bucket][$elementID]);
				}
			}
		}
		foreach ($planetList['tech'] as $elementID => $tech) {
			if ($tech <= 0) {
				unset($planetList['tech'][$elementID]);
			}
		}

		$matrixSections = array();
		foreach (array('build' => 'build', 'fleet' => 'fleet', 'defense' => 'defense', 'missiles' => 'missiles') as $key => $section) {
			$matrixSections[$section] = array();
			foreach ($planetList[$key] as $elementID => $values) {
				$matrixSections[$section][] = array(
					'id'     => (int) $elementID,
					'name'   => $LNG['tech'][$elementID] ?? (string) $elementID,
					'total'  => array_sum($values),
					'values' => $values,
				);
			}
			unset($planetList[$key]);
		}
		$matrixSections['tech'] = array();
		foreach ($planetList['tech'] as $elementID => $tech) {
			$matrixSections['tech'][] = array(
				'id'    => (int) $elementID,
				'name'  => $LNG['tech'][$elementID] ?? (string) $elementID,
				'total' => (int) $tech,
				'values' => array(),
			);
		}
		unset($planetList['tech']);

		$empireMatrixJson = json_encode(array(
			'colspan'  => count($PLANETS) + 2,
			'sections' => $matrixSections,
		), JSON_UNESCAPED_UNICODE);
		
		$this->assign(array(
			'colspan'          => count($PLANETS) + 2,
			'planetList'       => $planetList,
			'empireMatrixJson' => $empireMatrixJson,
		));

		$this->display('page.empire.default.tpl');
	}

	/**
	 * Planet columns required for the empire overview: template fields, matrix
	 * buckets, CalcResource production, and queue/persist gates.
	 *
	 * @param array<int|string, string> $resource
	 * @param array<string, list<int>> $reslist
	 * @return list<string>
	 */
	public static function imperiumPlanetSelectColumns(array $resource, array $reslist): array
	{
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

		foreach (['build', 'fleet', 'defense', 'missile', 'storage', 'prod'] as $bucket) {
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
