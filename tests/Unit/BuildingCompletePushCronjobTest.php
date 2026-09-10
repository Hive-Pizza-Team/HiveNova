<?php

use HiveNova\Core\BuildingCompletePushService;
use HiveNova\Cronjob\BuildingCompletePushCronjob;
use PHPUnit\Framework\TestCase;

class BuildingCompletePushCronjobTest extends TestCase
{
	public function testRunDelegatesToService(): void
	{
		$service = $this->createMock(BuildingCompletePushService::class);
		$service->expects($this->once())->method('run');

		(new BuildingCompletePushCronjob($service))->run();
	}

	public function testRunWithoutServiceDoesNotThrowWhenUnconfigured(): void
	{
		(new BuildingCompletePushCronjob())->run();
		$this->assertTrue(true);
	}

	public function testRunSwallowsServiceFailures(): void
	{
		$service = $this->createMock(BuildingCompletePushService::class);
		$service->expects($this->once())
			->method('run')
			->willThrowException(new RuntimeException('boom'));

		(new BuildingCompletePushCronjob($service))->run();
		$this->assertTrue(true);
	}
}
