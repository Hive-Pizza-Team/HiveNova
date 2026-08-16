<?php

namespace HiveNova\Core;

use Throwable;

class EventFirehoseWriter
{
	public const EVENT_BATTLE = 'battle';

	public const EVENT_MOON = 'moon';

	public const SIZE_SMALL = 'small';

	public const SIZE_MEDIUM = 'medium';

	public const SIZE_LARGE = 'large';

	public const OUTCOME_ATTACKER = 'attacker';

	public const OUTCOME_DEFENDER = 'defender';

	public const OUTCOME_DRAW = 'draw';

	public const OUTCOME_FORMED = 'formed';

	public const SMALL_MAX = 1_000_000;

	public const MEDIUM_MAX = 100_000_000;

	public const MOON_SMALL_MAX = 6000;

	public const MOON_MEDIUM_MAX = 8000;

	public static function sizeBucket(float $totalUnitsLost): string
	{
		if ($totalUnitsLost < self::SMALL_MAX) {
			return self::SIZE_SMALL;
		}
		if ($totalUnitsLost < self::MEDIUM_MAX) {
			return self::SIZE_MEDIUM;
		}

		return self::SIZE_LARGE;
	}

	public static function moonSizeBucket(float $diameter): string
	{
		if ($diameter < self::MOON_SMALL_MAX) {
			return self::SIZE_SMALL;
		}
		if ($diameter < self::MOON_MEDIUM_MAX) {
			return self::SIZE_MEDIUM;
		}

		return self::SIZE_LARGE;
	}

	public static function outcome(string $won): string
	{
		return match ($won) {
			'a' => self::OUTCOME_ATTACKER,
			'r' => self::OUTCOME_DEFENDER,
			default => self::OUTCOME_DRAW,
		};
	}

	public static function record(int $universe, int $time, float $totalUnitsLost, string $won): void
	{
		self::insert(
			$universe,
			$time,
			self::EVENT_BATTLE,
			self::sizeBucket($totalUnitsLost),
			self::outcome($won)
		);
	}

	public static function recordMoon(int $universe, int $time, float $diameter): void
	{
		self::insert(
			$universe,
			$time,
			self::EVENT_MOON,
			self::moonSizeBucket($diameter),
			self::OUTCOME_FORMED
		);
	}

	private static function insert(int $universe, int $time, string $eventType, string $sizeBucket, string $outcome): void
	{
		try {
			Database::get()->insert(
				'INSERT INTO %%UNIVERSE_EVENTS%% SET
				universe	= :universe,
				time		= :time,
				event_type	= :eventType,
				size_bucket	= :sizeBucket,
				outcome		= :outcome;',
				[
					':universe'		=> $universe,
					':time'			=> $time,
					':eventType'	=> $eventType,
					':sizeBucket'	=> $sizeBucket,
					':outcome'		=> $outcome,
				]
			);
		} catch (Throwable $e) {
			error_log('EventFirehoseWriter: ' . $e->getMessage());
		}
	}
}
