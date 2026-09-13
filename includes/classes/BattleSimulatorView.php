<?php

namespace HiveNova\Core;

/**
 * Battle simulator form helpers (first-paint fleet rows).
 */
class BattleSimulatorView
{
	/**
	 * Fleet element IDs to render: flyable ships plus any already present in battleinput.
	 *
	 * @param list<int> $fleetIds
	 * @param callable(int):bool $isFlyable
	 * @param array<int|string, mixed> $battleArray Nested battleinput structure
	 * @return list<int>
	 */
	public static function fleetIdsForForm(array $fleetIds, callable $isFlyable, array $battleArray = []): array
	{
		$needed = [];
		foreach ($battleArray as $slot) {
			if (!is_array($slot)) {
				continue;
			}
			foreach ($slot as $side) {
				if (!is_array($side)) {
					continue;
				}
				foreach ($side as $id => $count) {
					$id = (int) $id;
					if ($id >= 200 && $id < 400) {
						$needed[$id] = true;
					}
				}
			}
		}

		$out = [];
		$seen = [];
		foreach ($fleetIds as $id) {
			$id = (int) $id;
			if (isset($seen[$id])) {
				continue;
			}
			if ($isFlyable($id) || isset($needed[$id])) {
				$out[] = $id;
				$seen[$id] = true;
			}
		}

		foreach (array_keys($needed) as $id) {
			if (!isset($seen[$id])) {
				$out[] = $id;
				$seen[$id] = true;
			}
		}

		return $out;
	}

	/**
	 * On compact viewports, drop defense rows that are zero on both sides of every slot.
	 *
	 * @param list<int> $defenseIds
	 * @param array<int|string, mixed> $battleArray
	 * @return list<int>
	 */
	public static function defenseIdsForForm(array $defenseIds, array $battleArray, bool $compact): array
	{
		if (!$compact) {
			return array_values(array_map('intval', $defenseIds));
		}

		$out = [];
		foreach ($defenseIds as $id) {
			$id = (int) $id;
			if (self::hasNonZeroInput($battleArray, $id)) {
				$out[] = $id;
			}
		}

		// Keep a usable empty form: if everything is zero, show the full list.
		return $out === [] ? array_values(array_map('intval', $defenseIds)) : $out;
	}

	/**
	 * Slots to SSR: at least 1, at most requested, driven by battleinput width.
	 *
	 * @param array<int|string, mixed> $battleArray
	 */
	public static function slotsToRender(array $battleArray, int $requestedSlots): int
	{
		$fromInput = 0;
		foreach ($battleArray as $key => $_) {
			if (is_int($key) || ctype_digit((string) $key)) {
				$fromInput = max($fromInput, (int) $key + 1);
			}
		}

		$requestedSlots = max(1, min(10, $requestedSlots));

		return max(1, min(10, max($requestedSlots, $fromInput)));
	}

	/**
	 * @param array<int|string, mixed> $battleArray
	 */
	private static function hasNonZeroInput(array $battleArray, int $id): bool
	{
		foreach ($battleArray as $slot) {
			if (!is_array($slot)) {
				continue;
			}
			foreach ($slot as $side) {
				if (!is_array($side)) {
					continue;
				}
				if (!empty($side[$id]) && (float) $side[$id] != 0.0) {
					return true;
				}
			}
		}

		return false;
	}
}
