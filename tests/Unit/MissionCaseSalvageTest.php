<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseSalvage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';
require_once __DIR__ . '/../Support/MissionCombatFixtures.php';

class MissionCaseSalvageTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        missionCombatEnvironmentSetup();
        Config::setInstance(missionCombatConfig(['max_planets' => 15]), 1);
        transportMissionEnvironmentSetup();
        $this->fake->achievement->users[1] = missionCombatUser(1);
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

    public function testEncounterDestroysWeakFleet(): void
    {
        $this->fake->salvagePackages[] = $this->package([
            'encounter_seed' => 10,
            'tier' => 1,
        ]);
        $mission = new MissionCaseSalvage($this->salvageFleet([
            'fleet_array' => '202,1;',
            'fleet_amount' => 1,
        ]));
        $mission->TargetEvent();
        $this->assertSame(1, $mission->kill);
    }

    public function testEncounterSurvivesAndCollects(): void
    {
        $this->fake->salvagePackages[] = $this->package([
            'encounter_seed' => 10,
            'tier' => 1,
        ]);
        $mission = new MissionCaseSalvage($this->salvageFleet([
            'fleet_array' => '202,100;',
            'fleet_amount' => 100,
        ]));
        $mission->TargetEvent();
        $this->assertSame(0, $mission->kill);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertGreaterThan(0, (int) $mission->_fleet['fleet_resource_metal']);
    }

    public function testPlanetOwnerAccusedTriggersFightOnBorderlineSeed(): void
    {
        $this->fake->accusedDestIds = [22];
        $this->fake->planetRowsById[55] = ['id' => 55, 'id_owner' => 22, 'name' => 'Accused'];
        $this->fake->salvagePackages[] = $this->package([
            'planet_id' => 55,
            'encounter_seed' => 45,
            'tier' => 1,
        ]);
        $mission = new MissionCaseSalvage($this->salvageFleet([
            'fleet_array' => '202,100;',
            'fleet_amount' => 100,
        ]));
        $mission->TargetEvent();
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function testEndStayEventIsNoop(): void
    {
        $mission = new MissionCaseSalvage($this->salvageFleet(['fleet_mess' => FLEET_HOLD]));
        $mission->EndStayEvent();
        $this->assertSame(FLEET_HOLD, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->fleetUpdates);
    }

    public function testReturnEventRestoresFleet(): void
    {
        $mission = new MissionCaseSalvage($this->salvageFleet([
            'fleet_mess' => FLEET_RETURN,
            'fleet_resource_metal' => 100,
            'fleet_resource_crystal' => 50,
        ]));
        $mission->ReturnEvent();
        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function testHarvestIgnoresMissingPackagePlanetOwner(): void
    {
        $this->fake->salvagePackages[] = $this->package([
            'planet_id' => 404,
            'encounter_seed' => 99,
        ]);
        $mission = new MissionCaseSalvage($this->salvageFleet());
        $mission->TargetEvent();
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }
}
