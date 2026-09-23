<?php

namespace HiveNova\Core;

/**
 * Placeholder until season_sbt_grants exists. Does not invent winner or participant rows.
 */
class NullSeasonGrantBadgeSource implements SeasonGrantBadgeSource
{
	public function badgesForUser(int $userId): array
	{
		return [];
	}
}
