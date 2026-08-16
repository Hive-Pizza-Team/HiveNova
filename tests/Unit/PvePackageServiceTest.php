<?php

use HiveNova\Core\Config;
use HiveNova\Core\PvePackageService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PvePackageServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', array_fill(0, 50, 1))]), 1);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testCurrentLootGrowsWithAgeAndCaps(): void
    {
        $row = [
            'metal' => 5000,
            'crystal' => 2500,
            'spawned_at' => TIMESTAMP - 10 * 3600,
        ];
        $loot = PvePackageService::currentLoot($row, TIMESTAMP);
        $this->assertSame(10000, $loot['metal']);
        $this->assertSame(7500, $loot['crystal']);

        $capped = PvePackageService::currentLoot([
            'metal' => 49000,
            'crystal' => 24000,
            'spawned_at' => TIMESTAMP - 100 * 3600,
        ], TIMESTAMP);
        $this->assertSame(PVE_PACKAGE_CAP_METAL, $capped['metal']);
        $this->assertSame(PVE_PACKAGE_CAP_CRYSTAL, $capped['crystal']);
    }

    public function testSpawnBudgetScalesThenCaps(): void
    {
        $this->assertSame(2, PvePackageService::spawnBudget(0));
        $this->assertSame(7, PvePackageService::spawnBudget(5));
        $this->assertSame(PVE_SPAWN_HARD_CAP, PvePackageService::spawnBudget(100));
    }

    public function testCollectRemovesEmptyPackage(): void
    {
        $this->fake->salvagePackages[] = [
            'id' => 1,
            'universe' => 1,
            'galaxy' => 1,
            'system' => 1,
            'planet' => 4,
            'metal' => 100,
            'crystal' => 50,
            'spawned_at' => TIMESTAMP,
            'expires_at' => TIMESTAMP + 100,
            'tier' => 1,
            'encounter_seed' => 1,
        ];
        $this->assertTrue(PvePackageService::collect(1, 100, 50, 100, 50));
        $this->assertSame([], $this->fake->salvagePackages);
    }

    public function testCollectRejectsStaleSnapshot(): void
    {
        $this->fake->salvagePackages[] = [
            'id' => 1,
            'universe' => 1,
            'galaxy' => 1,
            'system' => 1,
            'planet' => 4,
            'metal' => 40,
            'crystal' => 20,
            'spawned_at' => TIMESTAMP,
            'expires_at' => TIMESTAMP + 100,
            'tier' => 1,
            'encounter_seed' => 1,
        ];
        $this->assertFalse(PvePackageService::collect(1, 100, 50, 100, 50));
        $this->assertSame(40, $this->fake->salvagePackages[0]['metal']);
        $this->assertSame(20, $this->fake->salvagePackages[0]['crystal']);
    }

    public function testAttachToPlanetSetsPlanetId(): void
    {
        $this->fake->salvagePackages[] = [
            'id' => 1,
            'universe' => 1,
            'galaxy' => 2,
            'system' => 3,
            'planet' => 8,
            'planet_id' => null,
            'metal' => 100,
            'crystal' => 50,
            'spawned_at' => TIMESTAMP,
            'expires_at' => TIMESTAMP + 100,
            'tier' => 1,
            'encounter_seed' => 1,
        ];
        PvePackageService::attachToPlanet(1, 2, 3, 8, 77);
        $this->assertSame(77, $this->fake->salvagePackages[0]['planet_id']);
    }

    public function testSpyHintRevealsFamilyThenTier(): void
    {
        $row = [
            'metal' => 1000,
            'crystal' => 500,
            'spawned_at' => TIMESTAMP,
            'encounter_seed' => 10,
            'tier' => 3,
        ];
        $low = PvePackageService::spyHint($row, 3);
        $this->assertArrayNotHasKey('family', $low);
        $mid = PvePackageService::spyHint($row, 4);
        $this->assertSame('pirate', $mid['family']);
        $this->assertArrayNotHasKey('tier', $mid);
        $high = PvePackageService::spyHint($row, 8);
        $this->assertSame(3, $high['tier']);
    }

    public function testExpireOldDeletesPastTtl(): void
    {
        $this->fake->salvagePackages[] = [
            'id' => 1,
            'universe' => 1,
            'galaxy' => 1,
            'system' => 1,
            'planet' => 1,
            'metal' => 1,
            'crystal' => 1,
            'spawned_at' => TIMESTAMP - 10,
            'expires_at' => TIMESTAMP - 1,
            'tier' => 1,
            'encounter_seed' => 1,
        ];
        PvePackageService::expireOld(1, TIMESTAMP);
        $this->assertSame([], $this->fake->salvagePackages);
    }

    public function testSpawnTickDisabledWhenModuleOff(): void
    {
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', array_fill(0, 50, 0))]), 1);
        $this->assertSame(0, PvePackageService::spawnTick(1, TIMESTAMP));
    }

    public function testCountOnlineReadsFakeUserWindow(): void
    {
        $this->fake->onlineUserCount = 4;
        $this->assertSame(4, PvePackageService::countOnline(1, TIMESTAMP));
    }

    public function testSpawnTickCreatesPackageOnEmptyMap(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => implode(';', array_fill(0, 50, 1)),
            'max_galaxy' => 1,
            'max_system' => 1,
            'max_planets' => 1,
        ]), 1);
        $this->fake->onlineUserCount = 0;
        $created = PvePackageService::spawnTick(1, TIMESTAMP);
        $this->assertGreaterThan(0, $created);
        $this->assertNotEmpty($this->fake->salvagePackages);
        $this->assertSame(1, (int) $this->fake->salvagePackages[0]['galaxy']);
    }
}
