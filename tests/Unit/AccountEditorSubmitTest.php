<?php

use HiveNova\Core\AccountEditorSubmit;
use PHPUnit\Framework\TestCase;

class AccountEditorSubmitTest extends TestCase
{
	public function testAddOnlyDoesNotReadDeleteKey(): void
	{
		$post = ['add' => 'Add', 'id' => '1', 'metal' => '10'];

		$this->assertTrue(AccountEditorSubmit::isAdd($post));
		$this->assertFalse(AccountEditorSubmit::isDelete($post));
	}

	public function testDeleteOnlyDoesNotReadAddKey(): void
	{
		$post = ['delete' => 'Delete', 'id' => '1', 'metal' => '10'];

		$this->assertFalse(AccountEditorSubmit::isAdd($post));
		$this->assertTrue(AccountEditorSubmit::isDelete($post));
	}

	public function testNeitherSubmitIsFalse(): void
	{
		$post = ['id' => '1'];

		$this->assertFalse(AccountEditorSubmit::isAdd($post));
		$this->assertFalse(AccountEditorSubmit::isDelete($post));
	}

	public function testEmptyStringIsNotPressed(): void
	{
		$this->assertFalse(AccountEditorSubmit::isAdd(['add' => '']));
		$this->assertFalse(AccountEditorSubmit::isDelete(['delete' => '']));
	}

	public function testMissingSubmitKeysDoNotWarn(): void
	{
		$warnings = [];
		set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
			$warnings[] = $message;
			return true;
		});

		try {
			$this->assertFalse(AccountEditorSubmit::isAdd(['delete' => 'Delete']));
			$this->assertFalse(AccountEditorSubmit::isDelete(['add' => 'Add']));
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $warnings);
	}
}
