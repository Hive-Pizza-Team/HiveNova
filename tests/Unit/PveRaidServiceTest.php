<?php

use HiveNova\Core\Config;
use HiveNova\Core\PveRaidService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PveRaidServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    private array $savedResource = [];
    private array $savedReslist = [];
    private array $savedPricelist = [];

    protected function setUp(): void
    {
        global $resource, $reslist, $pricelist;
        $this->savedResource = $resource;
        $this->savedReslist = $reslist;
        $this->savedPricelist = $pricelist;

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => implode(';', array_fill(0, 50, 1)),
            'max_planets' => 15,
        ]), 1);

        $available = new ReflectionProperty(\HiveNova\Core\Universe::class, 'availableUniverses');
        $available->setAccessible(true);
        $available->setValue([1]);
        $current = new ReflectionProperty(\HiveNova\Core\Universe::class, 'currentUniverse');
        $current->setAccessible(true);
        $current->setValue(1);

        $this->fake->achievement->users[10] = [
            'id' => 10,
            'urlaubs_modus' => 0,
            'universe' => 1,
            'lang' => 'en',
        ];
        $this->fake->planetRowsById[55] = [
            'id' => 55,
            'id_owner' => 10,
            'galaxy' => 1,
            'system' => 2,
            'planet' => 3,
            'planet_type' => 1,
            'universe' => 1,
            'destruyed' => 0,
        ];
    }

    protected function tearDown(): void
    {
        global $resource, $reslist, $pricelist;
        $resource = $this->savedResource;
        $reslist = $this->savedReslist;
        $pricelist = $this->savedPricelist;

        foreach (['availableUniverses', 'currentUniverse', 'emulatedUniverse'] as $prop) {
            $ref = new ReflectionProperty(\HiveNova\Core\Universe::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null);
        }
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function outward(array $overrides = []): array
    {
        return array_merge([
            'fleet_owner' => 10,
            'fleet_start_id' => 55,
            'fleet_start_galaxy' => 1,
            'fleet_start_system' => 2,
            'fleet_start_planet' => 3,
            'fleet_start_type' => 1,
            'fleet_amount' => 40,
        ], $overrides);
    }

    public function testSkipsVacationMode(): void
    {
        $this->fake->achievement->users[10]['urlaubs_modus'] = 1;
        $this->assertFalse(PveRaidService::trySpawnRaid(1, 10, $this->outward(), TIMESTAMP, false));
        $this->assertSame(0, $this->fake->lastFleetInsertId);
    }

    public function testSkipsWhenHangarNotWeak(): void
    {
        global $resource, $reslist, $pricelist;
        $resource[204] = 'light_hunter';
        $reslist['fleet'] = [204];
        $reslist['defense'] = [];
        $pricelist[204]['attack'] = 50;
        $this->fake->planetRowsById[55]['light_hunter'] = 20;

        $this->assertFalse(PveRaidService::trySpawnRaid(1, 10, $this->outward(['fleet_amount' => 10]), TIMESTAMP, false));
        $this->assertSame(0, $this->fake->lastFleetInsertId);
    }

    public function testSpawnsNpcMissionOneWhenHangarWeak(): void
    {
        $this->assertTrue(PveRaidService::trySpawnRaid(1, 10, $this->outward(), TIMESTAMP, false));
        $this->assertSame(99, $this->fake->lastFleetInsertId);
        $row = $this->fake->fleetRowsById[99];
        $this->assertSame(0, $row['fleet_owner']);
        $this->assertSame(1, $row['fleet_mission']);
        $this->assertSame(10, $row['fleet_target_owner']);
        $this->assertSame(55, $row['fleet_end_id']);
    }

    public function testSkipsWhenInboundNpcAlreadyExists(): void
    {
        $this->fake->fleetRowsById[9] = [
            'fleet_owner' => 0,
            'fleet_end_id' => 55,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
        ];
        $this->assertFalse(PveRaidService::trySpawnRaid(1, 10, $this->outward(), TIMESTAMP, false));
    }

    public function testHangarPowerSumsFleetAndDefenseAttack(): void
    {
        global $resource, $reslist, $pricelist;
        $resource[204] = 'light_hunter';
        $resource[401] = 'misil_launcher';
        $reslist['fleet'] = [204];
        $reslist['defense'] = [401];
        $pricelist[204]['attack'] = 50;
        $pricelist[401]['attack'] = 80;
        $power = PveRaidService::hangarPower([
            'light_hunter' => 2,
            'misil_launcher' => 1,
        ]);
        $this->assertSame(180, $power);
    }

    public function testRunReturnsZeroWhenModuleDisabled(): void
    {
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', array_fill(0, 50, 0))]), 1);
        $this->assertSame(0, PveRaidService::run(1, TIMESTAMP));
    }

    public function testRunSpawnsFromOutwardCombatFleet(): void
    {
        $this->fake->fleetRowsById[3] = [
            'fleet_owner' => 10,
            'fleet_start_id' => 55,
            'fleet_start_galaxy' => 1,
            'fleet_start_system' => 2,
            'fleet_start_planet' => 3,
            'fleet_start_type' => 1,
            'fleet_amount' => 40,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
            'fleet_universe' => 1,
        ];
        $this->assertSame(1, PveRaidService::run(1, TIMESTAMP));
        $this->assertSame(99, $this->fake->lastFleetInsertId);
    }

    public function testRunAccusedIdlePathSpawnsWhenChanceHits(): void
    {
        $this->fake->accusedDestIds = range(200, 280);
        foreach ($this->fake->accusedDestIds as $id) {
            $this->fake->achievement->users[$id] = [
                'id' => $id,
                'urlaubs_modus' => 0,
                'universe' => 1,
                'lang' => 'en',
            ];
            $this->fake->planetRowsById[$id] = [
                'id' => $id,
                'id_owner' => $id,
                'galaxy' => 1,
                'system' => 1,
                'planet' => 1,
                'planet_type' => 1,
                'universe' => 1,
                'destruyed' => 0,
            ];
        }
        $spawned = PveRaidService::run(1, TIMESTAMP);
        $this->assertGreaterThan(0, $spawned);
    }

    public function testTrySpawnRaidSkipsMissingPlanet(): void
    {
        $this->assertFalse(PveRaidService::trySpawnRaid(1, 10, $this->outward(['fleet_start_id' => 404]), TIMESTAMP, false));
    }

    public function testTrySpawnRaidSkipsAccusedIdleWhenHangarStrong(): void
    {
        global $resource, $reslist, $pricelist;
        $resource[204] = 'light_hunter';
        $reslist['fleet'] = [204];
        $reslist['defense'] = [];
        $pricelist[204]['attack'] = 50;
        $this->fake->planetRowsById[55]['light_hunter'] = 20;
        $this->assertFalse(PveRaidService::trySpawnRaid(1, 10, $this->outward(['fleet_amount' => 10]), TIMESTAMP, true));
    }
}
