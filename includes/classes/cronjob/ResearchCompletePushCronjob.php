<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\ResearchCompletePushService;
use Throwable;

class ResearchCompletePushCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?ResearchCompletePushService $service = null,
	) {
	}

	public function run()
	{
		try {
			($this->service ?? new ResearchCompletePushService())->run();
		} catch (Throwable $e) {
			return;
		}
	}
}
