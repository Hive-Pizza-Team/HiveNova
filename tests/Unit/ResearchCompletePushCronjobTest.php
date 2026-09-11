<?php

use HiveNova\Core\ResearchCompletePushService;
use HiveNova\Cronjob\ResearchCompletePushCronjob;
use PHPUnit\Framework\TestCase;

class ResearchCompletePushCronjobTest extends TestCase
{
	public function testRunDelegatesToService(): void
	{
		$service = $this->createMock(ResearchCompletePushService::class);
		$service->expects($this->once())->method('run');

		(new ResearchCompletePushCronjob($service))->run();
	}

	public function testRunWithoutServiceDoesNotThrowWhenUnconfigured(): void
	{
		(new ResearchCompletePushCronjob())->run();
		$this->assertTrue(true);
	}

	public function testRunSwallowsServiceFailures(): void
	{
		$service = $this->createMock(ResearchCompletePushService::class);
		$service->expects($this->once())
			->method('run')
			->willThrowException(new RuntimeException('boom'));

		(new ResearchCompletePushCronjob($service))->run();
		$this->assertTrue(true);
	}
}
