<?php

declare(strict_types=1);

use HiveNova\Core\TechTreeNudgeService;
use PHPUnit\Framework\TestCase;

class TechTreeNudgeServiceTest extends TestCase
{
	public function testShowsOnStarterPagesUntilSeen(): void
	{
		$this->assertTrue(TechTreeNudgeService::shouldShow([], 'buildings'));
		$this->assertTrue(TechTreeNudgeService::shouldShow([], 'research'));
		$this->assertTrue(TechTreeNudgeService::shouldShow([], 'shipyard'));
		$this->assertFalse(TechTreeNudgeService::shouldShow([], 'overview'));
		$this->assertFalse(TechTreeNudgeService::shouldShow([], 'techtree'));
		$this->assertFalse(TechTreeNudgeService::shouldShow([], 'fleetTable'));
	}

	public function testHidesAfterDismissOrVisitCookie(): void
	{
		$seen = [TechTreeNudgeService::COOKIE => TechTreeNudgeService::COOKIE_VALUE];

		$this->assertTrue(TechTreeNudgeService::isSeen($seen));
		$this->assertFalse(TechTreeNudgeService::shouldShow($seen, 'buildings'));
		$this->assertFalse(TechTreeNudgeService::shouldShow($seen, 'research'));
		$this->assertFalse(TechTreeNudgeService::shouldShow($seen, 'shipyard'));
		$this->assertFalse(TechTreeNudgeService::isSeen([]));
		$this->assertFalse(TechTreeNudgeService::isSeen([TechTreeNudgeService::COOKIE => '0']));
	}

	public function testCookieOptionsAreJsReadableAndLongLived(): void
	{
		$opts = TechTreeNudgeService::cookieOptions(1_700_000_000, true);

		$this->assertSame(1_700_000_000 + TechTreeNudgeService::TTL_SECONDS, $opts['expires']);
		$this->assertSame('/', $opts['path']);
		$this->assertTrue($opts['secure']);
		$this->assertFalse($opts['httponly']);
		$this->assertSame('Lax', $opts['samesite']);
		$this->assertSame('hn_techtree_seen', TechTreeNudgeService::COOKIE);
	}

	public function testSecureRequestFollowsHttpsConstant(): void
	{
		if (!defined('HTTPS')) {
			define('HTTPS', false);
		}
		$this->assertSame((bool) HTTPS, TechTreeNudgeService::isSecureRequest());
	}
}
