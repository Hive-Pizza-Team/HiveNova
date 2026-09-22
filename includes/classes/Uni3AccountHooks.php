<?php

namespace HiveNova\Core;

/**
 * Registration side effects for the Uni3 claim-gate pilot.
 */
class Uni3AccountHooks
{
	public static function onPlayerCreated(int $universe, int $userId, string $hiveAccount): void
	{
		try {
			$config = Config::get($universe);
			if (!isset($config->season_mode) || (int) $config->season_mode !== 1) {
				return;
			}
			$seasonId = (int) ($config->season_id ?? 0);
			$hive = strtolower(trim($hiveAccount));
			if ($hive === '' || !HiveUtil::isAccountValid($hive)) {
				Uni3PilotLog::record(Uni3PilotLog::REGISTER_UNI3_EMAIL, $universe, $seasonId, $userId, '');
				return;
			}
			(new Uni3HiveLinkService(new DatabaseUni3ClaimStore()))->bind(
				$universe,
				$seasonId,
				$userId,
				$hive,
				Uni3ClaimGate::ORIGIN_KEYCHAIN,
				defined('TIMESTAMP') ? TIMESTAMP : time()
			);
		} catch (\Throwable $e) {
			return;
		}
	}
}
