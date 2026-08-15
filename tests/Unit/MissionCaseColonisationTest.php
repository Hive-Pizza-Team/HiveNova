<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseColonisation;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCaseColonisationTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(new Config([
            'uni' => 1,
            'max_galaxy' => 9,
            'max_system' => 499,
            'max_planets' => 15,
            'min_player_planets' => 1,
            'planets_tech' => 4,
            'planets_officier' => 2,
            'planets_per_tech' => 1,
            'planet_factor' => 1.0,
            'metal_start' => 500,
            'crystal_start' => 500,
            'deuterium_start' => 0,
            'moduls' => implode(';', array_fill(0, 50, 0)),
        ]), 1);

        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $GLOBALS['resource'][208] = 'colony_ship';
        $GLOBALS['reslist']['bonus'] = $GLOBALS['reslist']['bonus'] ?? [];

        $this->fake->achievement->users[1] = [
            'id' => 1,
            'lang' => 'en',
            'universe' => 1,
            'authlevel' => 0,
            'astrophysics_tech' => 1,
            'factor' => ['Planets' => 0],
        ];

        $this->fake->planetPositionCount = 0;
        $this->fake->planetCountByOwner[1] = 0;
        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Homeworld', 'id_owner' => 1];
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function colonisationFleet(array $overrides = []): array
    {
        return missionFleetFixture(array_merge([
            'fleet_mission' => 7,
            'fleet_end_planet' => 8,
            'fleet_array' => '208,1;',
            'fleet_amount' => 1,
            'fleet_resource_metal' => 1000,
            'fleet_resource_crystal' => 500,
            'fleet_resource_deuterium' => 250,
        ], $overrides));
    }

    public function test_colonisation_succeeds_and_consumes_single_colony_ship(): void
    {
        $fleet = $this->colonisationFleet();

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($this->fake->planetInserts);
        $this->assertSame(200, $mission->_fleet['fleet_end_id']);
        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertTrue(
            (bool) array_filter(
                $this->fake->fleetUpdates,
                static fn (array $update): bool => !empty($update['delete'])
            )
        );
    }

    public function test_colonisation_succeeds_and_keeps_remaining_colony_ships(): void
    {
        $fleet = $this->colonisationFleet([
            'fleet_array' => '208,3;202,5;',
            'fleet_amount' => 8,
        ]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(0, $mission->kill);
        $this->assertSame(200, $mission->_fleet['fleet_end_id']);
        $this->assertSame('208,2;202,5;', $mission->_fleet['fleet_array']);
        $this->assertSame(7, $mission->_fleet['fleet_amount']);
        $this->assertSame(0, $mission->_fleet['fleet_resource_metal']);
        $this->assertSame(0, $mission->_fleet['fleet_resource_crystal']);
        $this->assertSame(0, $mission->_fleet['fleet_resource_deuterium']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_colonisation_aborts_when_position_occupied(): void
    {
        $this->fake->planetPositionCount = 1;

        $fleet = $this->colonisationFleet(['fleet_end_planet' => 8]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(0, $mission->kill);
        $this->assertEmpty($this->fake->planetInserts);
        $this->assertCount(1, $this->fake->achievement->messages);
    }

    public function test_colonisation_aborts_when_coordinates_invalid(): void
    {
        $fleet = $this->colonisationFleet(['fleet_end_planet' => 16]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->planetInserts);
        $this->assertCount(1, $this->fake->achievement->messages);
    }

    public function test_colonisation_aborts_when_astro_tech_too_low_for_edge_slot(): void
    {
        $this->fake->achievement->users[1]['astrophysics_tech'] = 0;

        $fleet = $this->colonisationFleet(['fleet_end_planet' => 1]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->planetInserts);
        $this->assertCount(1, $this->fake->achievement->messages);
    }

    public function test_colonisation_aborts_when_planet_cap_reached(): void
    {
        $this->fake->planetCountByOwner[1] = 2;

        $fleet = $this->colonisationFleet(['fleet_end_planet' => 9]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->planetInserts);
        $this->assertCount(1, $this->fake->achievement->messages);
    }

    public function test_colonisation_end_stay_event_is_noop(): void
    {
        $fleet = $this->colonisationFleet(['fleet_mess' => FLEET_HOLD]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->EndStayEvent();

        $this->assertSame(FLEET_HOLD, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->achievement->messages);
        $this->assertEmpty($this->fake->fleetUpdates);
    }

    public function test_colonisation_return_event_restores_fleet(): void
    {
        $fleet = $this->colonisationFleet([
            'fleet_mess' => FLEET_RETURN,
            'fleet_array' => '208,2;',
            'fleet_amount' => 2,
            'fleet_resource_metal' => 100,
            'fleet_resource_crystal' => 50,
            'fleet_resource_deuterium' => 25,
        ]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->ReturnEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertTrue(
            (bool) array_filter(
                $this->fake->fleetUpdates,
                static fn (array $update): bool => !empty($update['delete'])
            )
        );
    }
}
