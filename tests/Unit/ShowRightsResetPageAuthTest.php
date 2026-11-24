<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Rights/Reset must deny via AdminPageSidAuth (message + redirect), never bare exit.
 */
final class ShowRightsResetPageAuthTest extends TestCase
{
	public function testRightsPageUsesSidAuthGate(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/adm/ShowRightsPage.php');
		$this->assertStringContainsString('AdminPageSidAuth::canAccessAllowed', $src);
		$this->assertStringContainsString('AdminPageSidAuth::deny()', $src);
		$this->assertStringContainsString("HTTP::_GP('sid', '')", $src);
		$this->assertDoesNotMatchRegularExpression(
			'/allowedTo\([^)]+\)\s*\|\|\s*\(\$_GET\[\'sid\'\][^)]*\)\s*!=\s*session_id\(\)\s*\)\s*exit/',
			$src
		);
	}

	public function testResetPageUsesSidAuthGate(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/adm/ShowResetPage.php');
		$this->assertStringContainsString('AdminPageSidAuth::canAccessRoot', $src);
		$this->assertStringContainsString('AdminPageSidAuth::deny()', $src);
		$this->assertStringContainsString("HTTP::_GP('sid', '')", $src);
		$this->assertDoesNotMatchRegularExpression(
			'/\$USER\[\'id\'\]\s*!=\s*ROOT_USER\s*\|\|\s*\(\$_GET\[\'sid\'\][^)]*\)\s*!=\s*session_id\(\)\s*\)\s*exit/',
			$src
		);
	}

	public function testCronjobAndAchievementsThrowOnPermissionFailure(): void
	{
		$cronjob = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/adm/ShowCronjobPage.php');
		$achievements = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/adm/ShowAchievementsPage.php');

		$this->assertStringContainsString('throw new Exception("Permission error!")', $cronjob);
		$this->assertStringNotContainsString('))) exit;', $cronjob);

		$this->assertStringContainsString('throw new Exception("Permission error!")', $achievements);
		$this->assertDoesNotMatchRegularExpression(
			'/allowedTo\([^)]+\)\)\s*\{\s*exit;/',
			$achievements
		);
	}
}
