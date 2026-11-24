<?php

declare(strict_types=1);

use HiveNova\Core\AdminReloginPassword;
use HiveNova\Core\HTTP;
use HiveNova\Core\PlayerUtil;
use PHPUnit\Framework\TestCase;

/**
 * Admin re-login must accept the same HTTP::_GP() normalization used when
 * passwords are set or checked in the game, and also accept a raw fallback.
 */
class AdminReloginPasswordTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$_REQUEST = [];
	}

	protected function tearDown(): void
	{
		$_REQUEST = [];
		parent::tearDown();
	}

	public function testMatchesWebHashedPasswordContainingAmpersandAndQuote(): void
	{
		$password = "a&b'c d";
		$_REQUEST['admin_pw'] = $password;
		$hash = PlayerUtil::cryptPassword(HTTP::_GP('admin_pw', '', true));

		$this->assertTrue(AdminReloginPassword::matches($hash));
	}

	public function testMatchesWebHashedPlainPassword(): void
	{
		$password = 'plainpass123';
		$_REQUEST['admin_pw'] = $password;
		$hash = PlayerUtil::cryptPassword(HTTP::_GP('admin_pw', '', true));

		$this->assertTrue(AdminReloginPassword::matches($hash));
	}

	public function testMatchesRawHashedPasswordContainingAmpersandAndQuote(): void
	{
		$password = "a&b'c d";
		$_REQUEST['admin_pw'] = $password;
		$hash = PlayerUtil::cryptPassword($password);

		$this->assertTrue(AdminReloginPassword::matches($hash));
	}

	public function testRejectsWrongPassword(): void
	{
		$_REQUEST['admin_pw'] = "a&b'c d";
		$hash = PlayerUtil::cryptPassword(HTTP::_GP('admin_pw', '', true));
		$_REQUEST['admin_pw'] = 'wrong-password';

		$this->assertFalse(AdminReloginPassword::matches($hash));
	}

	public function testRejectsEmptyPassword(): void
	{
		$_REQUEST['admin_pw'] = '';
		$hash = PlayerUtil::cryptPassword('plainpass123');

		$this->assertFalse(AdminReloginPassword::matches($hash));
	}

	public function testWebNormalizationDiffersFromRawForSpecialCharacters(): void
	{
		$_REQUEST['admin_pw'] = "a&b'c d";
		$normalized = HTTP::_GP('admin_pw', '', true);

		$this->assertSame("a&amp;b&#039;c d", $normalized);
		$this->assertNotSame($_REQUEST['admin_pw'], $normalized);
	}
}
