<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\BotDetectionService;
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
	/** @deprecated use BotDetectionService::SLEEP_THRESHOLD */
	public const SLEEP_THRESHOLD = BotDetectionService::SLEEP_THRESHOLD;

	/** @deprecated use BotDetectionService::DAYS_WINDOW */
	public const DAYS_WINDOW = BotDetectionService::DAYS_WINDOW;

	/** @deprecated use BotDetectionService::MIN_ACTIONS */
	public const MIN_ACTIONS = BotDetectionService::MIN_ACTIONS;

	function run()
	{
		$service = new BotDetectionService();
		$unis    = Universe::availableUniverses();

		foreach ($unis as $uni)
		{
			$suspects = $service->findSuspects($uni);
			if ($suspects === [])
			{
				continue;
			}

			$digestHash = BotDetectionService::computeDigestHash($suspects);
			if (!$service->shouldNotify($uni, $digestHash))
			{
				continue;
			}

			$text   = $service->buildReportText($suspects);
			$admins = $service->adminRecipientIds($uni);

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

			$service->markNotified($uni, $digestHash);
		}
	}
}
