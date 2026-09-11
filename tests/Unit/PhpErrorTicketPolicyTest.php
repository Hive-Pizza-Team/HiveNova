<?php

declare(strict_types=1);

use HiveNova\Core\PhpErrorTicketPolicy;
use PHPUnit\Framework\TestCase;

class PhpErrorTicketPolicyTest extends TestCase
{
	private string $cacheFile;

	protected function setUp(): void
	{
		parent::setUp();
		$this->cacheFile = sys_get_temp_dir() . '/hivenova-php-error-tickets-' . uniqid('', true) . '.json';
	}

	protected function tearDown(): void
	{
		if (is_file($this->cacheFile)) {
			@unlink($this->cacheFile);
		}
		parent::tearDown();
	}

	public function testWarningsAndNoticesNeverOpenTickets(): void
	{
		foreach ([
			E_WARNING,
			E_NOTICE,
			E_USER_WARNING,
			E_USER_NOTICE,
			E_CORE_WARNING,
			E_COMPILE_WARNING,
			E_DEPRECATED,
			E_USER_DEPRECATED,
		] as $errno) {
			$this->assertTrue(PhpErrorTicketPolicy::isDiagnosticOnly($errno), (string) $errno);
			$this->assertFalse(PhpErrorTicketPolicy::shouldOpenSupportTicket($errno, 'INGAME'), (string) $errno);
			$this->assertFalse(PhpErrorTicketPolicy::shouldOpenSupportTicket($errno, 'ADMIN'), (string) $errno);
		}
	}

	public function testAdminModeNeverOpensTicketsEvenForFatals(): void
	{
		$this->assertFalse(PhpErrorTicketPolicy::shouldOpenSupportTicket(E_ERROR, 'ADMIN'));
		$this->assertFalse(PhpErrorTicketPolicy::shouldOpenSupportTicket(E_USER_ERROR, 'admin'));
		$this->assertFalse(PhpErrorTicketPolicy::shouldOpenSupportTicket(E_RECOVERABLE_ERROR, 'ADMIN'));
	}

	public function testPlayerFacingFatalsMayOpenTickets(): void
	{
		$this->assertFalse(PhpErrorTicketPolicy::isDiagnosticOnly(E_ERROR));
		$this->assertTrue(PhpErrorTicketPolicy::shouldOpenSupportTicket(E_ERROR, 'INGAME'));
		$this->assertTrue(PhpErrorTicketPolicy::shouldOpenSupportTicket(E_USER_ERROR, 'LOGIN'));
		$this->assertTrue(PhpErrorTicketPolicy::shouldOpenSupportTicket(E_RECOVERABLE_ERROR, 'CRON'));
	}

	public function testFingerprintIsStableAndDistinct(): void
	{
		$a = PhpErrorTicketPolicy::fingerprint(E_WARNING, '/app/foo.php', 54, 'Undefined array key "add"');
		$b = PhpErrorTicketPolicy::fingerprint(E_WARNING, '/app/foo.php', 54, 'Undefined array key "add"');
		$c = PhpErrorTicketPolicy::fingerprint(E_WARNING, '/app/foo.php', 55, 'Undefined array key "add"');

		$this->assertSame($a, $b);
		$this->assertNotSame($a, $c);
		$this->assertSame(64, strlen($a));
	}

	public function testClaimFingerprintDedupesWithinWindow(): void
	{
		$fp = PhpErrorTicketPolicy::fingerprint(E_ERROR, '/app/bar.php', 10, 'boom');
		$other = PhpErrorTicketPolicy::fingerprint(E_ERROR, '/app/baz.php', 11, 'other');
		$now = 1_700_000_000;

		$this->assertTrue(PhpErrorTicketPolicy::claimFingerprint($fp, $now, $this->cacheFile, 3600));
		$this->assertTrue(PhpErrorTicketPolicy::claimFingerprint($other, $now, $this->cacheFile, 3600));
		$this->assertFalse(PhpErrorTicketPolicy::claimFingerprint($fp, $now + 10, $this->cacheFile, 3600));
		$this->assertTrue(PhpErrorTicketPolicy::claimFingerprint($fp, $now + 3601, $this->cacheFile, 3600));
	}

	public function testClaimFingerprintRejectsEmptyInput(): void
	{
		$this->assertFalse(PhpErrorTicketPolicy::claimFingerprint('', 1, $this->cacheFile));
		$this->assertFalse(PhpErrorTicketPolicy::claimFingerprint('abc', 1, ''));
	}

	public function testClaimFingerprintIgnoresCorruptCacheAndJunkEntries(): void
	{
		file_put_contents($this->cacheFile, 'not-json');
		$fp = PhpErrorTicketPolicy::fingerprint(E_ERROR, '/app/corrupt.php', 1, 'x');
		$this->assertTrue(PhpErrorTicketPolicy::claimFingerprint($fp, 100, $this->cacheFile, 3600));

		file_put_contents($this->cacheFile, json_encode([
			0 => 100,
			'nested' => ['nope' => 1],
			'stale' => 1,
			$fp => 50,
		]));
		$this->assertTrue(PhpErrorTicketPolicy::claimFingerprint($fp, 100, $this->cacheFile, 10));
	}

	public function testClaimFingerprintCreatesMissingCacheDirectory(): void
	{
		$dir = sys_get_temp_dir() . '/hivenova-php-error-tickets-' . uniqid('', true);
		$file = $dir . '/nested/php-error-tickets.json';
		$fp = PhpErrorTicketPolicy::fingerprint(E_ERROR, '/app/dir.php', 2, 'y');

		try {
			$this->assertTrue(PhpErrorTicketPolicy::claimFingerprint($fp, 1, $file, 3600));
			$this->assertFileExists($file);
		} finally {
			@unlink($file);
			@rmdir($dir . '/nested');
			@rmdir($dir);
		}
	}
}
