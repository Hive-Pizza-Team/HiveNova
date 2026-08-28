<?php

declare(strict_types=1);

use HiveNova\Core\EmailRegistrationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class EmailRegistrationServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		parent::setUp();

		if (!defined('PROTOCOL')) {
			define('PROTOCOL', 'http://');
		}
		if (!defined('HTTP_HOST')) {
			define('HTTP_HOST', '127.0.0.1');
		}
		if (!defined('HTTP_BASE')) {
			define('HTTP_BASE', '/');
		}

		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_build_verify_url_embeds_universe(): void
	{
		$url = EmailRegistrationService::buildVerifyUrl(3, 42, 'abcKEY');
		$this->assertStringContainsString('/uni3/index.php?', $url);
		$this->assertStringContainsString('page=vertify', $url);
		$this->assertStringContainsString('i=42', $url);
		$this->assertStringContainsString('k=abcKEY', $url);
		$this->assertStringContainsString('uni=3', $url);
	}

	public function test_build_verify_url_clamps_universe_to_at_least_one(): void
	{
		$url = EmailRegistrationService::buildVerifyUrl(0, 1, 'k');
		$this->assertStringContainsString('/uni1/index.php?', $url);
		$this->assertStringContainsString('uni=1', $url);
	}

	public function test_find_pending_validation_ignores_current_universe(): void
	{
		$this->fake->achievement->usersValidRows = [[
			'validationID' => 7,
			'validationKey' => 'secret',
			'universe' => 3,
			'userName' => 'Commander',
		]];

		$found = EmailRegistrationService::findPendingValidation(7, 'secret');
		$this->assertIsArray($found);
		$this->assertSame(3, (int) $found['universe']);

		$this->assertFalse(EmailRegistrationService::findPendingValidation(7, 'wrong'));
		$this->assertFalse(EmailRegistrationService::findPendingValidation(0, 'secret'));
		$this->assertFalse(EmailRegistrationService::findPendingValidation(7, ''));
	}
}
