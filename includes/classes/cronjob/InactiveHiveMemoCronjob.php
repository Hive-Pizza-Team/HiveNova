<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\Config;
use HiveNova\Core\InactiveHiveMemoService;
use HiveNova\Cronjob\CronjobTask;
use Throwable;

class InactiveHiveMemoCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?InactiveHiveMemoService $service = null,
	) {
	}

	public function run()
	{
		try {
			$config = Config::get(ROOT_UNI);
			$service = $this->service ?? new InactiveHiveMemoService();
			$service->run($config);
		} catch (Throwable $e) {
			return;
		}
	}
}
