<?php

use HiveNova\Core\SeasonService;
use HiveNova\Cronjob\SeasonCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/InMemorySeasonStore.php';

class SeasonCronjobTest extends TestCase
{
	public function testRunInvokesServiceTick(): void
	{
		$store = new InMemorySeasonStore();
		$service = $this->getMockBuilder(SeasonService::class)
			->setConstructorArgs([$store])
			->onlyMethods(['tick'])
			->getMock();
		$service->expects($this->once())->method('tick');

		(new SeasonCronjob($service))->run();
	}

	public function testRunSwallowsServiceExceptions(): void
	{
		$store = new InMemorySeasonStore();
		$service = $this->getMockBuilder(SeasonService::class)
			->setConstructorArgs([$store])
			->onlyMethods(['tick'])
			->getMock();
		$service->method('tick')->willThrowException(new RuntimeException('nope'));
		(new SeasonCronjob($service))->run();
		$this->assertTrue(true);
	}
}
