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
}
