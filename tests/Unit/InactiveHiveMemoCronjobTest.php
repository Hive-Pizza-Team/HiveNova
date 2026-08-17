<?php

use HiveNova\Core\Config;
use HiveNova\Core\InactiveHiveMemoService;
use HiveNova\Cronjob\InactiveHiveMemoCronjob;

use PHPUnit\Framework\TestCase;

class InactiveHiveMemoCronjobTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Config::setInstance(new Config([
			'uni' => 1,
			'game_name' => 'HiveNova',
			'del_user_automatic' => 90,
			'hive_inactive_memo_active' => 0,
			'hive_inactive_memo_armed' => 0,
			'hive_inactive_memo_account' => '',
			'hive_inactive_memo_active_key' => '',
			'hive_inactive_memo_asset' => 'HIVE',
			'hive_inactive_memo_amount' => 0.003,
		]), 1);
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		parent::tearDown();
	}

	public function testDisabledConfigDoesNotThrow(): void
	{
		$cron = new InactiveHiveMemoCronjob();
		$cron->run();
		$this->assertTrue(true);
	}

	public function testServiceExceptionIsSwallowed(): void
	{
		$service = $this->createMock(InactiveHiveMemoService::class);
		$service->expects($this->once())
			->method('run')
			->willThrowException(new RuntimeException('boom'));

		$cron = new InactiveHiveMemoCronjob($service);
		$cron->run();
		$this->assertTrue(true);
	}
}
