<?php

namespace HiveNova\Core;

class GalaxyPlayService
{
	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @return array<string, mixed>
	 */
	public static function show(array &$user, array &$planet, int $galaxy, int $system): array
	{
		global $resource, $THEME;

		$config = Config::get();
		$galaxy = min(max($galaxy, 1), (int) $config->max_galaxy);
		$system = min(max($system, 1), (int) $config->max_system);

		$charged = false;
		if ($galaxy !== (int) $planet['galaxy'] || $system !== (int) $planet['system']) {
			$cost = (int) $config->deuterium_cost_galaxy;
			if ($planet['deuterium'] < $cost) {
				throw new \RuntimeException($GLOBALS['LNG']['gl_no_deuterium_to_view_galaxy'] ?? 'Not enough deuterium');
			}
			$planet['deuterium'] -= $cost;
			$charged = true;
		}

		$rows = new GalaxyRows();
		$rows->setGalaxy($galaxy);
		$rows->setSystem($system);
		$rows->setUser($user);
		$rows->setPlanet($planet);
		$result = $rows->getGalaxyData();
		if (!is_array($result)) {
			$result = [];
		}
		$populated = count($result);
		$theme = is_object($THEME) ? $THEME->getTheme() : '';
		$rows->fillUncolonizedSlots($result, (int) $config->max_planets, $galaxy, $system, $theme);

		$slots = [];
		foreach ($result as $position => $row) {
			$slots[] = self::summarizeSlot((int) $position, $row);
		}

		return [
			'galaxy' => $galaxy,
			'system' => $system,
			'populated' => $populated,
			'deuteriumCharged' => $charged,
			'slotsUsed' => FleetFunctions::GetCurrentFleets($user['id']),
			'slotsMax' => FleetFunctions::GetMaxFleetSlots($user),
			'probes' => (int) ($planet[$resource[SHIP_ESPIONAGE_PROBE]] ?? 0),
			'slots' => $slots,
		];
	}

	/**
	 * @param array<string, mixed>|false $row
	 * @return array<string, mixed>
	 */
	public static function summarizeSlot(int $position, $row): array
	{
		if ($row === false || $row === null) {
			return ['position' => $position, 'empty' => true];
		}
		if (!empty($row['uncolonized'])) {
			$planet = is_array($row['planet'] ?? null) ? $row['planet'] : [];
			return [
				'position' => $position,
				'empty' => true,
				'canColonize' => (bool) ($row['canColonize'] ?? false),
				'image' => PlanetImageUtil::hiveThemeImage((string) ($planet['image'] ?? 'unknown')),
			];
		}
		$planet = is_array($row['planet'] ?? null) ? $row['planet'] : [];
		$user = is_array($row['user'] ?? null) ? $row['user'] : [];
		$missions = is_array($row['missions'] ?? null) ? $row['missions'] : [];
		$debris = is_array($row['debris'] ?? null) ? $row['debris'] : [];
		$moon = is_array($row['moon'] ?? null) ? $row['moon'] : [];
		$alliance = is_array($row['alliance'] ?? null) ? $row['alliance'] : [];
		$debrisMetal = (int) ($debris['metal'] ?? 0);
		$debrisCrystal = (int) ($debris['crystal'] ?? 0);

		return [
			'position' => $position,
			'empty' => false,
			'own' => !empty($row['ownPlanet']),
			'planetId' => (int) ($planet['id'] ?? 0),
			'name' => (string) ($planet['name'] ?? ''),
			'image' => PlanetImageUtil::hiveThemeImage((string) ($planet['image'] ?? '')),
			'username' => (string) ($user['username'] ?? ''),
			'userId' => (int) ($user['id'] ?? 0),
			'alliance' => (string) ($alliance['tag'] ?? $alliance['name'] ?? ''),
			'lastActivity' => (string) ($row['lastActivity'] ?? ''),
			'debris' => [
				'metal' => $debrisMetal,
				'crystal' => $debrisCrystal,
			],
			'moon' => isset($moon['id']) ? [
				'id' => (int) $moon['id'],
				'name' => (string) ($moon['name'] ?? 'Moon'),
				'image' => 'mond',
			] : null,
			'canSpy' => !empty($missions[FLEET_MISSION_SPY]),
			'canAttack' => !empty($missions[FLEET_MISSION_ATTACK]),
			'canTransport' => !empty($missions[FLEET_MISSION_TRANSPORT]),
			'canRecycle' => !empty($missions[FLEET_MISSION_RECYCLE]) && ($debrisMetal + $debrisCrystal) > 0,
		];
	}
}
