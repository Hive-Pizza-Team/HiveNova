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

	public function testDenyBuildsPermissionMessageBeforeRender(): void
	{
		$GLOBALS['LNG'] = ['404_page' => 'Permission error!', 'fcm_info' => ''];
		$GLOBALS['THEME'] = null;

		try {
			AdminPageSidAuth::deny();
			self::fail('deny should not return');
		} catch (\Throwable $e) {
			$this->assertNotSame('', $e->getMessage());
		}
	}

	public function testDenyFallsBackWhenLanguageKeyMissing(): void
	{
		$GLOBALS['LNG'] = ['fcm_info' => ''];
		$GLOBALS['THEME'] = null;

		try {
			AdminPageSidAuth::deny();
			self::fail('deny should not return');
		} catch (\Throwable $e) {
			$this->assertNotSame('', $e->getMessage());
		}
	}
}
