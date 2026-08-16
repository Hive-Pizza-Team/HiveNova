<?php

use HiveNova\Core\Config;
use HiveNova\Core\FrequentLocationService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FrequentLocationServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(new Config([
            'uni'         => 1,
            'max_planets' => 15,
        ]), 1);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testRecordsForeignPlanet(): void
    {
        $recorded = FrequentLocationService::record(1, 2, 10, 4, 1, 1, [], 15, 1000);

        $this->assertTrue($recorded);
        $list = FrequentLocationService::listForUser(1, []);
        $this->assertCount(1, $list);
        $this->assertSame(2, $list[0]['galaxy']);
        $this->assertSame(10, $list[0]['system']);
        $this->assertSame(4, $list[0]['planet']);
        $this->assertSame(1, $list[0]['type']);
    }

    public function testPlanetAndMoonAtSameSlotAreSeparateRows(): void
    {
        FrequentLocationService::record(1, 1, 1, 8, 1, 1, [], 15, 100);
        FrequentLocationService::record(1, 1, 1, 8, 3, 9, [], 15, 200);

        $list = FrequentLocationService::listForUser(1, []);
        $this->assertCount(2, $list);
        $this->assertSame(3, $list[0]['type']);
        $this->assertSame(1, $list[1]['type']);
    }

    public function testResendUpdatesRecencyWithoutDuplicating(): void
    {
        FrequentLocationService::record(1, 3, 5, 7, 1, 1, [], 15, 100);
        FrequentLocationService::record(1, 4, 5, 7, 1, 1, [], 15, 150);
        FrequentLocationService::record(1, 3, 5, 7, 1, 1, [], 15, 300);

        $list = FrequentLocationService::listForUser(1, []);
        $this->assertCount(2, $list);
        $this->assertSame(3, $list[0]['galaxy']);
        $this->assertSame(300, $list[0]['lastUsed']);
        $this->assertSame(4, $list[1]['galaxy']);
    }

    public function testSkipsOwnPlanetMoonAndDebrisAtSameCoords(): void
    {
        $own = [['galaxy' => 1, 'system' => 2, 'planet' => 3]];

        $this->assertFalse(FrequentLocationService::record(1, 1, 2, 3, 1, 3, $own, 15, 1));
        $this->assertFalse(FrequentLocationService::record(1, 1, 2, 3, 3, 4, $own, 15, 1));
        $this->assertFalse(FrequentLocationService::record(1, 1, 2, 3, 2, 8, $own, 15, 1));
        $this->assertSame([], FrequentLocationService::listForUser(1, $own));
    }

    public function testRecycleMissionStoresDebrisType(): void
    {
        FrequentLocationService::record(1, 6, 6, 6, 1, 8, [], 15, 50);

        $list = FrequentLocationService::listForUser(1, []);
        $this->assertCount(1, $list);
        $this->assertSame(2, $list[0]['type']);
    }

    public function testSkipsExpeditionAndMarketSlots(): void
    {
        $this->assertFalse(FrequentLocationService::record(1, 1, 1, 16, 1, 15, [], 15, 1));
        $this->assertFalse(FrequentLocationService::record(1, 1, 1, 17, 1, 16, [], 15, 1));
        $this->assertSame([], FrequentLocationService::listForUser(1, []));
    }

    public function testTrimsOldestWhenOverCap(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            FrequentLocationService::record(1, 1, $i, 1, 1, 1, [], 15, $i);
        }

        $list = FrequentLocationService::listForUser(1, []);
        $this->assertCount(20, $list);
        $this->assertSame(21, $list[0]['system']);
        $this->assertSame(2, $list[19]['system']);
        $systems = array_column($list, 'system');
        $this->assertNotContains(1, $systems);
    }

    public function testRevisitKeepsOldUniqueAndDropsDifferentOldest(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            FrequentLocationService::record(1, 1, $i, 1, 1, 1, [], 15, $i);
        }

        $systems = array_column(FrequentLocationService::listForUser(1, []), 'system');
        $this->assertNotContains(1, $systems);

        FrequentLocationService::record(1, 1, 1, 1, 1, 1, [], 15, 100);

        $list = FrequentLocationService::listForUser(1, []);
        $this->assertCount(20, $list);
        $this->assertSame(1, $list[0]['system']);
        $systems = array_column($list, 'system');
        $this->assertContains(1, $systems);
        $this->assertNotContains(2, $systems);
    }

    public function testListHidesCoordsThatBecameOwnBodies(): void
    {
        FrequentLocationService::record(1, 2, 2, 2, 1, 1, [], 15, 10);

        $list = FrequentLocationService::listForUser(1, [['galaxy' => 2, 'system' => 2, 'planet' => 2]]);
        $this->assertSame([], $list);
    }

    public function testEmptyOwnerHasEmptyList(): void
    {
        $this->assertSame([], FrequentLocationService::listForUser(99, []));
    }

    public function testRecordFromFleetSkipsOwnedPlanetFromDatabase(): void
    {
        $this->fake->planetRowsById[10] = [
            'id'        => 10,
            'id_owner'  => 5,
            'galaxy'    => 1,
            'system'    => 1,
            'planet'    => 1,
            'destruyed' => 0,
        ];

        $this->assertFalse(FrequentLocationService::recordFromFleet(5, 1, 1, 1, 1, 3));
        $this->assertTrue(FrequentLocationService::recordFromFleet(5, 9, 9, 9, 1, 1));
        $this->assertCount(1, FrequentLocationService::listForUser(5, []));
    }

    public function testTryRecordFromFleetSwallowsDatabaseErrors(): void
    {
        $this->fake->throwOnFrequentLocations = true;

        FrequentLocationService::tryRecordFromFleet(1, 2, 3, 4, 1, 1);

        $this->fake->throwOnFrequentLocations = false;
        $this->assertSame([], FrequentLocationService::listForUser(1, []));
    }
}
