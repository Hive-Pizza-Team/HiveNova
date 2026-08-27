<?php

use HiveNova\Core\SocialHiveMemoAdminConfig;

use PHPUnit\Framework\TestCase;

class SocialHiveMemoAdminConfigTest extends TestCase
{
	public function testEmptyPostedMemoKeyIsOmittedFromApplySet(): void
	{
		$result = SocialHiveMemoAdminConfig::applyPosted(
			[
				'hive_social_memo_active' => 0,
				'hive_social_memo_memo_key' => '5KEXISTING',
			],
			[
				'hive_social_memo_active' => 'on',
				'hive_social_memo_memo_key' => '',
			]
		);

		$this->assertSame(1, $result['apply']['hive_social_memo_active']);
		$this->assertArrayNotHasKey('hive_social_memo_memo_key', $result['apply']);
	}

	public function testPostedMemoKeyReplacesStoredKey(): void
	{
		$GLOBALS['salt'] = './0123456789abcdefghij';
		$result = SocialHiveMemoAdminConfig::applyPosted(
			['hive_social_memo_memo_key' => '5KOLD'],
			['hive_social_memo_memo_key' => '5KNEW']
		);
		$stored = $result['apply']['hive_social_memo_memo_key'];
		$this->assertStringStartsWith('enc:v1:', $stored);
		$this->assertSame('5KNEW', \HiveNova\Core\ConfigSecret::reveal($stored));
	}

	public function testLogPayloadNeverContainsRawWif(): void
	{
		$result = SocialHiveMemoAdminConfig::applyPosted(
			['hive_social_memo_memo_key' => '5KSECRETOLD'],
			['hive_social_memo_memo_key' => '5KSECRETNEW']
		);
		$blob = json_encode([$result['log_old'], $result['log_new'], $result['template']]);
		$this->assertStringNotContainsString('5KSECRETOLD', (string) $blob);
		$this->assertStringNotContainsString('5KSECRETNEW', (string) $blob);
		$this->assertSame('', $result['template']['hive_social_memo_memo_key']);
	}
}
