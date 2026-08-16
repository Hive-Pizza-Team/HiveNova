<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\PvePackageService;
use HiveNova\Core\PveRaidService;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\CronjobTask;

class PveSpawnCronjob implements CronjobTask
{
	public function run()
	{
		foreach (Universe::availableUniverses() as $universe) {
			PvePackageService::spawnTick((int) $universe);
			PveRaidService::run((int) $universe);
		}

		return true;
	}
}
