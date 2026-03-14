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


class PushingDetectionCronjob implements CronjobTask
{
	function run()
	{
		$unis = Universe::availableUniverses();

		foreach ($unis as $uni)
		{
			$cutoff = TIMESTAMP - (14 * 24 * 60 * 60);

			$sql = 'SELECT destination, dest_id, COUNT(DISTINCT source) AS source_count
			FROM (
				SELECT u1.username AS source, u2.username AS destination, u2.id AS dest_id,
				       COUNT(DISTINCT fl.fleet_id) AS fleet_count
				FROM %%LOG_FLEETS%% fl
				JOIN %%PLANETS%% p ON fl.fleet_end_id = p.id
				JOIN %%USERS%% u1 ON u1.id = fl.fleet_owner
				JOIN %%USERS%% u2 ON u2.id = p.id_owner
				JOIN %%STATPOINTS%% sp1 ON sp1.id_owner = u1.id AND sp1.universe = :universe
				JOIN %%STATPOINTS%% sp2 ON sp2.id_owner = u2.id AND sp2.universe = :universe
				WHERE fl.fleet_mission = 3
				  AND fl.fleet_owner != p.id_owner
				  AND sp1.total_points < sp2.total_points
				  AND fl.start_time > :cutoff
				  AND p.universe = :universe
				GROUP BY u1.id, u2.id, u1.username, u2.username
				HAVING COUNT(DISTINCT fl.fleet_id) > 5
			) pushers
			GROUP BY destination, dest_id
			ORDER BY source_count DESC, destination DESC;';

			$pushers = Database::get()->select($sql, array(
				':universe' => $uni,
				':cutoff'   => $cutoff,
			));

			if (empty($pushers))
			{
				continue;
			}

			$lines = array();
			foreach ($pushers as $row)
			{
				$count = (int) $row['source_count'];
				$name  = $row['destination'];
				if ($count === 1)
				{
					$lines[] = '- 1 player is pushing to ' . $name;
				}
				else
				{
					$lines[] = '- ' . $count . ' player(s) are pushing to ' . $name;
				}
			}

			$text = "Suspicious attack patterns have been detected in this universe:\n"
				. implode("\n", $lines) . "\n";

			$playerSql = 'SELECT id FROM %%USERS%% WHERE universe = :universe;';
			$players   = Database::get()->select($playerSql, array(
				':universe'  => $uni,
			));

			foreach ($players as $player)
			{
				PlayerUtil::sendMessage(
					$player['id'],
					0,
					'Game Master',
					4,
					'Pushing Warning',
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
