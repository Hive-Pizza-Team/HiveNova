<?php

declare(strict_types=1);

use HiveNova\Core\RegisterUsernameCheckLimiter;
use PHPUnit\Framework\TestCase;

class RegisterUsernameCheckLimiterTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = sys_get_temp_dir().'/hivenova-reg-rl-'.bin2hex(random_bytes(4)).'/';
	}

	protected function tearDown(): void
	{
		foreach (glob($this->dir.'*.json') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dir);
		parent::tearDown();
	}

	public function test_allows_up_to_max_then_denies_with_retry_after(): void
	{
		$limiter = new RegisterUsernameCheckLimiter($this->dir, 3, 60);

		$this->assertTrue($limiter->consume('10.0.0.1', null, 1000)['allowed']);
		$this->assertTrue($limiter->consume('10.0.0.1', null, 1010)['allowed']);
		$this->assertTrue($limiter->consume('10.0.0.1', null, 1020)['allowed']);

		$denied = $limiter->consume('10.0.0.1', null, 1030);
		$this->assertFalse($denied['allowed']);
		$this->assertSame(30, $denied['retryAfter']);
	}

	public function test_counts_per_ip_independently(): void
	{
		$limiter = new RegisterUsernameCheckLimiter($this->dir, 1, 60);

		$this->assertTrue($limiter->consume('10.0.0.1', null, 1000)['allowed']);
		$this->assertFalse($limiter->consume('10.0.0.1', null, 1001)['allowed']);
		$this->assertTrue($limiter->consume('10.0.0.2', null, 1001)['allowed']);
	}

	public function test_session_bucket_denies_across_ips(): void
	{
		$limiter = new RegisterUsernameCheckLimiter($this->dir, 1, 60);

		$this->assertTrue($limiter->consume('10.0.0.1', 'sess-a', 1000)['allowed']);
		$denied = $limiter->consume('10.0.0.2', 'sess-a', 1001);
		$this->assertFalse($denied['allowed']);
		$this->assertTrue($limiter->consume('10.0.0.3', 'sess-b', 1001)['allowed']);
	}

	public function test_window_reset_allows_again(): void
	{
		$limiter = new RegisterUsernameCheckLimiter($this->dir, 1, 60);

		$this->assertTrue($limiter->consume('10.0.0.1', null, 1000)['allowed']);
		$this->assertFalse($limiter->consume('10.0.0.1', null, 1059)['allowed']);
		$this->assertTrue($limiter->consume('10.0.0.1', null, 1060)['allowed']);
	}

	public function test_empty_ip_fails_closed_without_writing(): void
	{
		$limiter = new RegisterUsernameCheckLimiter($this->dir, 45, 60);
		$denied = $limiter->consume('  ', null, 1000);

		$this->assertFalse($denied['allowed']);
		$this->assertSame(60, $denied['retryAfter']);
		$this->assertFalse(is_dir($this->dir));
	}

	public function test_unwritable_cache_path_fails_closed(): void
	{
		$asFile = sys_get_temp_dir().'/hivenova-reg-rl-file-'.bin2hex(random_bytes(4));
		file_put_contents($asFile, 'nope');

		try {
			$limiter = new RegisterUsernameCheckLimiter($asFile, 45, 60);
			$denied = $limiter->consume('10.0.0.1', null, 1000);
			$this->assertFalse($denied['allowed']);
		} finally {
			@unlink($asFile);
		}
	}

	public function test_empty_cache_dir_fails_closed(): void
	{
		$limiter = new RegisterUsernameCheckLimiter('', 45, 60);
		$denied = $limiter->consume('10.0.0.1', null, 1000);
		$this->assertFalse($denied['allowed']);
	}

	public function test_corrupt_counter_file_is_treated_as_fresh_window(): void
	{
		mkdir($this->dir, 0755, true);
		$limiter = new RegisterUsernameCheckLimiter($this->dir, 1, 60);
		$path = $this->dir.hash('sha256', 'ip:10.0.0.1').'.json';
		file_put_contents($path, 'not-json');

		$this->assertTrue($limiter->consume('10.0.0.1', null, 2000)['allowed']);
		$this->assertFalse($limiter->consume('10.0.0.1', null, 2001)['allowed']);
	}

	public function test_default_cache_dir_is_under_cache_path(): void
	{
		$this->assertStringContainsString('reg-username-rl', RegisterUsernameCheckLimiter::defaultCacheDir());
	}

	public function test_documented_limit_matches_debounce_budget(): void
	{
		$this->assertSame(45, RegisterUsernameCheckLimiter::MAX_REQUESTS);
		$this->assertSame(60, RegisterUsernameCheckLimiter::WINDOW_SECONDS);
	}
}
