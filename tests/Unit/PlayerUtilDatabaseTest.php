<?php

use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PlayerUtilDatabaseTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testIsPositionFreeWhenNoPlanetAtCoords(): void
    {
        $this->fake->planetPositionCount = 0;
        $this->assertTrue(PlayerUtil::isPositionFree(1, 2, 100, 5, 1));
    }

    public function testIsPositionFreeReturnsFalseWhenOccupied(): void
    {
        $this->fake->planetPositionCount = 1;
        $this->assertFalse(PlayerUtil::isPositionFree(1, 2, 100, 5, 1));
    }

    public function testSendMessageInsertsRow(): void
    {
        $universe = defined('ROOT_UNI') ? ROOT_UNI : 1;
        PlayerUtil::sendMessage(7, 0, 'Tower', 5, 'Subject', 'Body text', TIMESTAMP, null, 1, $universe);

        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame(7, $this->fake->achievement->messages[0][':userId']);
        $this->assertSame('Body text', $this->fake->achievement->messages[0][':text']);
    }
}
