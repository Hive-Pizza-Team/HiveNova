<?php

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

use HiveNova\Core\Database;
use HiveNova\Core\Universe;


if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowBotDetectionPage()
{
	$sleepThreshold = 7200;
	$daysWindow     = 7;
	$minActions     = 10;

	$unis   = Universe::availableUniverses();
	$cutoff = TIMESTAMP - ($daysWindow * 24 * 60 * 60);

	$suspects = array();

	foreach ($unis as $uni)
	{
		$sql = 'SELECT user_id, username, event_time, source FROM (
			SELECT fl.fleet_owner AS user_id, u.username, fl.fleet_start_time AS event_time, \'fleet\' AS source
			FROM %%LOG_FLEETS%% fl
			JOIN %%USERS%% u ON u.id = fl.fleet_owner
			WHERE fl.fleet_universe = :universe
			  AND fl.fleet_start_time >= :cutoff
			  AND u.bana = 0
			  AND u.urlaubs_modus = 0
			UNION ALL
			SELECT lb.owner_id AS user_id, u.username, lb.queued_at AS event_time, \'building\' AS source
			FROM %%LOG_BUILDINGS%% lb
			JOIN %%USERS%% u ON u.id = lb.owner_id
			WHERE lb.universe = :universe
			  AND lb.queued_at >= :cutoff
			  AND u.bana = 0
			  AND u.urlaubs_modus = 0
			UNION ALL
			SELECT lr.owner_id AS user_id, u.username, lr.queued_at AS event_time, \'research\' AS source
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

		$byUser = array();
		foreach ($rows as $row)
		{
			$uid = (int) $row['user_id'];
			if (!isset($byUser[$uid]))
			{
				$byUser[$uid] = array(
					'username'       => $row['username'],
					'universe'       => $uni,
					'fleet_count'    => 0,
					'building_count' => 0,
					'research_count' => 0,
					'times'          => array(),
				);
			}
			$byUser[$uid]['times'][] = (int) $row['event_time'];
			if ($row['source'] === 'fleet')    $byUser[$uid]['fleet_count']++;
			if ($row['source'] === 'building') $byUser[$uid]['building_count']++;
			if ($row['source'] === 'research') $byUser[$uid]['research_count']++;
		}

		foreach ($byUser as $uid => $data)
		{
			$times = $data['times'];
			$count = count($times);
			if ($count < $minActions)
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

			if ($maxGap < $sleepThreshold)
			{
				$hours   = floor($maxGap / 3600);
				$minutes = floor(($maxGap % 3600) / 60);
				$suspects[] = array(
					'id'              => $uid,
					'username'        => $data['username'],
					'universe'        => $uni,
					'total_actions'   => $count,
					'fleet_count'     => $data['fleet_count'],
					'building_count'  => $data['building_count'],
					'research_count'  => $data['research_count'],
					'max_gap_seconds' => $maxGap,
					'max_gap_human'   => sprintf('%dh %02dm', $hours, $minutes),
				);
			}
		}
	}

	// Sort by max_gap_seconds ascending (worst offenders first)
	usort($suspects, function($a, $b) {
		return $a['max_gap_seconds'] - $b['max_gap_seconds'];
	});

	$template = new template();
	$template->assign_vars(array(
		'suspects' => $suspects,
	));
	$template->show('BotDetection.tpl');
}
