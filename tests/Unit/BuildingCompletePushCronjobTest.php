<?php

use HiveNova\Core\BuildingCompletePushService;
use HiveNova\Core\PushNotificationService;
use HiveNova\Cronjob\BuildingCompletePushCronjob;
use PHPUnit\Framework\TestCase;

class BuildingCompletePushCronjobTest extends TestCase
{
	protected function tearDown(): void
	{
		PushNotificationService::setErrorLogger(null);
		parent::tearDown();
	}
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
		$logs = [];
		PushNotificationService::setErrorLogger(static function (string $line) use (&$logs): void {
			$logs[] = $line;
		});
		$service = $this->createMock(BuildingCompletePushService::class);
		$service->expects($this->once())
			->method('run')
			->willThrowException(new RuntimeException('boom'));

		(new BuildingCompletePushCronjob($service))->run();
		$this->assertSame(['BuildingCompletePushCronjob: boom'], $logs);
	}
}
