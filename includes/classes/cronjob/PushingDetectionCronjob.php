<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\PushingAccusationQuery;
use HiveNova\Core\PushingRules;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\CronjobTask;

/**
 * Weekly pushing review (cron row 'pushing': Sunday 00:00 server time).
 *
 * Sends the flagged receivers, their sources, counts and value moved to game
 * moderators, operators and admins only (authlevel >= AUTH_MOD) so they can review them.
 * Players are never mailed: an automatic public accusation could be wrong.
 */
class PushingDetectionCronjob implements CronjobTask
{
	function run()
	{
		foreach (Universe::availableUniverses() as $uni) {
			$uni = (int) $uni;
			$report = PushingAccusationQuery::report($uni, TIMESTAMP);
			if ($report === array()) {
				continue;
			}

			$recipients = self::reviewerIds($uni);
			if ($recipients === array()) {
				continue;
			}

			$text = PushingRules::reportText($report, TIMESTAMP - PushingRules::WINDOW_SECONDS, TIMESTAMP);
			foreach ($recipients as $userId) {
				PlayerUtil::sendMessage(
					$userId,
					0,
					'Game Master',
					4,
					'Pushing review',
					$text,
					TIMESTAMP,
					NULL,
					1,
					$uni
				);
			}
		}
	}

	/**
	 * Same recipient pattern as BotDetectionService::adminRecipientIds, widened to operators.
	 *
	 * @return list<int>
	 */
	public static function reviewerIds(int $universe): array
	{
		$rows = Database::get()->select(
			'SELECT id FROM %%USERS%% WHERE universe = :universe AND authlevel >= :authlevel;',
			array(
				':universe'  => $universe,
				':authlevel' => AUTH_MOD,
			)
		);
		return array_map(static fn(array $row): int => (int) $row['id'], $rows);
	}
}
