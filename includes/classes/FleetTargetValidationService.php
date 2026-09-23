<?php

namespace HiveNova\Core;

/**
 * Destination-step rules for empty / missing planet slots.
 */
class FleetTargetValidationService
{
	public const TYPE_PLANET = 1;
	public const TYPE_DEBRIS = 2;
	public const TYPE_MOON = 3;

	/**
	 * Whether an uninhabited coordinate may continue to the mission step.
	 *
	 * Colonisation needs empty planet slots. Expedition and market use reserved
	 * planet indices. Salvage can target a PvE package with no owner row.
	 *
	 * @param array<int, mixed> $ships
	 */
	public static function allowsMissingPlanet(
		array $ships,
		int $targetType,
		int $targetPlanet,
		int $maxPlanets,
		bool $hasPvePackage = false
	): bool {
		if ($targetPlanet === $maxPlanets + 1 || $targetPlanet === $maxPlanets + 2) {
			return true;
		}

		if ($hasPvePackage) {
			return true;
		}

		return $targetType === self::TYPE_PLANET
			&& isset($ships[SHIP_COLONY_SHIP])
			&& (float) $ships[SHIP_COLONY_SHIP] > 0;
	}

	/**
	 * Prefer the session fleet token; fall back to the legacy kolo flag.
	 *
	 * @param array<string, mixed> $sessionFleetByToken
	 * @return array<int, mixed>
	 */
	public static function resolveShipsForCheck(array $sessionFleetByToken, string $token, bool $colonyFlag): array
	{
		if ($token !== '' && isset($sessionFleetByToken[$token]['fleet']) && is_array($sessionFleetByToken[$token]['fleet'])) {
			return $sessionFleetByToken[$token]['fleet'];
		}

		if ($colonyFlag) {
			return [SHIP_COLONY_SHIP => 1];
		}

		return [];
	}
}
