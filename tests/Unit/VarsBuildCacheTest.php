<?php

use HiveNova\Core\Cache;
use HiveNova\Core\Cache\VarsBuildCache;
use HiveNova\Core\Database;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class VarsBuildCacheFakeAchievementDatabase extends FakeAchievementDatabase
{
    /** @var list<array<string, mixed>> */
    public array $varsRows = [];

    /** @var list<array<string, mixed>> */
    public array $varsRequireRows = [];

    /** @var list<array<string, mixed>> */
    public array $varsRapidfireRows = [];

    /** @var list<string> */
    public array $nativeQueries = [];

    public function nativeQuery($qry)
    {
        $this->nativeQueries[] = $qry;

        if (str_contains($qry, '%%VARS_REQUIRE%%')) {
            return $this->varsRequireRows;
        }

        if (str_contains($qry, '%%VARS_RAPIDFIRE%%')) {
            return $this->varsRapidfireRows;
        }

        if (str_contains($qry, '%%VARS%%')) {
            return $this->varsRows;
        }

        return parent::nativeQuery($qry);
    }
}

class VarsBuildCacheTest extends TestCase
{
    use SwapDatabaseInstance;

    private VarsBuildCacheFakeAchievementDatabase $achievement;

    private FakeDatabase $fakeDb;

    protected function setUp(): void
    {
        $this->achievement = new VarsBuildCacheFakeAchievementDatabase();
        $this->fakeDb = new FakeDatabase($this->achievement);
        $this->swapDatabaseInstance($this->fakeDb);
        $this->resetCacheSingleton();
        $this->clearVarsCacheFile();
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        $this->resetCacheSingleton();
        $this->clearVarsCacheFile();
    }

    private function resetCacheSingleton(): void
    {
        $ref = new ReflectionClass(Cache::class);
        $prop = $ref->getProperty('obj');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    private function clearVarsCacheFile(): void
    {
        $path = CACHE_PATH . 'cache.vars.php';
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function varsRow(array $overrides = []): array
    {
        return array_merge([
            'elementID' => 1,
            'name' => 'metal_mine',
            'class' => 0,
            'onPlanetType' => '1,3',
            'onePerPlanet' => 0,
            'factor' => 1.5,
            'maxLevel' => 255,
            'cost901' => 60,
            'cost902' => 15,
            'cost903' => 0,
            'cost911' => 0,
            'cost921' => 0,
            'consumption1' => null,
            'consumption2' => null,
            'speedTech' => null,
            'speed1' => null,
            'speed2' => null,
            'capacity' => null,
            'timeBonus' => null,
            'attack' => 0,
            'defend' => 0,
            'bonusAttack' => 0.0,
            'bonusDefensive' => 0.0,
            'bonusShield' => 0.0,
            'bonusBuildTime' => 0.0,
            'bonusResearchTime' => 0.0,
            'bonusShipTime' => 0.0,
            'bonusDefensiveTime' => 0.0,
            'bonusResource' => 0.0,
            'bonusEnergy' => 0.0,
            'bonusResourceStorage' => 0.0,
            'bonusShipStorage' => 0.0,
            'bonusFlyTime' => 0.0,
            'bonusFleetSlots' => 0.0,
            'bonusPlanets' => 0.0,
            'bonusSpyPower' => 0.0,
            'bonusExpedition' => 0.0,
            'bonusGateCoolTime' => 0.0,
            'bonusMoreFound' => 0.0,
            'bonusAttackUnit' => 0,
            'bonusDefensiveUnit' => 0,
            'bonusShieldUnit' => 0,
            'bonusBuildTimeUnit' => 0,
            'bonusResearchTimeUnit' => 0,
            'bonusShipTimeUnit' => 0,
            'bonusDefensiveTimeUnit' => 0,
            'bonusResourceUnit' => 0,
            'bonusEnergyUnit' => 0,
            'bonusResourceStorageUnit' => 0,
            'bonusShipStorageUnit' => 0,
            'bonusFlyTimeUnit' => 0,
            'bonusFleetSlotsUnit' => 0,
            'bonusPlanetsUnit' => 0,
            'bonusSpyPowerUnit' => 0,
            'bonusExpeditionUnit' => 0,
            'bonusGateCoolTimeUnit' => 0,
            'bonusMoreFoundUnit' => 0,
            'production901' => null,
            'production902' => null,
            'production903' => null,
            'production911' => null,
            'storage901' => null,
            'storage902' => null,
            'storage903' => null,
        ], $overrides);
    }

    public function testBuildCacheReturnsEmptyStructureWhenDatabaseIsEmpty(): void
    {
        $builder = new VarsBuildCache();
        $result = $builder->buildCache();

        $this->assertSame([], $result['resource']);
        $this->assertSame([], $result['requirements']);
        $this->assertSame([], $result['pricelist']);
        $this->assertSame([], $result['CombatCaps']);
        $this->assertSame([], $result['ProdGrid']);
        $this->assertSame([], $result['reslist']['build']);
        $this->assertSame([], $result['reslist']['tech']);
        $this->assertSame([], $result['reslist']['fleet']);
        $this->assertSame([], $result['reslist']['prod']);
        $this->assertSame([], $result['reslist']['storage']);
        $this->assertSame([], $result['reslist']['bonus']);
        $this->assertSame([], $result['reslist']['one']);
        $this->assertCount(3, $this->achievement->nativeQueries);
    }

    public function testBuildCacheClassifiesElementsAndAggregatesData(): void
    {
        $this->achievement->varsRequireRows = [
            ['elementID' => 202, 'requireID' => 115, 'requireLevel' => 1],
        ];
        $this->achievement->varsRows = [
            $this->varsRow([
                'elementID' => 1,
                'name' => 'metal_mine',
                'class' => 0,
                'onPlanetType' => '1,3',
                'production901' => 'return_build(901, {{ 1 }});',
                'storage901' => 'return_build(901, {{ 1 }});',
            ]),
            $this->varsRow([
                'elementID' => 108,
                'name' => 'computer_tech',
                'class' => 100,
                'onPlanetType' => '1',
            ]),
            $this->varsRow([
                'elementID' => 202,
                'name' => 'light_fighter',
                'class' => 200,
                'onPlanetType' => '1',
                'attack' => 50,
                'defend' => 10,
                'consumption1' => 20,
                'speed1' => 12500,
                'capacity' => 50,
            ]),
            $this->varsRow([
                'elementID' => 401,
                'name' => 'rocket_launcher',
                'class' => 400,
                'onPlanetType' => '1',
                'attack' => 80,
                'defend' => 20,
            ]),
            $this->varsRow([
                'elementID' => 502,
                'name' => 'interplanetary_missile',
                'class' => 500,
                'onPlanetType' => '1',
            ]),
            $this->varsRow([
                'elementID' => 601,
                'name' => 'admiral',
                'class' => 600,
                'onPlanetType' => '1',
                'bonusAttack' => 0.25,
                'bonusAttackUnit' => 1,
            ]),
            $this->varsRow([
                'elementID' => 701,
                'name' => 'dm_boost',
                'class' => 700,
                'onPlanetType' => '1',
            ]),
            $this->varsRow([
                'elementID' => 31,
                'name' => 'intergalactic_research',
                'class' => 0,
                'onPlanetType' => '3',
                'onePerPlanet' => 1,
            ]),
        ];
        $this->achievement->varsRapidfireRows = [
            ['elementID' => 202, 'rapidfireID' => 401, 'shoots' => 5],
        ];

        $result = (new VarsBuildCache())->buildCache();

        $this->assertSame('metal_mine', $result['resource'][1]);
        $this->assertSame('light_fighter', $result['resource'][202]);
        $this->assertSame([115 => 1], $result['requirements'][202]);
        $this->assertSame(50, $result['CombatCaps'][202]['attack']);
        $this->assertSame(10, $result['CombatCaps'][202]['shield']);
        $this->assertSame(5, $result['CombatCaps'][202]['sd'][401]);
        $this->assertSame(60, $result['pricelist'][1]['cost'][901]);
        $this->assertSame(1.5, $result['pricelist'][1]['factor']);
        $this->assertSame('return_build(901, {{ 1 }});', $result['ProdGrid'][1]['production'][901]);
        $this->assertSame('return_build(901, {{ 1 }});', $result['ProdGrid'][1]['storage'][901]);

        $this->assertContains(1, $result['reslist']['build']);
        $this->assertContains(31, $result['reslist']['build']);
        $this->assertContains(108, $result['reslist']['tech']);
        $this->assertContains(202, $result['reslist']['fleet']);
        $this->assertContains(401, $result['reslist']['defense']);
        $this->assertContains(502, $result['reslist']['missile']);
        $this->assertContains(601, $result['reslist']['officier']);
        $this->assertContains(701, $result['reslist']['dmfunc']);
        $this->assertContains(1, $result['reslist']['prod']);
        $this->assertContains(1, $result['reslist']['storage']);
        $this->assertContains(601, $result['reslist']['bonus']);
        $this->assertContains(31, $result['reslist']['one']);
        $this->assertContains(1, $result['reslist']['allow'][1]);
        $this->assertContains(1, $result['reslist']['allow'][3]);
        $this->assertContains(31, $result['reslist']['allow'][3]);
        $this->assertNotContains(31, $result['reslist']['allow'][1]);
    }

    public function testCacheBuildStoresAndLoadsFromDisk(): void
    {
        $this->achievement->varsRows = [
            $this->varsRow(['elementID' => 1, 'name' => 'metal_mine']),
        ];

        $cache = Cache::get();
        $cache->add('vars', VarsBuildCache::class);
        $cache->buildCache('vars');

        $this->assertFileExists(CACHE_PATH . 'cache.vars.php');

        $this->resetCacheSingleton();
        $this->achievement->nativeQueries = [];
        $reloaded = Cache::get();
        $reloaded->add('vars', VarsBuildCache::class);
        $data = $reloaded->getData('vars', false);

        $this->assertSame('metal_mine', $data['resource'][1]);
        $this->assertSame([], $this->achievement->nativeQueries, 'disk load must not hit the database');
    }

    public function testCacheFlushRebuildsFromDatabase(): void
    {
        $this->achievement->varsRows = [
            $this->varsRow(['elementID' => 1, 'name' => 'metal_mine']),
        ];

        $cache = Cache::get();
        $cache->add('vars', VarsBuildCache::class);
        $cache->buildCache('vars');

        $this->achievement->varsRows = [
            $this->varsRow(['elementID' => 2, 'name' => 'crystal_mine']),
        ];
        $this->achievement->nativeQueries = [];

        $cache->flush('vars');
        $data = $cache->getData('vars', false);

        $this->assertSame('crystal_mine', $data['resource'][2]);
        $this->assertArrayNotHasKey(1, $data['resource']);
        $this->assertCount(3, $this->achievement->nativeQueries);
    }

    public function testGetDataRebuildsWhenCacheFileMissing(): void
    {
        $this->achievement->varsRows = [
            $this->varsRow(['elementID' => 22, 'name' => 'solar_plant']),
        ];

        $cache = Cache::get();
        $cache->add('vars', VarsBuildCache::class);
        $data = $cache->getData('vars');

        $this->assertSame('solar_plant', $data['resource'][22]);
        $this->assertFileExists(CACHE_PATH . 'cache.vars.php');
        $this->assertCount(3, $this->achievement->nativeQueries);
    }
}
