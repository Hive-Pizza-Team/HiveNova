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

	/** Astrophysics — expedition slots scale from this research. */
	public const RESEARCH_ASTROPHYSICS = 124;

	/** Rocket launcher — first defense unit. */
	public const DEFENSE_MISSILE_LAUNCHER = 401;

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
				'recommended_stance' => 'balanced',
				// Hold (mission 5) needs an alliance or buddy and fails noob protection
				// against established Uni 1 targets, so a new commander cannot finish it.
				// Shipyard defenses are the completable early action.
				'targets' => [
					'defense_complete' => 6,
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

	/**
	 * Unlock gates for the directive picker. Industrial Surge has none.
	 *
	 * - any_accessible: player can build at least one listed element
	 * - min_research: player has researched each listed tech to the min level
	 *
	 * @return array<string, array{any_accessible?: list<int>, min_research?: array<int, int>}>
	 */
	public static function unlockRules(): array
	{
		return [
			self::INDUSTRIAL => [],
			self::DEFENSIVE => [
				'any_accessible' => [self::DEFENSE_MISSILE_LAUNCHER],
			],
			self::EXPLORATION => [
				'min_research' => [self::RESEARCH_ASTROPHYSICS => 1],
			],
			self::TRADE => [
				'any_accessible' => [SHIP_SMALL_CARGO, SHIP_RECYCLER],
			],
		];
	}

	/**
	 * Whether the player has unlocked the ships / research needed for a directive.
	 *
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int|string, array<int|string, int|string>>|null $requirements
	 * @param array<int|string, string>|null $resource
	 */
	public static function isUnlocked(
		string $key,
		array $user,
		array $planet,
		?array $requirements = null,
		?array $resource = null
	): bool {
		if (!self::exists($key)) {
			return false;
		}
		$rules = self::unlockRules()[$key] ?? [];
		if ($rules === []) {
			return true;
		}

		$requirements ??= is_array($GLOBALS['requirements'] ?? null) ? $GLOBALS['requirements'] : [];
		$resource ??= is_array($GLOBALS['resource'] ?? null) ? $GLOBALS['resource'] : [];

		$accessible = $rules['any_accessible'] ?? null;
		if (is_array($accessible) && $accessible !== []) {
			$ok = false;
			foreach ($accessible as $elementId) {
				if (self::isElementAccessible((int) $elementId, $user, $planet, $requirements, $resource)) {
					$ok = true;
					break;
				}
			}
			if (!$ok) {
				return false;
			}
		}

		$minResearch = $rules['min_research'] ?? null;
		if (is_array($minResearch) && $minResearch !== []) {
			foreach ($minResearch as $elementId => $minLevel) {
				if (!self::hasResearchLevel((int) $elementId, (int) $minLevel, $user, $resource)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int|string, array<int|string, int|string>> $requirements
	 * @param array<int|string, string> $resource
	 */
	public static function isElementAccessible(
		int $elementId,
		array $user,
		array $planet,
		array $requirements,
		array $resource
	): bool {
		if (!isset($requirements[$elementId]) || !is_array($requirements[$elementId])) {
			return true;
		}

		foreach ($requirements[$elementId] as $reqElement => $eleLevel) {
			$reqId = (int) $reqElement;
			$need = (int) $eleLevel;
			$column = $resource[$reqId] ?? null;
			if ($column === null) {
				return false;
			}
			$haveUser = isset($user[$column]) ? (int) $user[$column] : 0;
			$havePlanet = isset($planet[$column]) ? (int) $planet[$column] : 0;
			if ($haveUser < $need && $havePlanet < $need) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<int|string, string> $resource
	 */
	public static function hasResearchLevel(int $elementId, int $minLevel, array $user, array $resource): bool
	{
		if ($minLevel <= 0) {
			return true;
		}
		$column = $resource[$elementId] ?? null;
		if ($column === null) {
			return false;
		}

		return (int) ($user[$column] ?? 0) >= $minLevel;
	}

	public static function exists(string $key): bool
	{
		return isset(self::all()[$key]);
	}

	/**
	 * Clamp a points/reward scalar to a non-negative int without overflowing PHP_INT_MAX.
	 */
	public static function clampNonNegativeInt(mixed $value): int
	{
		if (!is_numeric($value)) {
			return 0;
		}
		$asFloat = (float) $value;
		if ($asFloat <= 0) {
			return 0;
		}
		if (!is_finite($asFloat) || $asFloat >= (float) PHP_INT_MAX) {
			return PHP_INT_MAX;
		}

		return (int) $asFloat;
	}

	public static function rewardFactor(mixed $points): float
	{
		$points = self::clampNonNegativeInt($points);

		return max(self::REWARD_MIN_FACTOR, $points / self::REWARD_REFERENCE_POINTS);
	}

	public static function scaleAmount(float $base, float $factor): int
	{
		if ($base <= 0 || $factor <= 0) {
			return 0;
		}
		$scaled = $base * $factor;
		$max = (float) PHP_INT_MAX;
		if (!is_finite($scaled) || $scaled >= $max) {
			return PHP_INT_MAX;
		}
		$floored = floor($scaled);
		if ($floored >= $max) {
			return PHP_INT_MAX;
		}

		return (int) $floored;
	}

	/**
	 * @param array{metal?: int|float, crystal?: int|float, deuterium?: int|float} $reward
	 * @return array{metal: int, crystal: int, deuterium: int}
	 */
	public static function scaledReward(array $reward, mixed $points): array
	{
		$factor = self::rewardFactor($points);

		return [
			'metal' => self::scaleAmount((float) ($reward['metal'] ?? 0), $factor),
			'crystal' => self::scaleAmount((float) ($reward['crystal'] ?? 0), $factor),
			'deuterium' => self::scaleAmount((float) ($reward['deuterium'] ?? 0), $factor),
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
			self::DEFENSIVE => $eventType === 'defense_complete' ? 'defense_complete' : null,
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
			$pct = self::progressPercent($have, $need);
			$bars[] = [
				'counter' => (string) $counter,
				'have' => $need > 0 ? min($have, $need) : $have,
				'need' => $need,
				'pct' => $pct,
			];
		}

		return $bars;
	}
}
