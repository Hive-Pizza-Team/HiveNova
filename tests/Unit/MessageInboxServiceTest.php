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

	public function testBuildCategoryStatsSumsOnlyInboxTypesAndCastsAggregates(): void
	{
		$stats = MessageInboxService::buildCategoryStats([
			['message_type' => '1', 'message_unread' => '1', 'count' => '1'],
			['message_type' => '3', 'message_unread' => null, 'count' => '2'],
			['message_type' => '100', 'message_unread' => '9', 'count' => '9'],
		], 4);

		$this->assertSame(1, $stats['unread'][1]);
		$this->assertSame(1, $stats['total'][1]);
		$this->assertSame(0, $stats['unread'][3]);
		$this->assertSame(2, $stats['total'][3]);
		$this->assertSame(1, $stats['unread'][100], 'All unread must not double-count bogus type 100 rows');
		$this->assertSame(3, $stats['total'][100]);
		$this->assertSame(4, $stats['total'][999]);
		$this->assertSame(0, $stats['unread'][999]);
	}
}
