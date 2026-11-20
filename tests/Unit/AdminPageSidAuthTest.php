<?php

declare(strict_types=1);

use HiveNova\Core\AdminPageSidAuth;
use PHPUnit\Framework\TestCase;

final class AdminPageSidAuthTest extends TestCase
{
	public function testSidMatchesRequiresNonEmptySessionAndEqualToken(): void
	{
		$this->assertTrue(AdminPageSidAuth::sidMatches('sess-token', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::sidMatches('', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::sidMatches('other', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::sidMatches('sess-token', ''));
		$this->assertFalse(AdminPageSidAuth::sidMatches('', ''));
	}

	public function testCanAccessAllowedRequiresPermissionAndMatchingSid(): void
	{
		$this->assertTrue(AdminPageSidAuth::canAccessAllowed(true, 'sess-token', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessAllowed(false, 'sess-token', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessAllowed(true, '', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessAllowed(true, 'other', 'sess-token'));
	}

	public function testCanAccessRootRequiresRootUserAndMatchingSid(): void
	{
		$this->assertTrue(AdminPageSidAuth::canAccessRoot(1, 1, 'sess-token', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessRoot(2, 1, 'sess-token', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessRoot(1, 1, '', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessRoot(1, 1, 'other', 'sess-token'));
		$this->assertFalse(AdminPageSidAuth::canAccessRoot(1, 1, 'sess-token', ''));
	}
}
