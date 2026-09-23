<?php

namespace HiveNova\Mission;

/**
 * Combat-report page chrome: in-game menus for commanders, report-only for guests.
 */
class CombatReportChrome
{
	/**
	 * @param array<string, mixed> $user
	 */
	public static function showGameMenus(array $user): bool
	{
		return (int) ($user['id'] ?? 0) > 0;
	}
}
