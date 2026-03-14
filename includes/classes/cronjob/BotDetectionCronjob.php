<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\CronjobTask;

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */


class BotDetectionCronjob implements CronjobTask
{
	const SLEEP_THRESHOLD = 7200; // 2 hours in seconds
	const DAYS_WINDOW     = 7;
	const MIN_ACTIONS     = 10;

	function run()
	{
		$unis = Universe::availableUniverses();

		foreach ($unis as $uni)
		{
			$cutoff = TIMESTAMP - (self::DAYS_WINDOW * 24 * 60 * 60);

			$sql = 'SELECT user_id, username, event_time FROM (
				SELECT fl.fleet_owner AS user_id, u.username, fl.fleet_start_time AS event_time
				FROM %%LOG_FLEETS%% fl
				JOIN %%USERS%% u ON u.id = fl.fleet_owner
				WHERE fl.fleet_universe = :universe
				  AND fl.fleet_start_time >= :cutoff
				  AND u.bana = 0
				  AND u.urlaubs_modus = 0
				UNION ALL
				SELECT lb.owner_id AS user_id, u.username, lb.queued_at AS event_time
				FROM %%LOG_BUILDINGS%% lb
				JOIN %%USERS%% u ON u.id = lb.owner_id
				WHERE lb.universe = :universe
				  AND lb.queued_at >= :cutoff
				  AND u.bana = 0
				  AND u.urlaubs_modus = 0
				UNION ALL
				SELECT lr.owner_id AS user_id, u.username, lr.queued_at AS event_time
				FROM %%LOG_RESEARCH%% lr
				JOIN %%USERS%% u ON u.id = lr.owner_id
				WHERE lr.universe = :universe
				  AND lr.queued_at >= :cutoff
				  AND u.bana = 0
				  AND u.urlaubs_modus = 0
			) events
			ORDER BY user_id ASC, event_time ASC;';

			$rows = Database::get()->select($sql, array(
				':universe' => $uni,
				':cutoff'   => $cutoff,
			));

			if (empty($rows))
			{
				continue;
			}

			// Group events by user and compute max gap between consecutive events
			$byUser = array();
			foreach ($rows as $row)
			{
				$uid = (int) $row['user_id'];
				if (!isset($byUser[$uid]))
				{
					$byUser[$uid] = array(
						'username' => $row['username'],
						'times'    => array(),
					);
				}
				$byUser[$uid]['times'][] = (int) $row['event_time'];
			}

			$suspects = array();
			foreach ($byUser as $uid => $data)
			{
				$times = $data['times'];
				$count = count($times);
				if ($count < self::MIN_ACTIONS)
				{
					continue;
				}

				$maxGap = 0;
				for ($i = 1; $i < $count; $i++)
				{
					$gap = $times[$i] - $times[$i - 1];
					if ($gap > $maxGap)
					{
						$maxGap = $gap;
					}
				}

				if ($maxGap < self::SLEEP_THRESHOLD)
				{
					$hours   = floor($maxGap / 3600);
					$minutes = floor(($maxGap % 3600) / 60);
					$suspects[] = sprintf(
						'- %s (longest break: %dh %02dm)',
						$data['username'],
						$hours,
						$minutes
					);
				}
			}

			if (empty($suspects))
			{
				continue;
			}

			$text = "The following players have shown no natural sleep break in the last "
				. self::DAYS_WINDOW . " days:\n"
				. implode("\n", $suspects) . "\n"
				. "These accounts may be using automated scripts. Please review.";

			$adminSql = 'SELECT id FROM %%USERS%% WHERE universe = :universe AND authlevel >= :authlevel;';
			$admins   = Database::get()->select($adminSql, array(
				':universe'  => $uni,
				':authlevel' => AUTH_ADM,
			));

			foreach ($admins as $admin)
			{
				PlayerUtil::sendMessage(
					$admin['id'],
					0,
					'Game Master',
					4,
					'Bot Detection Report',
					$text,
					TIMESTAMP,
					NULL,
					1,
					$uni
				);
			}
		}
	}
}
