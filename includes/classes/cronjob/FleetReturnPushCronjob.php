<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\FleetReturnPushService;
use Throwable;

class FleetReturnPushCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?FleetReturnPushService $service = null,
	) {
	}

	public function run()
	{
		try {
			($this->service ?? new FleetReturnPushService())->run();
		} catch (Throwable $e) {
			return;
		}
	}
}
