<?php

use HiveNova\Core\Config;
use HiveNova\Core\SocialHiveMemoService;
use HiveNova\Cronjob\SocialHiveMemoCronjob;

use PHPUnit\Framework\TestCase;

class SocialHiveMemoCronjobTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Config::setInstance(new Config([
			'uni' => 1,
			'game_name' => 'HiveNova',
			'hive_inactive_memo_account' => '',
			'hive_inactive_memo_active_key' => '',
			'hive_inactive_memo_asset' => 'HIVE',
			'hive_inactive_memo_amount' => 0.003,
			'hive_social_memo_active' => 0,
			'hive_social_memo_memo_key' => '',
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
		$cron = new SocialHiveMemoCronjob();
		$cron->run();
		$this->assertTrue(true);
	}

	public function testServiceExceptionIsSwallowed(): void
	{
		$service = $this->createMock(SocialHiveMemoService::class);
		$service->expects($this->once())
			->method('run')
			->willThrowException(new RuntimeException('boom'));

		$cron = new SocialHiveMemoCronjob($service);
		$cron->run();
		$this->assertTrue(true);
	}
}
