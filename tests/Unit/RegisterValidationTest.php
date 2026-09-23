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

	public function test_empty_username_reports_only_empty_key(): void
	{
		$this->assertSame('registerErrorUsernameEmpty', RegisterValidation::usernameErrorKey(''));
	}

	public function test_short_or_long_username_reports_length_key(): void
	{
		$this->assertSame('registerErrorUsernameLength', RegisterValidation::usernameErrorKey('ab'));
		$this->assertSame('registerErrorUsernameLength', RegisterValidation::usernameErrorKey(str_repeat('n', 26)));
	}

	public function test_invalid_username_chars_report_char_key(): void
	{
		$this->assertSame('registerErrorUsernameChar', RegisterValidation::usernameErrorKey('bad@name'));
	}

	public function test_valid_username_has_no_error_key(): void
	{
		$this->assertNull(RegisterValidation::usernameErrorKey('Commander'));
	}
}
