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

	public function test_email_register_allowed_when_hive_chain_has_name_but_game_does_not(): void
	{
		$this->assertFalse(RegisterValidation::blocksEmailRegisterForOnChainHiveName(true));
		$this->assertSame(
			[],
			RegisterValidation::uniquenessErrorKeys(0, 0, 0, '', true)
		);
	}

	public function test_in_game_username_collision_still_blocks(): void
	{
		$this->assertSame(
			['registerErrorUsernameExist'],
			RegisterValidation::uniquenessErrorKeys(1, 0, 0, '', false)
		);
	}

	public function test_in_game_mail_collision_still_blocks(): void
	{
		$this->assertSame(
			['registerErrorMailExist'],
			RegisterValidation::uniquenessErrorKeys(0, 1, 0, '', false)
		);
	}

	public function test_hive_keychain_duplicate_account_still_blocks(): void
	{
		$this->assertSame(
			['registerErrorHiveAccountExist'],
			RegisterValidation::uniquenessErrorKeys(0, 0, 1, 'alice', false)
		);
	}

	public function test_empty_hive_account_does_not_treat_blank_hive_rows_as_taken(): void
	{
		$this->assertSame(
			[],
			RegisterValidation::uniquenessErrorKeys(0, 0, 5, '', false)
		);
	}

	public function test_on_chain_hive_name_does_not_stack_with_in_game_uniqueness(): void
	{
		$this->assertSame(
			['registerErrorUsernameExist', 'registerErrorMailExist'],
			RegisterValidation::uniquenessErrorKeys(2, 1, 0, '', true)
		);
	}
}
