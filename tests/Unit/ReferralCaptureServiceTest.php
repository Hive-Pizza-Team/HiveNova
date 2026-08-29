<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\ReferralCaptureService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

class ReferralCaptureServiceTest extends TestCase
{
	/** @var list<array{name: string, value: string, expire: int}> */
	private array $cookiesWritten = [];

	/** @var array<int, bool> */
	private array $refActiveByUni = [];

	private function service(?array $refActiveByUni = null): ReferralCaptureService
	{
		$this->cookiesWritten = [];
		$this->refActiveByUni = $refActiveByUni ?? [];

		return new ReferralCaptureService(
			function (string $name, string $value, int $expire): void {
				$this->cookiesWritten[] = [
					'name'   => $name,
					'value'  => $value,
					'expire' => $expire,
				];
			},
			function (int $universeId): bool {
				if ($this->refActiveByUni === []) {
					return true;
				}

				return (bool) ($this->refActiveByUni[$universeId] ?? false);
			}
		);
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

	public function testIsAliasAndAliasUserId(): void
	{
		$this->assertTrue(ReferralCaptureService::isAlias(1));
		$this->assertFalse(ReferralCaptureService::isAlias(712));
		$this->assertSame(1, ReferralCaptureService::aliasUserId(1, 1));
		$this->assertSame(712, ReferralCaptureService::aliasUserId(1, 3));
		$this->assertSame(0, ReferralCaptureService::aliasUserId(1, 2));
		$this->assertSame(0, ReferralCaptureService::aliasUserId(712, 3));
	}

	public function testPublicCodeFromPrefersRefThenCookieThenReferralId(): void
	{
		$this->assertSame(1, ReferralCaptureService::publicCodeFrom(
			['ref' => 1, 'referralID' => 99],
			[ReferralCaptureService::COOKIE_REF => '7']
		));
		$this->assertSame(7, ReferralCaptureService::publicCodeFrom(
			['referralID' => 99],
			[ReferralCaptureService::COOKIE_REF => '7']
		));
		$this->assertSame(99, ReferralCaptureService::publicCodeFrom(
			['referralID' => 99],
			[]
		));
	}

	public function testCaptureInactiveOnReferrerUniverseReturnsEmpty(): void
	{
		$result = $this->service([2 => false])->capture(
			$this->dbWithReferrer(['id' => 5, 'username' => 'Alice', 'universe' => 2]),
			['ref' => 5],
			[]
		);

		$this->assertSame(['id' => 0, 'name' => '', 'universe' => 0, 'code' => 0], $result);
		$this->assertSame([], $this->cookiesWritten);
	}

	public function testCaptureIgnoresAmbientUniverseRefFlag(): void
	{
		// Referrer lives in uni 2 with refs on; ambient/current uni 1 may be off.
		$result = $this->service([1 => false, 2 => true])->capture(
			$this->dbWithReferrer(['id' => 712, 'username' => 'Referrer', 'universe' => 2]),
			['ref' => 712],
			[]
		);

		$this->assertSame(712, $result['id']);
		$this->assertSame(2, $result['universe']);
		$this->assertSame(712, $result['code']);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('712', $this->cookiesWritten[0]['value']);
	}

	public function testCaptureFromQueryPersistsCookies(): void
	{
		$result = $this->service([2 => true])->capture(
			$this->dbWithReferrer(['id' => 12, 'username' => 'Bob', 'universe' => 2]),
			['ref' => 12],
			[]
		);

		$this->assertSame([
			'id'       => 12,
			'name'     => 'Bob',
			'universe' => 2,
			'code'     => 12,
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
		$result = $this->service([2 => true])->capture(
			$this->dbWithReferrer(['id' => 12, 'username' => 'Bob', 'universe' => 2]),
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

		$result = $this->service([1 => true])->capture(
			$db,
			['ref' => 7, 'referralID' => 99],
			[]
		);

		$this->assertSame(7, $result['id']);
	}

	public function testCaptureQueryOverwritesStaleCookie(): void
	{
		$result = $this->service([1 => true])->capture(
			$this->dbWithReferrer(['id' => 7, 'username' => 'Carol', 'universe' => 1]),
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
		$result = $this->service([1 => true])->capture(
			$this->dbWithReferrer(['id' => 9, 'username' => 'Dana', 'universe' => 1]),
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

		$result = $this->service([3 => true])->capture(
			$db,
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
			['ref' => 404],
			[
				ReferralCaptureService::COOKIE_REF     => '9',
				ReferralCaptureService::COOKIE_REF_UNI => '1',
			]
		);

		$this->assertSame(['id' => 0, 'name' => '', 'universe' => 0, 'code' => 0], $result);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('', $this->cookiesWritten[0]['value']);
		$this->assertSame('', $this->cookiesWritten[1]['value']);
		$this->assertSame(TIMESTAMP - 3600, $this->cookiesWritten[0]['expire']);
	}

	public function testResolveForRegisterPrefersCookieOverReferralId(): void
	{
		$result = $this->service([1 => true])->resolveForRegister(
			$this->dbWithReferrer(['id' => 99, 'username' => 'Cookie', 'universe' => 1]),
			['referralID' => 3],
			[ReferralCaptureService::COOKIE_REF => '99']
		);

		$this->assertSame(99, $result['id']);
		$this->assertSame('Cookie', $result['name']);
		$this->assertSame(99, $result['code']);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('99', $this->cookiesWritten[0]['value']);
	}

	public function testResolveForRegisterCookieFallbackPersists(): void
	{
		$result = $this->service([2 => true])->resolveForRegister(
			$this->dbWithReferrer(['id' => 8, 'username' => 'Frank', 'universe' => 2]),
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

		$result = $this->service([2 => true])->resolveForRegister(
			$db,
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

		$result = $this->service([2 => true])->resolveForRegister(
			$db,
			['referralID' => 4],
			[],
			2
		);

		$this->assertSame([
			'id'       => 4,
			'name'     => 'Gina',
			'universe' => 2,
			'code'     => 4,
		], $result);
		$this->assertCount(2, $this->cookiesWritten);
		$this->assertSame('4', $this->cookiesWritten[0]['value']);
		$this->assertSame('2', $this->cookiesWritten[1]['value']);
	}

	public function testResolveForRegisterInactiveOnTargetUniverse(): void
	{
		$result = $this->service([2 => false])->resolveForRegister(
			$this->dbWithReferrer(['id' => 5, 'username' => 'X', 'universe' => 2]),
			['referralID' => 5],
			[],
			2
		);

		$this->assertSame(0, $result['id']);
		$this->assertSame([], $this->cookiesWritten);
	}

	public function testAliasCapturePersistsPublicCode(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 1, ':universe' => 1]
			)
			->willReturn(['id' => 1, 'username' => 'Alpha', 'universe' => 1]);

		$result = $this->service([1 => true, 3 => true])->capture(
			$db,
			['ref' => 1],
			[]
		);

		$this->assertSame(1, $result['id']);
		$this->assertSame(1, $result['code']);
		$this->assertSame('1', $this->cookiesWritten[0]['value']);
		$this->assertSame('1', $this->cookiesWritten[1]['value']);
	}

	public function testAliasResolveForRegisterPerUniverse(): void
	{
		$dbUni1 = $this->createMock(DatabaseInterface::class);
		$dbUni1->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 1, ':universe' => 1]
			)
			->willReturn(['id' => 1, 'username' => 'Alpha', 'universe' => 1]);

		$result1 = $this->service([1 => true, 3 => true])->resolveForRegister(
			$dbUni1,
			[],
			[ReferralCaptureService::COOKIE_REF => '1'],
			1
		);
		$this->assertSame(1, $result1['id']);
		$this->assertSame(1, $result1['code']);
		$this->assertSame('1', $this->cookiesWritten[0]['value']);

		$dbUni3 = $this->createMock(DatabaseInterface::class);
		$dbUni3->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 712, ':universe' => 3]
			)
			->willReturn(['id' => 712, 'username' => 'Gamma', 'universe' => 3]);

		$result3 = $this->service([1 => true, 3 => true])->resolveForRegister(
			$dbUni3,
			[],
			[ReferralCaptureService::COOKIE_REF => '1'],
			3
		);
		$this->assertSame(712, $result3['id']);
		$this->assertSame(1, $result3['code']);
		$this->assertSame('1', $this->cookiesWritten[0]['value']);
		$this->assertSame('3', $this->cookiesWritten[1]['value']);
	}

	public function testAliasResolveUnknownUniverseReturnsEmptyWithoutClearingCookie(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('selectSingle');

		$result = $this->service([2 => true])->resolveForRegister(
			$db,
			[],
			[
				ReferralCaptureService::COOKIE_REF     => '1',
				ReferralCaptureService::COOKIE_REF_UNI => '1',
			],
			2
		);

		$this->assertSame(0, $result['id']);
		$this->assertSame([], $this->cookiesWritten);
	}

	public function testAliasCookiePreferredOverResolvedReferralIdPost(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 712, ':universe' => 3]
			)
			->willReturn(['id' => 712, 'username' => 'Gamma', 'universe' => 3]);

		$result = $this->service([3 => true])->resolveForRegister(
			$db,
			['referralID' => 712],
			[ReferralCaptureService::COOKIE_REF => '1'],
			3
		);

		$this->assertSame(712, $result['id']);
		$this->assertSame(1, $result['code']);
		$this->assertSame('1', $this->cookiesWritten[0]['value']);
	}

	public function testNonAlias712ScopedToWrongUniverseReturnsEmpty(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->stringContains('AND universe = :universe'),
				[':referralID' => 712, ':universe' => 1]
			)
			->willReturn([]);

		$result = $this->service([1 => true])->resolveForRegister(
			$db,
			['ref' => 712],
			[],
			1
		);

		$this->assertSame(0, $result['id']);
	}

	public function testResolveByUniverseForAlias(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->exactly(2))
			->method('selectSingle')
			->willReturnCallback(function (string $sql, array $params) {
				$id = (int) $params[':referralID'];
				$uni = (int) $params[':universe'];
				if ($id === 1 && $uni === 1) {
					return ['id' => 1, 'username' => 'Alpha', 'universe' => 1];
				}
				if ($id === 712 && $uni === 3) {
					return ['id' => 712, 'username' => 'Gamma', 'universe' => 3];
				}

				return [];
			});

		$map = $this->service([1 => true, 3 => true])->resolveByUniverse($db, 1);

		$this->assertSame([
			1 => ['id' => 1, 'name' => 'Alpha'],
			3 => ['id' => 712, 'name' => 'Gamma'],
		], $map);
	}

	public function testResolveByUniverseOmitsInactiveUniverse(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('selectSingle')
			->with(
				$this->anything(),
				[':referralID' => 1, ':universe' => 1]
			)
			->willReturn(['id' => 1, 'username' => 'Alpha', 'universe' => 1]);

		$map = $this->service([1 => true, 3 => false])->resolveByUniverse($db, 1);

		$this->assertSame([
			1 => ['id' => 1, 'name' => 'Alpha'],
		], $map);
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

	public function testAnyUniverseHasReferralsActive(): void
	{
		$savedConfig = $this->getConfigCache();
		$savedUnis = $this->getUniverseList();
		try {
			$this->resetUniverseList([1, 2]);
			$this->clearConfigCache();
			Config::setInstance(new Config([
				'uni' => 1,
				'uni_name' => 'A',
				'ref_active' => 0,
				'game_disable' => 1,
				'reg_closed' => 0,
			]), 1);
			Config::setInstance(new Config([
				'uni' => 2,
				'uni_name' => 'B',
				'ref_active' => 1,
				'game_disable' => 1,
				'reg_closed' => 0,
			]), 2);

			$this->assertTrue(ReferralCaptureService::anyUniverseHasReferralsActive());

			Config::setInstance(new Config([
				'uni' => 2,
				'uni_name' => 'B',
				'ref_active' => 0,
				'game_disable' => 1,
				'reg_closed' => 0,
			]), 2);
			$this->assertFalse(ReferralCaptureService::anyUniverseHasReferralsActive());

			$this->resetUniverseList([]);
			$this->assertFalse(ReferralCaptureService::anyUniverseHasReferralsActive());
		} finally {
			$this->setConfigCache($savedConfig);
			$this->resetUniverseList($savedUnis);
		}
	}

	public function testDefaultClosuresUseConfigAndHttpCookie(): void
	{
		$savedConfig = $this->getConfigCache();
		try {
			$this->clearConfigCache();
			Config::setInstance(new Config([
				'uni' => 2,
				'uni_name' => 'Live',
				'ref_active' => 1,
				'game_disable' => 1,
				'reg_closed' => 0,
			]), 2);

			// No injectable stubs — exercise default Config/HTTP closures.
			$service = new ReferralCaptureService();
			$result = $service->capture(
				$this->dbWithReferrer(['id' => 712, 'username' => 'Referrer', 'universe' => 2]),
				['ref' => 712],
				[]
			);

			$this->assertSame(712, $result['id']);
			$this->assertSame(2, $result['universe']);
		} finally {
			$this->setConfigCache($savedConfig);
		}
	}

	public function testDefaultCheckerRejectsInactiveUniverse(): void
	{
		$savedConfig = $this->getConfigCache();
		try {
			$this->clearConfigCache();
			Config::setInstance(new Config([
				'uni' => 2,
				'uni_name' => 'Off',
				'ref_active' => 0,
				'game_disable' => 1,
				'reg_closed' => 0,
			]), 2);

			$service = new ReferralCaptureService();
			$result = $service->capture(
				$this->dbWithReferrer(['id' => 5, 'username' => 'Alice', 'universe' => 2]),
				['ref' => 5],
				[]
			);

			$this->assertSame(0, $result['id']);
		} finally {
			$this->setConfigCache($savedConfig);
		}
	}

	public function testCaptureRejectsReferrerWithZeroUniverse(): void
	{
		$result = $this->service()->capture(
			$this->dbWithReferrer(['id' => 5, 'username' => 'Broken', 'universe' => 0]),
			['ref' => 5],
			[]
		);

		$this->assertSame(0, $result['id']);
		$this->assertSame([], $this->cookiesWritten);
	}

	/**
	 * @return list<int>
	 */
	private function getUniverseList(): array
	{
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$value = $ref->getValue(null);

		return is_array($value) ? array_map('intval', $value) : [];
	}

	/**
	 * @param list<int> $ids
	 */
	private function resetUniverseList(array $ids): void
	{
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, $ids);

		$cur = new ReflectionProperty(Universe::class, 'currentUniverse');
		$cur->setAccessible(true);
		$cur->setValue(null, null);
	}

	/**
	 * @return array<int|string, Config>
	 */
	private function getConfigCache(): array
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$value = $ref->getValue(null);

		return is_array($value) ? $value : [];
	}

	private function clearConfigCache(): void
	{
		$this->setConfigCache([]);
	}

	/**
	 * @param array<int|string, Config> $cache
	 */
	private function setConfigCache(array $cache): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, $cache);
	}
}
