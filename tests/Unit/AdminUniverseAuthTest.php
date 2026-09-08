<?php

declare(strict_types=1);

use HiveNova\Core\AdminUniverseAuth;
use PHPUnit\Framework\TestCase;

final class AdminUniverseAuthTest extends TestCase
{
	public function testOnlyAdmCanView(): void
	{
		$this->assertTrue(AdminUniverseAuth::canView(AUTH_ADM));
		$this->assertFalse(AdminUniverseAuth::canView(AUTH_OPS));
		$this->assertFalse(AdminUniverseAuth::canView(AUTH_MOD));
		$this->assertFalse(AdminUniverseAuth::canView(AUTH_PROMO));
		$this->assertFalse(AdminUniverseAuth::canView(AUTH_USR));
	}

	public function testAdmCanMutateWithMatchingSid(): void
	{
		$this->assertTrue(AdminUniverseAuth::canMutate(AUTH_ADM, 'sess-token', 'sess-token'));
	}

	public function testAdmCannotMutateWithWrongOrEmptySid(): void
	{
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_ADM, '', 'sess-token'));
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_ADM, 'other', 'sess-token'));
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_ADM, 'sess-token', ''));
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_ADM, '', ''));
	}

	public function testNonAdmCannotMutateEvenWithMatchingSid(): void
	{
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_OPS, 'sess-token', 'sess-token'));
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_MOD, 'sess-token', 'sess-token'));
		$this->assertFalse(AdminUniverseAuth::canMutate(AUTH_USR, 'sess-token', 'sess-token'));
	}
}
