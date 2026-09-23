<?php

namespace HiveNova\Core;

use InvalidArgumentException;

/**
 * Resolves the player/planet row used by in-game page chrome (topnav, etc.).
 *
 * AbstractGamePage copies $USER / $PLANET in the constructor, but spend flows
 * (shipyard, buildings, research, …) mutate the live globals afterward.
 * Navigation must prefer those live rows so the header matches the body.
 */
class GamePageState
{
	/**
	 * @param array<string, mixed>|null $snapshot Constructor copy
	 * @param mixed $live Current $USER / $PLANET global
	 * @return array<string, mixed>
	 */
	public static function resolve(?array $snapshot, mixed $live): array
	{
		if (is_array($live)) {
			return $live;
		}

		if ($snapshot !== null) {
			return $snapshot;
		}

		throw new InvalidArgumentException('Game page player state is missing');
	}
}
