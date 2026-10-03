<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\BuildingCompletePushService;
use HiveNova\Core\PushNotificationService;
use Throwable;

class BuildingCompletePushCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?BuildingCompletePushService $service = null,
	) {
	}

	public function run()
	{
		try {
			($this->service ?? new BuildingCompletePushService())->run();
		} catch (Throwable $e) {
			PushNotificationService::logFailure('BuildingCompletePushCronjob: ' . $e->getMessage());
		}
	}
}
