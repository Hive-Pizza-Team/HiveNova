<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\ReferralCaptureService;
use PHPUnit\Framework\TestCase;

class ReferralCaptureServiceTest extends TestCase
{
	/** @var list<array{name: string, value: string, expire: int}> */
	private array $cookiesWritten = [];

	private function service(): ReferralCaptureService
	{
		$this->cookiesWritten = [];

		return new ReferralCaptureService(function (string $name, string $value, int $expire): void {
			$this->cookiesWritten[] = [
				'name'   => $name,
				'value'  => $value,
				'expire' => $expire,
			];
		});
	}

	private function dbWithReferrer(?array $row): DatabaseInterface
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturn($row ?? []);

		return $db;
	}

	public function testRegisterUrlWithoutReferral(): void
	{
		$this->assertSame('index.php?page=register', $this->service()->registerUrl());
		$this->assertSame('index.php?page=register', $this->service()->registerUrl(0, 3));
	}

	public function testRegisterUrlIncludesReferralAndUniverse(): void
	{
		$this->assertSame(
			'index.php?page=register&referralID=42&uni=3',
			$this->service()->registerUrl(42, 3)
		);
	}

	public function testRegisterUrlReferralOnlyOmitsUni(): void
	{
		$this->assertSame(
			'index.php?page=register&referralID=42',
			$this->service()->registerUrl(42)
		);
	}

	public function testCaptureInactiveReturnsEmpty(): void
	{
		$service = $this->service();
		$result = $service->capture(
			$this->dbWithReferrer(['id' => 5, 'username' => 'Alice', 'universe' => 1]),
			false,
			['ref' => 5],
			[]
		);

		$this->assertSame(['id' => 0, 'name' => '', 'universe' => 0], $result);
		$this->assertSame([], $this->cookiesWritten);
	}

	public function testCaptureFromQueryPersistsCookies(): void
	{
		$service = $this->service();
		$result = $service->capture(
			$this->dbWithReferrer(['id' => 12, 'username' => 'Bob', 'universe' => 2]),
			true,
			['ref' => 12],
			[]
		);

		$this->assertSame([
			'id'       => 12,
			'name'     => 'Bob',
			'universe' => 2,
		], $result);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame(ReferralCaptureService::COOKIE_REF, $this->cookiesWritten[0]['name']);
		$this->assertSame('12', $this->cookiesWritten[0]['value']);
		$this->assertSame(ReferralCaptureService::COOKIE_REF_UNI, $this->cookiesWritten[1]['name']);
		$this->assertSame('2', $this->cookiesWritten[1]['value']);
		$this->assertSame(
			TIMESTAMP + ReferralCaptureService::COOKIE_TTL_SECONDS,
			$this->cookiesWritten[0]['expire']
		);
	}

	public function testCaptureFromReferralIdQueryPersistsCookies(): void
	{
		$result = $this->service()->capture(
			$this->dbWithReferrer(['id' => 12, 'username' => 'Bob', 'universe' => 2]),
			true,
			['referralID' => 12],
			[]
		);

		$this->assertSame(12, $result['id']);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('12', $this->cookiesWritten[0]['value']);
	}

	public function testCapturePrefersRefOverReferralId(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('WHERE id = :referralID'),
				[':referralID' => 7]
			)
			->willReturn(['id' => 7, 'username' => 'Carol', 'universe' => 1]);

		$result = $this->service()->capture(
			$db,
			true,
			['ref' => 7, 'referralID' => 99],
			[]
		);

		$this->assertSame(7, $result['id']);
	}

	public function testCaptureQueryOverwritesStaleCookie(): void
	{
		$result = $this->service()->capture(
			$this->dbWithReferrer(['id' => 7, 'username' => 'Carol', 'universe' => 1]),
			true,
			['ref' => 7],
			[
				ReferralCaptureService::COOKIE_REF     => '99',
				ReferralCaptureService::COOKIE_REF_UNI => '9',
			]
		);

		$this->assertSame(7, $result['id']);
		$this->assertSame('7', $this->cookiesWritten[0]['value']);
		$this->assertSame('1', $this->cookiesWritten[1]['value']);
	}

	public function testCaptureFallsBackToCookie(): void
	{
		$result = $this->service()->capture(
			$this->dbWithReferrer(['id' => 9, 'username' => 'Dana', 'universe' => 1]),
			true,
			[],
			[ReferralCaptureService::COOKIE_REF => '9']
		);

		$this->assertSame(9, $result['id']);
		$this->assertSame('Dana', $result['name']);
	}

	public function testCaptureCookieUsesRefUniScope(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 9, ':universe' => 3]
			)
			->willReturn(['id' => 9, 'username' => 'Dana', 'universe' => 3]);

		$result = $this->service()->capture(
			$db,
			true,
			[],
			[
				ReferralCaptureService::COOKIE_REF     => '9',
				ReferralCaptureService::COOKIE_REF_UNI => '3',
			]
		);

		$this->assertSame(9, $result['id']);
		$this->assertSame(3, $result['universe']);
	}

	public function testCaptureInvalidUserClearsCookies(): void
	{
		$result = $this->service()->capture(
			$this->dbWithReferrer(null),
			true,
			['ref' => 404],
			[
				ReferralCaptureService::COOKIE_REF     => '9',
				ReferralCaptureService::COOKIE_REF_UNI => '1',
			]
		);

		$this->assertSame(['id' => 0, 'name' => '', 'universe' => 0], $result);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('', $this->cookiesWritten[0]['value']);
		$this->assertSame('', $this->cookiesWritten[1]['value']);
		$this->assertSame(TIMESTAMP - 3600, $this->cookiesWritten[0]['expire']);
	}

	public function testResolveForRegisterUsesReferralIdThenCookie(): void
	{
		$result = $this->service()->resolveForRegister(
			$this->dbWithReferrer(['id' => 3, 'username' => 'Eve', 'universe' => 1]),
			true,
			['referralID' => 3],
			[ReferralCaptureService::COOKIE_REF => '99']
		);

		$this->assertSame(3, $result['id']);
		$this->assertSame('Eve', $result['name']);
		$this->assertCount(2, $this->cookiesWritten);
	}

	public function testResolveForRegisterCookieFallbackPersists(): void
	{
		$result = $this->service()->resolveForRegister(
			$this->dbWithReferrer(['id' => 8, 'username' => 'Frank', 'universe' => 2]),
			true,
			[],
			[ReferralCaptureService::COOKIE_REF => '8']
		);

		$this->assertSame(8, $result['id']);
		$this->assertSame(2, $result['universe']);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('8', $this->cookiesWritten[0]['value']);
		$this->assertSame('2', $this->cookiesWritten[1]['value']);
	}

	public function testResolveForRegisterFiltersByUniverseMismatch(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 4, ':universe' => 2]
			)
			->willReturn([]);

		$result = $this->service()->resolveForRegister(
			$db,
			true,
			['referralID' => 4],
			[],
			2
		);

		$this->assertSame(0, $result['id']);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('', $this->cookiesWritten[0]['value']);
	}

	public function testResolveForRegisterMatchesUniverseSuccess(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 4, ':universe' => 2]
			)
			->willReturn(['id' => 4, 'username' => 'Gina', 'universe' => 2]);

		$result = $this->service()->resolveForRegister(
			$db,
			true,
			['referralID' => 4],
			[],
			2
		);

		$this->assertSame([
			'id'       => 4,
			'name'     => 'Gina',
			'universe' => 2,
		], $result);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('4', $this->cookiesWritten[0]['value']);
		$this->assertSame('2', $this->cookiesWritten[1]['value']);
	}

	public function testResolveForRegisterInactive(): void
	{
		$result = $this->service()->resolveForRegister(
			$this->dbWithReferrer(['id' => 1, 'username' => 'X', 'universe' => 1]),
			false,
			['referralID' => 1],
			[]
		);

		$this->assertSame(0, $result['id']);
	}

	public function testRegistrationUniverseIdWhenOpen(): void
	{
		$config = (object) ['game_disable' => 1, 'reg_closed' => 0];
		$this->assertSame(
			2,
			$this->service()->registrationUniverseId(['id' => 5, 'universe' => 2], $config)
		);
	}

	public function testRegistrationUniverseIdWhenClosed(): void
	{
		$config = (object) ['game_disable' => 1, 'reg_closed' => 1];
		$this->assertSame(
			0,
			$this->service()->registrationUniverseId(['id' => 5, 'universe' => 2], $config)
		);
	}

	public function testRegistrationUniverseIdWhenEmptyReferral(): void
	{
		$config = (object) ['game_disable' => 1, 'reg_closed' => 0];
		$this->assertSame(0, $this->service()->registrationUniverseId(['id' => 0, 'universe' => 2], $config));
	}

	public function testIsUniverseOpenForRegistration(): void
	{
		$this->assertTrue(ReferralCaptureService::isUniverseOpenForRegistration(
			(object) ['game_disable' => 1, 'reg_closed' => 0]
		));
		$this->assertFalse(ReferralCaptureService::isUniverseOpenForRegistration(
			(object) ['game_disable' => 0, 'reg_closed' => 0]
		));
	}
}
