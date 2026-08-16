<?php

namespace HiveNova\Core;

use Throwable;

class EventFirehoseWriter
{
	public const EVENT_BATTLE = 'battle';

	public const SIZE_SMALL = 'small';

	public const SIZE_MEDIUM = 'medium';

	public const SIZE_LARGE = 'large';

	public const OUTCOME_ATTACKER = 'attacker';

	public const OUTCOME_DEFENDER = 'defender';

	public const OUTCOME_DRAW = 'draw';

	public const SMALL_MAX = 1_000_000;

	public const MEDIUM_MAX = 100_000_000;

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
					':eventType'	=> self::EVENT_BATTLE,
					':sizeBucket'	=> self::sizeBucket($totalUnitsLost),
					':outcome'		=> self::outcome($won),
				]
			);
		} catch (Throwable $e) {
			error_log('EventFirehoseWriter: ' . $e->getMessage());
		}
	}
}
