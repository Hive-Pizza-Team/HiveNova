<?php

use HiveNova\Core\FleetReturnPushService;
use HiveNova\Cronjob\FleetReturnPushCronjob;
use PHPUnit\Framework\TestCase;

class FleetReturnPushCronjobTest extends TestCase
{
	public function testRunDelegatesToService(): void
	{
		$service = $this->createMock(FleetReturnPushService::class);
		$service->expects($this->once())->method('run');

		(new FleetReturnPushCronjob($service))->run();
	}

	public function testRunWithoutServiceDoesNotThrowWhenUnconfigured(): void
	{
		(new FleetReturnPushCronjob())->run();
		$this->assertTrue(true);
	}

	public function testRunSwallowsServiceFailures(): void
	{
		$service = $this->createMock(FleetReturnPushService::class);
		$service->expects($this->once())
			->method('run')
			->willThrowException(new RuntimeException('boom'));

		(new FleetReturnPushCronjob($service))->run();
		$this->assertTrue(true);
	}
}
