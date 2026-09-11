<?php

use HiveNova\Core\MessageInboxService;
use PHPUnit\Framework\TestCase;

class MessageInboxServiceTest extends TestCase
{
	public function testValidCategoriesAreKept(): void
	{
		$this->assertTrue(MessageInboxService::isValidCategory(1));
		$this->assertTrue(MessageInboxService::isValidCategory(100));
		$this->assertTrue(MessageInboxService::isValidCategory(999));
		$this->assertFalse(MessageInboxService::isValidCategory(-1));
		$this->assertFalse(MessageInboxService::isValidCategory(42));

		$this->assertSame(1, MessageInboxService::resolveCategory(1, [1 => 0]));
		$this->assertSame(100, MessageInboxService::resolveCategory(100));
		$this->assertSame(0, MessageInboxService::resolveCategory(0, [1 => 3]));
	}

	public function testDefaultOpensFirstUnreadInboxType(): void
	{
		$this->assertSame(
			1,
			MessageInboxService::resolveCategory(-1, [
				0 => 0,
				1 => 2,
				3 => 1,
			])
		);
	}

	public function testDefaultWithoutUnreadOpensAllMessages(): void
	{
		$this->assertSame(100, MessageInboxService::resolveCategory(-1, []));
		$this->assertSame(100, MessageInboxService::resolveCategory(-1, [
			0 => 0,
			1 => 0,
			100 => 4,
		]));
	}
}
