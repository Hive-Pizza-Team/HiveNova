<?php

namespace HiveNova\Core;

/**
 * Small leftover bonuses for researches that otherwise only gate the tech tree.
 *
 * Laser (120), Ion (121), and Plasma (122) add attack on units that require them.
 * Hyperspace Technology (114) adds cargo on ships that require it.
 * These must not go through getFactors() / bonusAttack.
 */
class LeftoverBonus
{
	public const ATTACK_TECH_IDS = [120, 121, 122];

	public const CARGO_TECH_ID = 114;

	private const FLEET_ID_MIN = 200;

	private const FLEET_ID_MAX = 300;

	public static function percentPerLevel(): float
	{
		return defined('TECH_LEFTOVER_PERCENT_PER_LEVEL')
			? (float) TECH_LEFTOVER_PERCENT_PER_LEVEL
			: 0.01;
	}

	/**
	 * @param array<string, mixed> $player
	 */
	public static function attackMultiplier(int $unitId, array $player): float
	{
		return 1 + self::percentPerLevel() * self::matchingTechLevels($unitId, $player, self::ATTACK_TECH_IDS);
	}

	/**
	 * @param array<string, mixed> $player
	 */
	public static function cargoMultiplier(int $shipId, array $player): float
	{
		if ($shipId < self::FLEET_ID_MIN || $shipId >= self::FLEET_ID_MAX) {
			return 1.0;
		}

		return 1 + self::percentPerLevel() * self::matchingTechLevels($shipId, $player, [self::CARGO_TECH_ID]);
	}

	/**
	 * @param array<string, mixed> $player
	 */
	public static function shipCapacity(int $shipId, int|float $amount, array $player): float
	{
		global $pricelist;

		$capacity = $pricelist[$shipId]['capacity'] ?? 0;

		return $capacity * $amount * self::cargoMultiplier($shipId, $player);
	}

	/**
	 * Map battle-simulator / spy input element IDs onto user tech columns.
	 *
	 * @param array<int|string, mixed> $input
	 * @return array<string, int>
	 */
	public static function playerTechsFromBattleInput(array $input): array
	{
		return [
			'hyperspace_tech' => (int) ($input[114] ?? 0),
			'laser_tech'      => (int) ($input[120] ?? 0),
			'ionic_tech'      => (int) ($input[121] ?? 0),
			'buster_tech'     => (int) ($input[122] ?? 0),
		];
	}

	/**
	 * @param array<string, mixed> $player
	 * @param list<int> $techIds
	 */
	private static function matchingTechLevels(int $unitId, array $player, array $techIds): int
	{
		global $requirements, $resource;

		$sum = 0;
		foreach ($techIds as $techId) {
			if (!isset($requirements[$unitId][$techId])) {
				continue;
			}

			$column = $resource[$techId] ?? null;
			if ($column === null) {
				continue;
			}

			$sum += (int) ($player[$column] ?? 0);
		}

		return $sum;
	}
}
