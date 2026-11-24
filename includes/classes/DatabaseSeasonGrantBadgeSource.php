<?php

namespace HiveNova\Core;

/**
 * season_sbt_grants → nameplate badges. Soft-empty when the table is absent.
 */
class DatabaseSeasonGrantBadgeSource implements SeasonGrantBadgeSource
{
	public function __construct(
		private readonly SeasonSbtGrantStore $store = new DatabaseSeasonSbtGrantStore(),
		/** @var array<string, string> */
		private readonly array $copy = [],
	) {
	}

	public function badgesForUser(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}

		return SeasonSbtBadgeMapper::badgesFromGrants($this->store->listForUser($userId), $this->copy);
	}
}
