<?php

namespace HiveNova\Core;

/**
 * Colonization slot / capacity rules (Astrophysics).
 */
class PlanetColonizationRules
{
	static public function maxPlanetCount($USER)
	{
		global $resource;
		$config	= Config::get($USER['universe']);

		$planetPerTech	= $config->planets_tech;
		$planetPerBonus	= $config->planets_officier;

		if($config->min_player_planets == 0)
		{
			$planetPerTech = 999;
		}

		if($config->min_player_planets == 0)
		{
			$planetPerBonus = 999;
		}

		return (int) ceil($config->min_player_planets + min($planetPerTech, $USER[$resource[124]] * $config->planets_per_tech) + min($planetPerBonus, $USER['factor']['Planets']));
	}

	static public function countOwnedPlanets($USER)
	{
		if (empty($USER['id'])) {
			return self::countOwnedPlanetsFromCache($USER);
		}

		try {
			$sql = 'SELECT COUNT(*) as state
			FROM %%PLANETS%%
			WHERE id_owner = :userId
			AND universe = :universe
			AND planet_type = :type
			AND destruyed = :destroyed;';

			return (int) Database::get()->selectSingle($sql, array(
				':userId'     => (int) $USER['id'],
				':universe'   => (int) $USER['universe'],
				':type'       => 1,
				':destroyed'  => 0,
			), 'state');
		} catch (\Throwable $e) {
			return self::countOwnedPlanetsFromCache($USER);
		}
	}

	static public function countOwnedPlanetsFromCache($USER)
	{
		if (!isset($USER['PLANETS']) || !is_array($USER['PLANETS'])) {
			return 0;
		}

		$count = 0;
		foreach ($USER['PLANETS'] as $planet) {
			if (!is_array($planet)) {
				continue;
			}
			if ((int) ($planet['planet_type'] ?? 1) !== 1) {
				continue;
			}
			if (!empty($planet['destruyed'])) {
				continue;
			}
			$count++;
		}

		return $count;
	}

	static public function hasColonizationCapacity($USER)
	{
		return self::countOwnedPlanets($USER) < self::maxPlanetCount($USER);
	}

	static public function allowPlanetPosition($position, $USER)
	{
		global $resource;
		$config	= Config::get($USER['universe']);

		switch($position) {
			case 1:
			case ($config->max_planets):
				return $USER[$resource[124]] >= 8;
			break;
			case 2:
			case ($config->max_planets-1):
				return $USER[$resource[124]] >= 6;
			break;
			case 3:
			case ($config->max_planets-2):
				return $USER[$resource[124]] >= 4;
			break;
			default:
				return $USER[$resource[124]] >= 1;
			break;
		}
	}
}
