<?php

use HiveNova\Core\PushingAccusationQuery;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PushingAccusationQueryTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testAccusedReceiversAreDestIdsNotSenders(): void
    {
        $this->fake->accusedDestIds = [42, 7];
        $this->assertSame([42, 7], PushingAccusationQuery::accusedReceiverIds(1, TIMESTAMP));
        $this->assertTrue(PushingAccusationQuery::isAccusedReceiver(42, 1, TIMESTAMP));
        $this->assertFalse(PushingAccusationQuery::isAccusedReceiver(99, 1, TIMESTAMP));
    }
}
