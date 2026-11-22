<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\Database;
use HiveNova\Core\PushingAccusationQuery;
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
			$pushers = self::byDestination(PushingAccusationQuery::pushPairs((int) $uni, TIMESTAMP));

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

	/**
	 * Group flagged pairs by receiver: one line per destination with how many
	 * players push to it (same output as the old SQL GROUP BY destination).
	 *
	 * @param list<array{dest_id: int, dest_name: string, source_id: int}> $pairs
	 * @return list<array{destination: string, dest_id: int, source_count: int}>
	 */
	public static function byDestination(array $pairs): array
	{
		$byDest = array();
		foreach ($pairs as $pair) {
			$id = (int) $pair['dest_id'];
			if (!isset($byDest[$id])) {
				$byDest[$id] = array('destination' => (string) $pair['dest_name'], 'dest_id' => $id, 'sources' => array());
			}
			$byDest[$id]['sources'][(int) $pair['source_id']] = true;
		}
		$rows = array();
		foreach ($byDest as $row) {
			$rows[] = array('destination' => $row['destination'], 'dest_id' => $row['dest_id'], 'source_count' => count($row['sources']));
		}
		usort($rows, static function (array $a, array $b): int {
			$cmp = $b['source_count'] <=> $a['source_count'];
			return $cmp !== 0 ? $cmp : strcmp($b['destination'], $a['destination']);
		});
		return $rows;
	}
}
