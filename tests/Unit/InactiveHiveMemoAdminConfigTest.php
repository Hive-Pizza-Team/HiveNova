<?php

use HiveNova\Core\InactiveHiveMemoAdminConfig;

use PHPUnit\Framework\TestCase;

class InactiveHiveMemoAdminConfigTest extends TestCase
{
	public function testEmptyPostedKeyIsOmittedFromApplySet(): void
	{
		$stored = [
			'hive_inactive_memo_active' => 1,
			'hive_inactive_memo_account' => 'gameacct',
			'hive_inactive_memo_active_key' => '5KEXISTING',
			'hive_inactive_memo_asset' => 'HIVE',
			'hive_inactive_memo_amount' => '0.003',
		];
		$result = InactiveHiveMemoAdminConfig::applyPosted($stored, [
			'hive_inactive_memo_active' => 'on',
			'hive_inactive_memo_account' => 'newacct',
			'hive_inactive_memo_active_key' => '',
			'hive_inactive_memo_asset' => 'HIVE',
			'hive_inactive_memo_amount' => '0.005',
		]);

		$this->assertArrayNotHasKey('hive_inactive_memo_active_key', $result['apply']);
		$this->assertSame('newacct', $result['apply']['hive_inactive_memo_account']);
		$this->assertSame('0.005', $result['apply']['hive_inactive_memo_amount']);
	}

	public function testPostedKeyReplacesStoredKey(): void
	{
		$GLOBALS['salt'] = './0123456789abcdefghij';
		$result = InactiveHiveMemoAdminConfig::applyPosted(
			['hive_inactive_memo_active_key' => '5KOLD'],
			['hive_inactive_memo_active_key' => '5KNEW']
		);
		$stored = $result['apply']['hive_inactive_memo_active_key'];
		$this->assertNotSame('5KNEW', $stored);
		$this->assertStringStartsWith('enc:v1:', $stored);
		$this->assertSame('5KNEW', \HiveNova\Core\ConfigSecret::reveal($stored));
	}

	public function testLogPayloadNeverContainsRawWif(): void
	{
		$result = InactiveHiveMemoAdminConfig::applyPosted(
			['hive_inactive_memo_active_key' => '5KSECRETOLD'],
			['hive_inactive_memo_active_key' => '5KSECRETNEW']
		);
		$blob = json_encode([$result['log_old'], $result['log_new'], $result['template']]);
		$this->assertStringNotContainsString('5KSECRETOLD', (string) $blob);
		$this->assertStringNotContainsString('5KSECRETNEW', (string) $blob);
		$this->assertSame('', $result['template']['hive_inactive_memo_active_key']);
	}

	public function testAmountBelowFloorIsNotApplied(): void
	{
		$result = InactiveHiveMemoAdminConfig::applyPosted(
			['hive_inactive_memo_amount' => '0.003'],
			['hive_inactive_memo_amount' => '0.001']
		);
		$this->assertArrayNotHasKey('hive_inactive_memo_amount', $result['apply']);
	}

	public function testInvalidAssetIsRejected(): void
	{
		$result = InactiveHiveMemoAdminConfig::applyPosted(
			['hive_inactive_memo_asset' => 'HIVE'],
			['hive_inactive_memo_asset' => 'STEEM']
		);
		$this->assertArrayNotHasKey('hive_inactive_memo_asset', $result['apply']);
	}
}
