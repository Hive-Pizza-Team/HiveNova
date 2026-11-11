<?php

namespace HiveNova\Core;

/**
 * Shared energy balance for the top bar, Resources, and Empire.
 *
 * Planet rows store produced energy in `energy` and consumption in
 * `energy_used` (≤ 0 by convention). Available balance is produced − consumed.
 *
 * Energy is a capacity balance, not a stock that accumulates over hours/days.
 */
class EnergyBalanceService
{
	/**
	 * Remaining energy (negative when consumption exceeds production).
	 *
	 * `energy_used` is normally ≤ 0. A positive value is treated as consumption magnitude.
	 */
	public static function available(float|int|string $energy, float|int|string $energyUsed): float
	{
		$produced = (float) $energy;
		$usedRaw = (float) $energyUsed;

		if ($usedRaw > 0.0) {
			return $produced - $usedRaw;
		}

		return $produced + $usedRaw;
	}

	/**
	 * Absolute consumption drawn from `energy_used`.
	 */
	public static function consumed(float|int|string $energyUsed): float
	{
		return abs((float) $energyUsed);
	}

	/**
	 * Top-bar fields: available vs produced (same pair Empire uses).
	 *
	 * @return array{
	 *   name: string,
	 *   used: float,
	 *   max: float,
	 *   available: float,
	 *   consumed: float,
	 *   inDeficit: bool
	 * }
	 */
	public static function headerResourceFields(
		float|int|string $energy,
		float|int|string $energyUsed,
		string $name = 'energy'
	): array {
		$produced = (float) $energy;
		$consumed = self::consumed($energyUsed);
		$available = self::available($produced, $energyUsed);

		return [
			'name'      => $name,
			// Legacy raw used (≤ 0) for older topnav snippets.
			'used'      => -$consumed,
			'max'       => $produced,
			'available' => $available,
			'consumed'  => $consumed,
			'inDeficit' => $available < 0.0,
		];
	}

	/**
	 * Resources page energy totals — same available/produced as the header.
	 *
	 * Daily/weekly are omitted (null): energy is not an hourly stock.
	 *
	 * @return array{
	 *   available: float,
	 *   produced: float,
	 *   basic: float,
	 *   consumed: float,
	 *   daily: null,
	 *   weekly: null
	 * }
	 */
	public static function resourcesSummary(
		float|int|string $energy,
		float|int|string $energyUsed,
		float|int|string $basicIncome = 0
	): array {
		$produced = (float) $energy;
		$basic = (float) $basicIncome;
		$consumed = self::consumed($energyUsed);
		$available = self::available($produced + $basic, $energyUsed);

		return [
			'available' => $available,
			'produced'  => $produced + $basic,
			'basic'     => $basic,
			'consumed'  => $consumed,
			'daily'     => null,
			'weekly'    => null,
		];
	}
}
