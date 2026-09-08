<?php

declare(strict_types=1);

use HiveNova\Core\RegisterValidation;
use PHPUnit\Framework\TestCase;

class RegisterValidationTest extends TestCase
{
	public function test_empty_email_reports_only_empty_key(): void
	{
		$this->assertSame('registerErrorMailEmpty', RegisterValidation::mailErrorKey(''));
	}

	public function test_invalid_email_reports_only_invalid_key(): void
	{
		$this->assertSame('registerErrorMailInvalid', RegisterValidation::mailErrorKey('not-an-email'));
		$this->assertSame('registerErrorMailInvalid', RegisterValidation::mailErrorKey('user@'));
	}

	public function test_valid_email_has_no_error_key(): void
	{
		$this->assertNull(RegisterValidation::mailErrorKey('user@example.com'));
	}
}
