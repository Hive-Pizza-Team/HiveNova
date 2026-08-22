<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\DirectiveService;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\CronjobTask;
use Throwable;

class DirectivePeriodCronjob implements CronjobTask
{
	function run()
	{
		try {
			foreach (Universe::availableUniverses() as $universe) {
				DirectiveService::ensureCurrentPeriod((int) $universe);
				DirectiveService::notifyPeriodEndingIfNeeded((int) $universe);
			}
			ExpeditionChoiceService::autoResolveExpired();
		} catch (Throwable $e) {
			return;
		}
	}
}
