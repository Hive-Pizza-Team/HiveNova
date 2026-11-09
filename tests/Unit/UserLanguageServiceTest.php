<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\UserLanguageService;
use PHPUnit\Framework\TestCase;

class UserLanguageServiceTest extends TestCase
{
	/** @var list<string> */
	private array $allowed = ['en', 'de', 'fr', 'pl'];

	public function testResolvePrefersRequestOverCookie(): void
	{
		$this->assertSame(
			'de',
			UserLanguageService::resolveFromRequest(
				['lang' => 'de'],
				['lang' => 'fr'],
				true,
				$this->allowed
			)
		);
	}

	public function testResolveFallsBackToCookieWhenRequested(): void
	{
		$this->assertSame(
			'fr',
			UserLanguageService::resolveFromRequest(
				[],
				['lang' => 'fr'],
				true,
				$this->allowed
			)
		);
	}

	public function testResolveIgnoresCookieWhenDisabled(): void
	{
		$this->assertNull(
			UserLanguageService::resolveFromRequest(
				[],
				['lang' => 'de'],
				false,
				$this->allowed
			)
		);
	}

	public function testResolveRejectsDisallowedCodes(): void
	{
		$this->assertNull(
			UserLanguageService::resolveFromRequest(
				['lang' => 'xx'],
				['lang' => 'yy'],
				true,
				$this->allowed
			)
		);
	}

	public function testResolveForRegisterUsesCandidateThenLobbyThenDefault(): void
	{
		$this->assertSame(
			'de',
			UserLanguageService::resolveForRegister('de', 'en', [], [], $this->allowed)
		);
		$this->assertSame(
			'fr',
			UserLanguageService::resolveForRegister('', 'en', [], ['lang' => 'fr'], $this->allowed)
		);
		$this->assertSame(
			'en',
			UserLanguageService::resolveForRegister('xx', 'en', [], [], $this->allowed)
		);
	}

	public function testApplyToUserSkipsWhenUnchanged(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('update');

		$this->assertFalse(UserLanguageService::applyToUser(42, 'de', 'de', $db));
	}

	public function testApplyToUserPersistsWhenChanged(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('update')
			->with(
				$this->stringContains('SET lang = :lang'),
				[
					':lang'   => 'de',
					':userId' => 42,
				]
			);

		$this->assertTrue(UserLanguageService::applyToUser(42, 'de', 'en', $db));
	}

	public function testSyncFromLobbyOnLoginCopiesCookieLanguage(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())->method('update');

		$this->assertSame(
			'de',
			UserLanguageService::syncFromLobbyOnLogin(
				7,
				'en',
				$db,
				[],
				['lang' => 'de']
			)
		);
	}

	public function testSyncFromLobbyOnLoginNoopsWithoutLobbyLanguage(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('update');

		$this->assertNull(
			UserLanguageService::syncFromLobbyOnLogin(7, 'en', $db, [], [])
		);
	}

	public function testApplyExplicitRequestUpdatesLoadedUser(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())->method('update');

		$user = ['id' => 9, 'lang' => 'en'];
		$this->assertTrue(
			UserLanguageService::applyExplicitRequestToUser(
				$user,
				$db,
				['lang' => 'de']
			)
		);
		$this->assertSame('de', $user['lang']);
	}

	public function testApplyExplicitRequestIgnoresCookie(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('update');

		$savedCookie = $_COOKIE;
		$_COOKIE['lang'] = 'de';
		try {
			$user = ['id' => 9, 'lang' => 'en'];
			$this->assertFalse(
				UserLanguageService::applyExplicitRequestToUser($user, $db, [])
			);
			$this->assertSame('en', $user['lang']);
		} finally {
			$_COOKIE = $savedCookie;
		}
	}
}
