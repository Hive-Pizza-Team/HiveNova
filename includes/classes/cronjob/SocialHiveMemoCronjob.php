<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\Config;
use HiveNova\Core\SocialHiveMemoService;
use Throwable;

class SocialHiveMemoCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?SocialHiveMemoService $service = null,
	) {
	}

	public function run()
	{
		try {
			$config = Config::get(ROOT_UNI);
			$service = $this->service ?? new SocialHiveMemoService();
			$service->run($config);
		} catch (Throwable $e) {
			return;
		}
	}
}
