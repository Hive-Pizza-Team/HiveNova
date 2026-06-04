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
        ]), 1);

        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $this->fake->achievement->users[1] = [
            'id' => 1,
            'lang' => 'en',
            'universe' => 1,
            'astrophysics_tech' => 0,
            'factor' => ['Planets' => 0],
        ];

        $this->fake->planetPositionCount = 1;
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_colonisation_aborts_when_position_occupied(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 7,
            'fleet_end_planet' => 8,
        ]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function test_colonisation_aborts_when_planet_cap_reached(): void
    {
        $this->fake->planetPositionCount = 0;
        $this->fake->planetCountByOwner[1] = 99;

        $fleet = missionFleetFixture([
            'fleet_mission' => 7,
            'fleet_end_planet' => 9,
        ]);

        $mission = new MissionCaseColonisation($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }
}
