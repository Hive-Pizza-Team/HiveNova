<?php

namespace HiveNova\Core;

/**
 * Read-only source of season SBT / grant nameplate badges (#628 / #630 / #631).
 *
 * The grants table does not exist yet. Callers inject a reader when it does;
 * until then NullSeasonGrantBadgeSource is the hook and returns nothing.
 */
interface SeasonGrantBadgeSource
{
	/**
	 * @return list<array{id: string, kind?: string, label: string, tooltip?: string}>
	 */
	public function badgesForUser(int $userId): array;
}
