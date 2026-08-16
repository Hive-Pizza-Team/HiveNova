<?php

namespace HiveNova\Core;

/**
 * Metal and silicon mine bonuses by solar-system slot.
 * Uranium and solar-satellite energy stay on temperature formulas.
 */
class PlanetProductionBonus
{
	public const METAL = 901;
	public const CRYSTAL = 902;

	private const METAL_BY_SLOT = [
		6  => 1.17,
		7  => 1.23,
		8  => 1.35,
		9  => 1.23,
		10 => 1.17,
	];

	private const CRYSTAL_BY_SLOT = [
		1 => 1.40,
		2 => 1.30,
		3 => 1.20,
	];

	/**
	 * @return array{901: float, 902: float}
	 */
	public static function factors(int $planetType, int $slot): array
	{
		if ($planetType !== 1) {
			return [self::METAL => 1.0, self::CRYSTAL => 1.0];
		}

		return [
			self::METAL   => self::METAL_BY_SLOT[$slot] ?? 1.0,
			self::CRYSTAL => self::CRYSTAL_BY_SLOT[$slot] ?? 1.0,
		];
	}

	public static function forResource(int $planetType, int $slot, int $resourceId): float
	{
		$factors = self::factors($planetType, $slot);

		return $factors[$resourceId] ?? 1.0;
	}
}
