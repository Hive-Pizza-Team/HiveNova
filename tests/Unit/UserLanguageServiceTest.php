<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\UserLanguageService;
use PHPUnit\Framework\TestCase;

class UserLanguageServiceTest extends TestCase
{
	protected function tearDown(): void
	{
		unset($_REQUEST['lang'], $_COOKIE['lang']);
		parent::tearDown();
	}

	public function testResolvePrefersRequestOverCookie(): void
	{
		$this->assertSame(
			'de',
			UserLanguageService::resolveFromRequest(
				['lang' => 'de'],
				['lang' => 'fr'],
				true
			)
		);
	}

	public function testResolveFallsBackToCookieWhenEnabled(): void
	{
		$this->assertSame(
			'pl',
			UserLanguageService::resolveFromRequest([], ['lang' => 'pl'], true)
		);
		$this->assertNull(
			UserLanguageService::resolveFromRequest([], ['lang' => 'pl'], false)
		);
	}

	public function testResolveRejectsDisallowedCodes(): void
	{
		$this->assertNull(
			UserLanguageService::resolveFromRequest(['lang' => 'xx'], ['lang' => 'yy'], true)
		);
	}

	public function testApplyToUserPersistsAndSkipsUnchanged(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('update')
			->with(
				$this->stringContains('SET lang'),
				[':lang' => 'de', ':userId' => 42]
			);

		$this->assertTrue(UserLanguageService::applyToUser(42, 'de', 'en', $db));
		$this->assertFalse(UserLanguageService::applyToUser(42, 'de', 'de', $db));
		$this->assertFalse(UserLanguageService::applyToUser(42, 'xx', 'en', $db));
		$this->assertFalse(UserLanguageService::applyToUser(0, 'de', 'en', $db));
	}

	public function testSyncFromLobbyOnLoginUsesCookie(): void
	{
		$_COOKIE['lang'] = 'es';
		unset($_REQUEST['lang']);

		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('update')
			->with(
				$this->stringContains('SET lang'),
				[':lang' => 'es', ':userId' => 7]
			);

		$this->assertSame('es', UserLanguageService::syncFromLobbyOnLogin(7, 'en', $db));
	}

	public function testApplyExplicitRequestUpdatesUserArray(): void
	{
		$_REQUEST['lang'] = 'fr';
		$_COOKIE['lang'] = 'de';

		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('update')
			->with(
				$this->stringContains('SET lang'),
				[':lang' => 'fr', ':userId' => 9]
			);

		$user = ['id' => 9, 'lang' => 'en'];
		$this->assertTrue(UserLanguageService::applyExplicitRequestToUser($user, $db));
		$this->assertSame('fr', $user['lang']);
	}

	public function testApplyExplicitRequestIgnoresCookieOnly(): void
	{
		unset($_REQUEST['lang']);
		$_COOKIE['lang'] = 'de';

		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('update');

		$user = ['id' => 9, 'lang' => 'en'];
		$this->assertFalse(UserLanguageService::applyExplicitRequestToUser($user, $db));
		$this->assertSame('en', $user['lang']);
	}
}
