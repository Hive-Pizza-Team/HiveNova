<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseRecycling;
use HiveNova\Mission\MissionCaseStay;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCasePhase5Test extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->defineMissionModules();

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(new Config([
            'uni' => 1,
            'fleet_speed' => 2500,
            'game_speed' => 1,
            'moduls' => implode(';', array_fill(0, 50, 1)),
        ]), 1);

        $userDefaults = [
            'lang' => 'en',
            'universe' => 1,
            'combustion_tech' => 0,
            'impulse_motor_tech' => 0,
            'hyperspace_motor_tech' => 0,
        ];
        $this->fake->achievement->users[1] = array_merge(['id' => 1], $userDefaults);
        $this->fake->achievement->users[2] = array_merge(['id' => 2], $userDefaults);

        $GLOBALS['pricelist'][209] = array_merge($GLOBALS['pricelist'][209] ?? [], [
            'capacity' => 20000,
            'cost' => [901 => 1000, 902 => 600, 903 => 0],
        ]);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_recycling_returns_when_no_debris(): void
    {
        $this->fake->planetRowsById[99] = ['der_metal' => 0, 'der_crystal' => 0, 'total' => 0];

        $fleet = missionFleetFixture([
            'fleet_mission' => 8,
            'fleet_array' => '209,2;',
        ]);

        $mission = new MissionCaseRecycling($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_recycling_collects_debris_with_recycler(): void
    {
        $this->fake->planetRowsById[99] = [
            'der_metal' => 10000,
            'der_crystal' => 5000,
            'total' => 15000,
        ];

        $fleet = missionFleetFixture([
            'fleet_mission' => 8,
            'fleet_array' => '209,1;',
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
        ]);

        $mission = new MissionCaseRecycling($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertGreaterThan(0, $mission->_fleet['fleet_resource_metal']);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function test_stay_target_event_sends_message_and_kills_fleet(): void
    {
        $this->fake->planetRowsById[99] = ['id' => 99, 'id_owner' => 2, 'name' => 'Target'];

        $fleet = missionFleetFixture([
            'fleet_mission' => 5,
            'fleet_array' => '202,5;',
            'fleet_target_owner' => 2,
        ]);

        $mission = new MissionCaseStay($fleet);
        $mission->TargetEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    private function defineMissionModules(): void
    {
        $modules = [
            'MODULE_MISSION_ATTACK' => 1,
            'MODULE_MISSION_STATION' => 36,
            'MODULE_MISSION_RECYCLE' => 32,
            'MODULE_ACHIEVEMENTS' => 46,
        ];
        foreach ($modules as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
