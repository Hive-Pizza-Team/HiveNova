<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseSalvage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCaseSalvageTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => implode(';', array_fill(0, 50, 1)),
            'max_planets' => 15,
        ]), 1);
        transportMissionEnvironmentSetup();
        $this->fake->achievement->users[1] = [
            'id' => 1,
            'lang' => 'en',
            'universe' => 1,
            'factor' => ['ShipStorage' => 0],
        ];
        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Home', 'id_owner' => 1];
    }

    protected function tearDown(): void
    {
        transportMissionEnvironmentTeardown();
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function package(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'universe' => 1,
            'galaxy' => 1,
            'system' => 1,
            'planet' => 8,
            'planet_id' => null,
            'metal' => 5000,
            'crystal' => 2500,
            'spawned_at' => TIMESTAMP,
            'expires_at' => TIMESTAMP + 86400,
            'tier' => 1,
            'encounter_seed' => 50,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function salvageFleet(array $overrides = []): array
    {
        return missionFleetFixture(array_merge([
            'fleet_mission' => 18,
            'fleet_end_id' => 0,
            'fleet_end_galaxy' => 1,
            'fleet_end_system' => 1,
            'fleet_end_planet' => 8,
            'fleet_array' => '210,20;',
            'fleet_amount' => 20,
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
            'fleet_resource_deuterium' => 0,
        ], $overrides));
    }

    public function testHarvestsPackageWithoutCombatWhenSeedSkipsEncounter(): void
    {
        $this->fake->salvagePackages[] = $this->package();
        $mission = new MissionCaseSalvage($this->salvageFleet());
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(5000, (int) $mission->_fleet['fleet_resource_metal']);
        $this->assertSame(2500, (int) $mission->_fleet['fleet_resource_crystal']);
        $this->assertSame([], $this->fake->salvagePackages);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function testSecondHarvestBouncesWhenPackageGone(): void
    {
        $this->fake->salvagePackages[] = $this->package();
        $first = new MissionCaseSalvage($this->salvageFleet());
        $first->TargetEvent();
        $second = new MissionCaseSalvage($this->salvageFleet(['fleet_id' => 2]));
        $second->TargetEvent();

        $this->assertSame(FLEET_RETURN, $second->_fleet['fleet_mess']);
        $this->assertSame(0, (int) $second->_fleet['fleet_resource_metal']);
        $this->assertGreaterThan(1, count($this->fake->achievement->messages));
    }

    public function testBounceWhenNoPackage(): void
    {
        $mission = new MissionCaseSalvage($this->salvageFleet());
        $mission->TargetEvent();
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(0, (int) $mission->_fleet['fleet_resource_metal']);
    }
}
