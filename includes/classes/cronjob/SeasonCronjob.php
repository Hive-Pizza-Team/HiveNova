<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\DatabaseSeasonStore;
use HiveNova\Core\SeasonService;
use HiveNova\Cronjob\CronjobTask;
use Throwable;

class SeasonCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?SeasonService $service = null,
	) {
	}

	public function run()
	{
		try {
			$service = $this->service ?? new SeasonService(new DatabaseSeasonStore());
			$service->tick();
		} catch (Throwable $e) {
			return;
		}
	}
}
