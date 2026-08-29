<?php

namespace HiveNova\Core;

use Throwable;

class EventFirehoseWriter
{
	public const EVENT_BATTLE = 'battle';

	public const EVENT_MOON = 'moon';

	public const EVENT_FEAT = 'feat';

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

	public const NAME_MAX_LEN = 32;

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

	/**
	 * Sanitize a public display name for the firehose / lobby.
	 */
	public static function sanitizeName(string $name): string
	{
		$name = trim(strip_tags($name));
		if ($name === '') {
			return '';
		}
		if (function_exists('mb_substr')) {
			return (string) mb_substr($name, 0, self::NAME_MAX_LEN);
		}

		return substr($name, 0, self::NAME_MAX_LEN);
	}

	public static function record(
		int $universe,
		int $time,
		float $totalUnitsLost,
		string $won,
		string $actorName = '',
		string $targetName = ''
	): void {
		self::insert(
			$universe,
			$time,
			self::EVENT_BATTLE,
			self::sizeBucket($totalUnitsLost),
			self::outcome($won),
			$actorName,
			$targetName
		);
	}

	public static function recordMoon(
		int $universe,
		int $time,
		float $diameter,
		string $actorName = ''
	): void {
		self::insert(
			$universe,
			$time,
			self::EVENT_MOON,
			self::moonSizeBucket($diameter),
			self::OUTCOME_FORMED,
			$actorName,
			''
		);
	}

	public static function recordFeat(int $universe, int $time, string $actorName = ''): void
	{
		self::insert(
			$universe,
			$time,
			self::EVENT_FEAT,
			self::SIZE_SMALL,
			self::OUTCOME_FORMED,
			$actorName,
			''
		);
	}

	private static function insert(
		int $universe,
		int $time,
		string $eventType,
		string $sizeBucket,
		string $outcome,
		string $actorName = '',
		string $targetName = ''
	): void {
		try {
			Database::get()->insert(
				'INSERT INTO %%UNIVERSE_EVENTS%% SET
				universe	= :universe,
				time		= :time,
				event_type	= :eventType,
				size_bucket	= :sizeBucket,
				outcome		= :outcome,
				actor_name	= :actorName,
				target_name	= :targetName;',
				[
					':universe'		=> $universe,
					':time'			=> $time,
					':eventType'	=> $eventType,
					':sizeBucket'	=> $sizeBucket,
					':outcome'		=> $outcome,
					':actorName'	=> self::sanitizeName($actorName),
					':targetName'	=> self::sanitizeName($targetName),
				]
			);
		} catch (Throwable $e) {
			error_log('EventFirehoseWriter: ' . $e->getMessage());
		}
	}
}
