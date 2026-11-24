<?php

namespace HiveNova\Core;

/**
 * Galaxy deuterium view fee: charge once per intentional navigation, not on
 * Back/refresh of a clean coords URL.
 *
 * Intentional navigation = form submit (galaxyNav) or arrow params
 * (galaxyLeft/Right, systemLeft/Right). Those requests charge (when not the
 * home system), record the coords in the session paid set, then PRG-redirect
 * to game.php?page=galaxy&galaxy=X&system=Y.
 *
 * Plain GET with only galaxy/system skips the fee when that system is already
 * in the session paid set (Back/refresh/replay). A plain GET to a system that
 * was never paid this session still charges once (e.g. deep link from
 * statistics) so address-bar browsing is not freely unbounded beyond replays.
 */
class GalaxyViewChargeService
{
	public const SESSION_KEY = 'galaxy_view_paid';
	public const NAV_PARAM = 'galaxyNav';

	/**
	 * @param array<string, mixed> $request
	 */
	public static function isNavigationRequest(array $request): bool
	{
		foreach (['galaxyLeft', 'galaxyRight', 'systemLeft', 'systemRight', self::NAV_PARAM] as $key) {
			if (!empty($request[$key])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array{galaxy: int, system: int}
	 */
	public static function resolveCoordinates(
		int $galaxy,
		int $system,
		int $maxGalaxy,
		int $maxSystem,
		string $galaxyLeft,
		string $galaxyRight,
		string $systemLeft,
		string $systemRight
	): array {
		if ($galaxyLeft !== '') {
			$galaxy = max($galaxy - 1, 1);
		} elseif ($galaxyRight !== '') {
			$galaxy = min($galaxy + 1, $maxGalaxy);
		}

		if ($systemLeft !== '') {
			$system = max($system - 1, 1);
		} elseif ($systemRight !== '') {
			$system = min($system + 1, $maxSystem);
		}

		$galaxy = min(max($galaxy, 1), $maxGalaxy);
		$system = min(max($system, 1), $maxSystem);

		return [
			'galaxy' => $galaxy,
			'system' => $system,
		];
	}

	public static function coordKey(int $galaxy, int $system): string
	{
		return $galaxy . ':' . $system;
	}

	public static function isHomeSystem(
		int $galaxy,
		int $system,
		int $homeGalaxy,
		int $homeSystem
	): bool {
		return $galaxy === $homeGalaxy && $system === $homeSystem;
	}

	/**
	 * @param array<string, bool> $paidSet
	 */
	public static function wasPaid(array $paidSet, int $galaxy, int $system): bool
	{
		return isset($paidSet[self::coordKey($galaxy, $system)]);
	}

	/**
	 * @param array<string, bool> $paidSet
	 */
	public static function shouldCharge(
		int $galaxy,
		int $system,
		int $homeGalaxy,
		int $homeSystem,
		bool $isNavigation,
		array $paidSet
	): bool {
		if (self::isHomeSystem($galaxy, $system, $homeGalaxy, $homeSystem)) {
			return false;
		}

		if ($isNavigation) {
			// Arrow / Show: pay every intentional hop away from home.
			return true;
		}

		// Plain GET: charge only the first time this system is seen this session
		// (deep link). Back/refresh of an already-paid system is free.
		return !self::wasPaid($paidSet, $galaxy, $system);
	}

	/**
	 * @param array<string, bool> $paidSet
	 * @return array<string, bool>
	 */
	public static function markPaid(array $paidSet, int $galaxy, int $system): array
	{
		$paidSet[self::coordKey($galaxy, $system)] = true;

		return $paidSet;
	}

	public static function cleanUrl(int $galaxy, int $system): string
	{
		return 'game.php?page=galaxy&galaxy=' . $galaxy . '&system=' . $system;
	}
}
