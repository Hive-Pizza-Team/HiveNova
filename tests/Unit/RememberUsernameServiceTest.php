<?php

declare(strict_types=1);

use HiveNova\Core\RememberUsernameService;
use PHPUnit\Framework\TestCase;

class RememberUsernameServiceTest extends TestCase
{
	/** @var list<array{name: string, value: string, expire: int}> */
	private array $cookiesWritten = [];

	private function service(): RememberUsernameService
	{
		$this->cookiesWritten = [];

		return new RememberUsernameService(
			function (string $name, string $value, int $expire): void {
				$this->cookiesWritten[] = [
					'name'   => $name,
					'value'  => $value,
					'expire' => $expire,
				];
			}
		);
	}

	public function testNormalizeUsernameTrimsAndValidates(): void
	{
		$this->assertSame('Commander', RememberUsernameService::normalizeUsername('email', '  Commander  '));
		$this->assertSame('alice', RememberUsernameService::normalizeUsername('hive', ' Alice '));
		$this->assertSame('', RememberUsernameService::normalizeUsername('email', '   '));
		$this->assertSame('', RememberUsernameService::normalizeUsername('nope', 'Commander'));
		$this->assertSame('', RememberUsernameService::normalizeUsername('email', "bad\nname"));
	}

	public function testKindFromLoginRequest(): void
	{
		$this->assertSame('email', RememberUsernameService::kindFromLoginRequest(['username' => 'Commander']));
		$this->assertSame('hive', RememberUsernameService::kindFromLoginRequest([
			'username'    => 'alice',
			'hiveAccount' => 'alice',
		]));
		$this->assertSame('email', RememberUsernameService::kindFromLoginRequest([
			'username'    => 'Commander',
			'hiveAccount' => '   ',
		]));
	}

	public function testReadAllFromCookies(): void
	{
		$cookies = [
			RememberUsernameService::COOKIE_EMAIL => '  Commander ',
			RememberUsernameService::COOKIE_HIVE  => ' Alice ',
		];
		$this->assertSame(
			['email' => 'Commander', 'hive' => 'alice'],
			RememberUsernameService::readAll($cookies)
		);
		$this->assertSame('', RememberUsernameService::read([], 'email'));
	}

	public function testPersistWritesCookieAndRejectsInvalid(): void
	{
		$service = $this->service();
		$now = 1_700_000_000;

		$this->assertTrue($service->persist('email', '  Commander  ', $now));
		$this->assertFalse($service->persist('email', '   ', $now));
		$this->assertFalse($service->persist('nope', 'Commander', $now));
		$this->assertTrue($service->persist('hive', ' Bob ', $now));

		$this->assertSame(
			[
				[
					'name'   => RememberUsernameService::COOKIE_EMAIL,
					'value'  => 'Commander',
					'expire' => $now + RememberUsernameService::TTL_SECONDS,
				],
				[
					'name'   => RememberUsernameService::COOKIE_HIVE,
					'value'  => 'bob',
					'expire' => $now + RememberUsernameService::TTL_SECONDS,
				],
			],
			$this->cookiesWritten
		);
	}

	public function testClipRespectsMaxLength(): void
	{
		$long = str_repeat('a', RememberUsernameService::MAX_LENGTH + 5);
		$clipped = RememberUsernameService::normalizeUsername('email', $long);
		$this->assertSame(RememberUsernameService::MAX_LENGTH, strlen($clipped));
		$this->assertSame(str_repeat('a', RememberUsernameService::MAX_LENGTH), $clipped);
	}
}
