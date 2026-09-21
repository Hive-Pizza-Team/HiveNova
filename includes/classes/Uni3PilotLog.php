<?php

namespace HiveNova\Core;

/**
 * Minimal Uni3 claim-gate events. Failures are swallowed so logging never blocks play.
 */
class Uni3PilotLog
{
	public const REGISTER_UNI3_EMAIL = 'register_uni3_email';

	public const HIVE_LINK = 'hive_link';

	public const HIVE_LINK_REJECTED = 'hive_link_rejected';

	public const ENTRY_PAID = 'entry_paid';

	public const CLAIM_STARTED = 'claim_started';

	public const CLAIM_COMPLETED = 'claim_completed';

	public const CLAIM_FORFEITED = 'claim_forfeited';

	/** @var callable|null fn(string $event, int $universe, int $seasonId, int $userId, string $detail): void */
	private static $recorder = null;

	public static function setRecorder(?callable $recorder): void
	{
		self::$recorder = $recorder;
	}

	public static function record(string $event, int $universe, int $seasonId, int $userId, string $detail = ''): void
	{
		$detail = substr($detail, 0, 255);
		if (self::$recorder !== null) {
			try {
				(self::$recorder)($event, $universe, $seasonId, $userId, $detail);
			} catch (\Throwable $e) {
				return;
			}

			return;
		}

		try {
			$now = defined('TIMESTAMP') ? TIMESTAMP : time();
			Database::get()->insert(
				'INSERT INTO %%UNI3_PILOT_EVENTS%% SET
				`event` = :event, `universe` = :uni, `season_id` = :sid,
				`user_id` = :uid, `detail` = :detail, `created_at` = :ts',
				[
					':event'  => substr($event, 0, 32),
					':uni'    => $universe,
					':sid'    => max(0, $seasonId),
					':uid'    => max(0, $userId),
					':detail' => $detail,
					':ts'     => $now,
				]
			);
		} catch (\Throwable $e) {
			return;
		}
	}
}
