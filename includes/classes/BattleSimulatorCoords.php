<?php

namespace HiveNova\Core;

/**
 * Coordinate helpers for the battle simulator combat report.
 *
 * GenerateReport() prints attacker coords from fleet_start_* and defender
 * coords from the defender slot's fleet_start_* (not fleet_end_*).
 */
class BattleSimulatorCoords
{
	public const TYPE_PLANET = 1;
	public const TYPE_MOON = 3;

	/**
	 * @param array<string, mixed> $coords
	 * @param array<string, mixed> $fallback
	 * @return array{galaxy: int, system: int, planet: int, type: int}
	 */
	public static function normalize(array $coords, array $fallback, int $maxGalaxy, int $maxSystem, int $maxPlanet): array
	{
		$fallbackGalaxy = self::positiveInt($fallback['galaxy'] ?? 1, 1);
		$fallbackSystem = self::positiveInt($fallback['system'] ?? 1, 1);
		$fallbackPlanet = self::positiveInt($fallback['planet'] ?? 1, 1);
		$fallbackType = self::planetType($fallback['type'] ?? $fallback['planet_type'] ?? self::TYPE_PLANET, self::TYPE_PLANET);

		return [
			'galaxy' => self::clamp((int) ($coords['galaxy'] ?? 0), 1, $maxGalaxy, $fallbackGalaxy),
			'system' => self::clamp((int) ($coords['system'] ?? 0), 1, $maxSystem, $fallbackSystem),
			'planet' => self::clamp((int) ($coords['planet'] ?? 0), 1, $maxPlanet, $fallbackPlanet),
			'type' => self::planetType(self::firstType($coords), $fallbackType),
		];
	}

	/**
	 * @param array{galaxy: int, system: int, planet: int, type: int} $start
	 * @param array{galaxy: int, system: int, planet: int, type: int} $end
	 * @return array<string, int>
	 */
	public static function attackerFleetDetail(array $start, array $end): array
	{
		return self::fleetDetail($start, $end);
	}

	/**
	 * Defender reports use fleet_start_* as the displayed address.
	 *
	 * @param array{galaxy: int, system: int, planet: int, type: int} $planet
	 * @return array<string, int>
	 */
	public static function defenderFleetDetail(array $planet): array
	{
		return self::fleetDetail($planet, $planet);
	}

	/**
	 * @param array{galaxy: int, system: int, planet: int, type: int} $start
	 * @param array{galaxy: int, system: int, planet: int, type: int} $end
	 * @return array<string, int>
	 */
	private static function fleetDetail(array $start, array $end): array
	{
		return [
			'fleet_start_galaxy' => $start['galaxy'],
			'fleet_start_system' => $start['system'],
			'fleet_start_planet' => $start['planet'],
			'fleet_start_type' => $start['type'],
			'fleet_end_galaxy' => $end['galaxy'],
			'fleet_end_system' => $end['system'],
			'fleet_end_planet' => $end['planet'],
			'fleet_end_type' => $end['type'],
			'fleet_resource_metal' => 0,
			'fleet_resource_crystal' => 0,
			'fleet_resource_deuterium' => 0,
		];
	}

	private static function clamp(int $value, int $min, int $max, int $fallback): int
	{
		if ($value < $min || $value > $max) {
			return $fallback;
		}

		return $value;
	}

	private static function positiveInt(mixed $value, int $fallback): int
	{
		$value = (int) $value;

		return $value > 0 ? $value : $fallback;
	}

	private static function firstType(array $coords): int
	{
		foreach (['type', 'planettype', 'planet_type'] as $key) {
			if (!isset($coords[$key])) {
				continue;
			}
			$type = (int) $coords[$key];
			if ($type === self::TYPE_PLANET || $type === self::TYPE_MOON) {
				return $type;
			}
		}

		return 0;
	}

	private static function planetType(mixed $value, int $fallback): int
	{
		$type = (int) $value;
		if ($type === self::TYPE_PLANET || $type === self::TYPE_MOON) {
			return $type;
		}

		return $fallback;
	}
}
