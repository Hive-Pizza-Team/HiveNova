<?php

namespace HiveNova\Core;

/**
 * Thin hooks so mission/page classes stay testable.
 */
class DirectiveHooks
{
	public static function enabled(): bool
	{
		if (!defined('MODULE_COMMANDER')) {
			return false;
		}
		try {
			return isModuleAvailable(MODULE_COMMANDER);
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * @param array<int, int|float> $builded
	 * @param array<string, mixed> $user
	 */
	public static function afterBuildCompleted(array $builded, array $user): void
	{
		if (!self::enabled() || $builded === []) {
			return;
		}

		global $reslist;
		$userId = (int) ($user['id'] ?? 0);
		$universe = (int) ($user['universe'] ?? Universe::current());
		$defense = $reslist['defense'] ?? [];
		$tech = $reslist['tech'] ?? [];
		$build = $reslist['build'] ?? [];

		foreach ($builded as $elementId => $count) {
			$elementId = (int) $elementId;
			$times = max(1, (int) $count);
			$event = 'building_complete';
			if (in_array($elementId, $defense, true)) {
				$event = 'defense_complete';
			} elseif (in_array($elementId, $tech, true)) {
				$event = 'research_complete';
			} elseif (!in_array($elementId, $build, true) && $elementId >= 400) {
				$event = 'defense_complete';
			}
			for ($i = 0; $i < $times; $i++) {
				DirectiveProgressService::record($userId, $event, ['universe' => $universe, 'element' => $elementId]);
			}
		}
	}

	public static function afterExpeditionDispatch(int $userId, int $universe = 1): void
	{
		DirectiveProgressService::record($userId, 'expedition_dispatch', ['universe' => $universe]);
	}

	public static function afterTransport(int $userId, int $metal, int $crystal, int $deuterium, int $universe = 1): void
	{
		DirectiveProgressService::record($userId, 'transport_delivery', [
			'universe' => $universe,
			'cargo' => $metal + $crystal + $deuterium,
		]);
	}

	public static function afterRecycle(int $userId, int $metal, int $crystal, int $universe = 1): void
	{
		DirectiveProgressService::record($userId, 'recycle_success', [
			'universe' => $universe,
			'cargo' => $metal + $crystal,
		]);
	}

	public static function afterHoldSuccess(int $userId, int $universe = 1): void
	{
		DirectiveProgressService::record($userId, 'hold_success', ['universe' => $universe]);
	}
}
