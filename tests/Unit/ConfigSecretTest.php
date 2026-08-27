<?php

use HiveNova\Core\ConfigSecret;
use PHPUnit\Framework\TestCase;

class ConfigSecretTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$GLOBALS['salt'] = './0123456789abcdefghij';
		putenv('APP_KEY');
		putenv('HIVE_INACTIVE_MEMO_ACTIVE_KEY');
	}

	protected function tearDown(): void
	{
		putenv('APP_KEY');
		putenv('HIVE_INACTIVE_MEMO_ACTIVE_KEY');
		putenv('HIVE_SOCIAL_MEMO_MEMO_KEY');
		putenv('SEASON_WALLET_ACTIVE_KEY');
		putenv('SEASON_BLOG_POSTING_KEY');
		unset($GLOBALS['salt']);
		parent::tearDown();
	}

	public function testSealAndRevealRoundTrip(): void
	{
		$sealed = ConfigSecret::seal('5KTESTWIFSECRET');
		$this->assertTrue(ConfigSecret::isEncrypted($sealed));
		$this->assertStringStartsWith(ConfigSecret::PREFIX, $sealed);
		$this->assertSame('5KTESTWIFSECRET', ConfigSecret::reveal($sealed));
	}

	public function testTamperedCiphertextThrows(): void
	{
		$sealed = ConfigSecret::seal('5KTESTWIFSECRET');
		$tampered = substr($sealed, 0, -4) . 'XXXX';
		$this->expectException(RuntimeException::class);
		ConfigSecret::reveal($tampered);
	}

	public function testResolvePrefersEnvOverDb(): void
	{
		putenv('HIVE_INACTIVE_MEMO_ACTIVE_KEY=5KENVKEY');
		$sealed = ConfigSecret::seal('5KDBKEY');
		$this->assertSame(
			'5KENVKEY',
			ConfigSecret::resolve(ConfigSecret::ENV_INACTIVE_ACTIVE_KEY, $sealed)
		);
	}

	public function testIsPresentChecksEnvAndDb(): void
	{
		$this->assertFalse(ConfigSecret::isPresent(ConfigSecret::ENV_INACTIVE_ACTIVE_KEY, ''));
		$this->assertTrue(ConfigSecret::isPresent(ConfigSecret::ENV_INACTIVE_ACTIVE_KEY, 'enc:v1:abc'));
		putenv('HIVE_INACTIVE_MEMO_ACTIVE_KEY=5KENV');
		$this->assertTrue(ConfigSecret::isPresent(ConfigSecret::ENV_INACTIVE_ACTIVE_KEY, ''));
	}

	public function testEmptySealStaysEmpty(): void
	{
		$this->assertSame('', ConfigSecret::seal(''));
	}
}
