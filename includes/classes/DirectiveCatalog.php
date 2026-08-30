<?php

namespace HiveNova\Core;

/**
 * v1 Empire Directive catalog (PHP config, not admin CMS).
 */
class DirectiveCatalog
{
	public const INDUSTRIAL = 'industrial_surge';
	public const DEFENSIVE = 'defensive_posture';
	public const EXPLORATION = 'exploration_push';
	public const TRADE = 'trade_surplus';

	public const TRADE_CARGO_THRESHOLD = 10000;

	/** Catalog reward amounts are the payout at this many total points. */
	public const REWARD_REFERENCE_POINTS = 10000;

	/** Floor so a day-one empire (0–500 points) is not given the full stockpile. */
	public const REWARD_MIN_FACTOR = 0.05;

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array
	{
		return [
			self::INDUSTRIAL => [
				'key' => self::INDUSTRIAL,
				'title_key' => 'cm_dir_industrial',
				'desc_key' => 'cm_dir_industrial_desc',
				'suggestion_key' => 'cm_suggest_industrial',
				'recommended_stance' => 'balanced',
				'targets' => [
					'build_complete' => 8,
				],
				'reward' => [
					'metal' => 50000,
					'crystal' => 25000,
					'deuterium' => 10000,
				],
			],
			self::DEFENSIVE => [
				'key' => self::DEFENSIVE,
				'title_key' => 'cm_dir_defensive',
				'desc_key' => 'cm_dir_defensive_desc',
				'suggestion_key' => 'cm_suggest_defensive',
				'recommended_stance' => 'cautious',
				'targets' => [
					'defense_complete' => 6,
					'hold_success' => 1,
				],
				'reward' => [
					'metal' => 40000,
					'crystal' => 20000,
					'deuterium' => 15000,
				],
			],
			self::EXPLORATION => [
				'key' => self::EXPLORATION,
				'title_key' => 'cm_dir_exploration',
				'desc_key' => 'cm_dir_exploration_desc',
				'suggestion_key' => 'cm_suggest_exploration',
				'recommended_stance' => 'aggressive',
				'targets' => [
					'expedition_dispatch' => 5,
				],
				'reward' => [
					'metal' => 30000,
					'crystal' => 30000,
					'deuterium' => 20000,
				],
			],
			self::TRADE => [
				'key' => self::TRADE,
				'title_key' => 'cm_dir_trade',
				'desc_key' => 'cm_dir_trade_desc',
				'suggestion_key' => 'cm_suggest_trade',
				'recommended_stance' => 'balanced',
				'targets' => [
					'trade_run' => 3,
				],
				'reward' => [
					'metal' => 45000,
					'crystal' => 45000,
					'deuterium' => 15000,
				],
			],
		];
	}

	public static function exists(string $key): bool
	{
		return isset(self::all()[$key]);
	}

	public static function rewardFactor(int $points): float
	{
		$points = max(0, $points);

		return max(self::REWARD_MIN_FACTOR, $points / self::REWARD_REFERENCE_POINTS);
	}

	/**
	 * @param array{metal?: int, crystal?: int, deuterium?: int} $reward
	 * @return array{metal: int, crystal: int, deuterium: int}
	 */
	public static function scaledReward(array $reward, int $points): array
	{
		$factor = self::rewardFactor($points);

		return [
			'metal' => (int) floor(((int) ($reward['metal'] ?? 0)) * $factor),
			'crystal' => (int) floor(((int) ($reward['crystal'] ?? 0)) * $factor),
			'deuterium' => (int) floor(((int) ($reward['deuterium'] ?? 0)) * $factor),
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get(string $key): ?array
	{
		return self::all()[$key] ?? null;
	}

	/**
	 * @return array<string, int>
	 */
	public static function emptyProgress(string $key): array
	{
		$def = self::get($key);
		if ($def === null) {
			return [];
		}
		$progress = [];
		foreach (array_keys($def['targets']) as $counter) {
			$progress[$counter] = 0;
		}

		return $progress;
	}

	/**
	 * Map a recorded event type to the catalog counter for a directive, or null.
	 */
	public static function counterForEvent(string $directiveKey, string $eventType, array $context = []): ?string
	{
		return match ($directiveKey) {
			self::INDUSTRIAL => in_array($eventType, ['building_complete', 'research_complete', 'build_complete'], true)
				? 'build_complete' : null,
			self::DEFENSIVE => match ($eventType) {
				'defense_complete' => 'defense_complete',
				'hold_success' => 'hold_success',
				default => null,
			},
			self::EXPLORATION => $eventType === 'expedition_dispatch' ? 'expedition_dispatch' : null,
			self::TRADE => in_array($eventType, ['transport_delivery', 'recycle_success', 'trade_run'], true)
				? 'trade_run' : null,
			default => null,
		};
	}

	public static function eventCountsToward(string $directiveKey, string $eventType, array $context = []): bool
	{
		if ($directiveKey === self::TRADE && in_array($eventType, ['transport_delivery', 'recycle_success', 'trade_run'], true)) {
			$cargo = (int) ($context['cargo'] ?? 0);
			return $cargo >= self::TRADE_CARGO_THRESHOLD;
		}

		return self::counterForEvent($directiveKey, $eventType, $context) !== null;
	}

	public static function progressPercent(int $have, int $need): int
	{
		if ($need <= 0) {
			return 0;
		}

		return (int) min(100, max(0, round($have / $need * 100)));
	}

	/**
	 * @param array<string, int> $targets
	 * @param array<string, mixed> $progress
	 * @return list<array{counter: string, have: int, need: int, pct: int}>
	 */
	public static function progressBars(array $targets, array $progress): array
	{
		$bars = [];
		foreach ($targets as $counter => $need) {
			$need = (int) $need;
			$have = (int) ($progress[$counter] ?? 0);
			$bars[] = [
				'counter' => (string) $counter,
				'have' => $have,
				'need' => $need,
				'pct' => self::progressPercent($have, $need),
			];
		}

		return $bars;
	}
}
