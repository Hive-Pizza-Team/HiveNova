<?php

require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\MessageInboxService;
use PHPUnit\Framework\TestCase;

class MessageInboxServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

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

	public function testBuildCategoryStatsLabelsUnreadAndTotals(): void
	{
		$stats = MessageInboxService::buildCategoryStats([
			['message_type' => 1, 'message_unread' => 1, 'count' => 1],
			['message_type' => 3, 'message_unread' => 2, 'count' => 5],
		], 4);

		$this->assertSame(1, $stats['unread'][1]);
		$this->assertSame(1, $stats['total'][1]);
		$this->assertSame(2, $stats['unread'][3]);
		$this->assertSame(5, $stats['total'][3]);
		$this->assertSame(3, $stats['unread'][100]);
		$this->assertSame(6, $stats['total'][100]);
		$this->assertSame(4, $stats['total'][999]);
		$this->assertSame(0, $stats['unread'][999]);
		$this->assertSame('#FF6699', $stats['categories'][1]['color']);
		$this->assertSame(1, $stats['categories'][1]['unread']);
	}

	public function testBuildCategoryStatsIgnoresUnknownMessageTypes(): void
	{
		$stats = MessageInboxService::buildCategoryStats([
			['message_type' => 42, 'message_unread' => 9, 'count' => 9],
			['message_type' => 1, 'message_unread' => 1, 'count' => 2],
		]);

		$this->assertSame(1, $stats['unread'][100]);
		$this->assertSame(2, $stats['total'][100]);
		$this->assertArrayNotHasKey(42, $stats['categories']);
	}

	public function testFindUserIdByUsername(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('selectSingle')->willReturnCallback(
			static function ($qry, array $params = []) {
				if (($params[':username'] ?? '') === 'Commander') {
					return ['id' => 17];
				}
				return null;
			}
		);
		$this->swapDatabaseInstance($db);

		$this->assertSame(17, MessageInboxService::findUserIdByUsername('Commander', 1));
		$this->assertNull(MessageInboxService::findUserIdByUsername('  ', 1));
		$this->assertNull(MessageInboxService::findUserIdByUsername('Commander', 0));
		$this->assertNull(MessageInboxService::findUserIdByUsername('Missing', 1));
	}
}
