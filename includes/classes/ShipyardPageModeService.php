<?php

namespace HiveNova\Core;

/**
 * Combined Shipyard page: fleet (ships) and defense share one URL
 * (`page=shipyard&mode=fleet|defense`) and one left-nav item.
 */
class ShipyardPageModeService
{
	public const MODE_FLEET = 'fleet';
	public const MODE_DEFENSE = 'defense';

	public static function resolveMode(string $requestedMode, bool $fleetAvailable, bool $defenseAvailable): string
	{
		$wantsDefense = $requestedMode === self::MODE_DEFENSE;

		if ($wantsDefense) {
			if ($defenseAvailable || !$fleetAvailable) {
				return self::MODE_DEFENSE;
			}

			return self::MODE_FLEET;
		}

		if ($fleetAvailable || !$defenseAvailable) {
			return self::MODE_FLEET;
		}

		return self::MODE_DEFENSE;
	}

	/**
	 * @return list<array{mode: string, active: bool}>
	 */
	public static function tabs(string $activeMode, bool $fleetAvailable, bool $defenseAvailable): array
	{
		$tabs = [];

		if ($fleetAvailable) {
			$tabs[] = [
				'mode'   => self::MODE_FLEET,
				'active' => $activeMode === self::MODE_FLEET,
			];
		}

		if ($defenseAvailable) {
			$tabs[] = [
				'mode'   => self::MODE_DEFENSE,
				'active' => $activeMode === self::MODE_DEFENSE,
			];
		}

		if ($tabs === []) {
			$tabs[] = [
				'mode'   => self::MODE_FLEET,
				'active' => $activeMode !== self::MODE_DEFENSE,
			];
			$tabs[] = [
				'mode'   => self::MODE_DEFENSE,
				'active' => $activeMode === self::MODE_DEFENSE,
			];
		}

		return $tabs;
	}

	public static function isDefenseMode(string $mode): bool
	{
		return $mode === self::MODE_DEFENSE;
	}
}
