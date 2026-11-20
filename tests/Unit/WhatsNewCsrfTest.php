<?php

declare(strict_types=1);

use HiveNova\Core\WhatsNewCsrf;
use PHPUnit\Framework\TestCase;

class WhatsNewCsrfTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$_SESSION = [];
	}

	public function test_issue_stores_and_validate_accepts(): void
	{
		$token = WhatsNewCsrf::issueCsrfToken();
		$this->assertNotSame('', $token);
		$this->assertSame($token, $_SESSION[WhatsNewCsrf::SESSION_KEY]);
		$this->assertTrue(WhatsNewCsrf::validateCsrfToken($token));
	}

	public function test_validate_rejects_mismatch_and_empty(): void
	{
		WhatsNewCsrf::issueCsrfToken();
		$this->assertFalse(WhatsNewCsrf::validateCsrfToken('nope'));
		$this->assertFalse(WhatsNewCsrf::validateCsrfToken(''));
		$this->assertFalse(WhatsNewCsrf::validateCsrfToken(null));
	}

	public function test_validate_rejects_when_session_empty(): void
	{
		$this->assertFalse(WhatsNewCsrf::validateCsrfToken('anything'));
	}
}
