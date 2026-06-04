<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\FleetFunctions;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetFunctionsDatabaseTest extends TestCase
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
            'moduls' => implode(';', array_fill(0, 50, 1)),
            'max_planets' => 15,
            'max_dm_missions' => 3,
            'halt_speed' => 1,
            'fleet_speed' => 2500,
        ]), 1);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testGetDMMissionLimitReadsConfig(): void
    {
        $user = ['universe' => 1];
        $this->assertSame(3, FleetFunctions::getDMMissionLimit($user));
    }

    public function testGetCurrentFleetsReturnsConfiguredCount(): void
    {
        $this->fake->fleetCountResult = 4;
        $this->assertSame(4, FleetFunctions::GetCurrentFleets(99));
    }

    public function testGetCurrentFleetsThisMissionFilter(): void
    {
        $this->fake->fleetCountResult = 2;
        $this->assertSame(2, FleetFunctions::GetCurrentFleets(99, 15, true));
    }

    public function testGetACSDurationReturnsRemainingTime(): void
    {
        $this->fake->aksRows[7] = ['ankunft' => TIMESTAMP + 500];
        $remaining = FleetFunctions::GetACSDuration(7);
        $this->assertSame(500, $remaining);
    }

    public function testGetACSDurationEmptyWhenMissing(): void
    {
        $this->assertSame(0, FleetFunctions::GetACSDuration(999));
    }

    public function testGetFleetShipInfoReturnsPerShipStats(): void
    {
        $GLOBALS['pricelist'][202]['speed'] = 12500;
        $GLOBALS['pricelist'][202]['tech'] = 1;
        $GLOBALS['pricelist'][202]['consumption'] = 20;

        $player = ['combustion_tech' => 0, 'impulse_motor_tech' => 0, 'hyperspace_motor_tech' => 0];
        $info = FleetFunctions::GetFleetShipInfo([202 => 2], $player);

        $this->assertArrayHasKey(202, $info);
        $this->assertSame(20, $info[202]['consumption']);
        $this->assertSame('2', $info[202]['amount']);
        $this->assertGreaterThan(0, $info[202]['speed']);
    }

    public function testGetFleetMissionsExpeditionIncludesStayBlock(): void
    {
        $GLOBALS['resource'][124] = 124;
        $user = [
            'id' => 1,
            'universe' => 1,
            124 => 3,
            'factor' => ['Expedition' => 0],
        ];
        $misInfo = [
            'planet' => 16,
            'planettype' => 1,
            'Ship' => [202 => 1],
        ];
        $planet = ['id_owner' => 0, 'der_metal' => 0, 'der_crystal' => 0];

        $result = FleetFunctions::GetFleetMissions($user, $misInfo, $planet);

        $this->assertContains(15, $result['MissionSelector']);
        $this->assertCount(3, $result['StayBlock']);
        $this->assertFalse($result['Exchange']);
    }

    private function defineMissionModules(): void
    {
        if (!defined('BASH_ON')) {
            define('BASH_ON', true);
        }
        if (!defined('BASH_COUNT')) {
            define('BASH_COUNT', 3);
        }
        if (!defined('BASH_TIME')) {
            define('BASH_TIME', 86400);
        }

        $modules = [
            'MODULE_MISSION_ATTACK' => 1,
            'MODULE_MISSION_ACS' => 42,
            'MODULE_MISSION_TRANSPORT' => 34,
            'MODULE_MISSION_HOLD' => 33,
            'MODULE_MISSION_SPY' => 24,
            'MODULE_MISSION_DESTROY' => 29,
            'MODULE_MISSION_EXPEDITION' => 30,
            'MODULE_MISSION_RECYCLE' => 32,
            'MODULE_MISSION_COLONY' => 35,
            'MODULE_MISSION_STATION' => 36,
            'MODULE_MISSION_TRADE' => 44,
            'MODULE_MISSION_TRANSFER' => 45,
            'MODULE_MISSION_DARKMATTER' => 31,
        ];
        foreach ($modules as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    public function testSendFleetBackReturnsFalseWhenFleetMissing(): void
    {
        $user = ['id' => 1];
        $this->assertFalse(FleetFunctions::SendFleetBack($user, 404));
    }

    public function testSendFleetBackSucceedsForOwnedFleet(): void
    {
        if (!defined('FLEET_RETURN')) {
            define('FLEET_RETURN', 1);
        }
        if (!defined('FLEET_HOLD')) {
            define('FLEET_HOLD', 2);
        }

        $this->fake->fleetRowsById[10] = [
            'start_time' => TIMESTAMP - 100,
            'fleet_start_time' => TIMESTAMP + 200,
            'fleet_mission' => 3,
            'fleet_group' => 0,
            'fleet_owner' => 1,
            'fleet_mess' => 0,
        ];

        $user = ['id' => 1];
        $this->assertTrue(FleetFunctions::SendFleetBack($user, 10));
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function testCheckBashReturnsFalseForInactiveTarget(): void
    {
        $GLOBALS['USER'] = ['id' => 1];
        $this->fake->planetOwners[55] = 2;
        $this->fake->userOnlinetime[2] = TIMESTAMP - INACTIVE - 100;

        $this->assertFalse(FleetFunctions::CheckBash(55));
    }

    public function testCheckBashReturnsTrueWhenLogCountExceeded(): void
    {
        $GLOBALS['USER'] = ['id' => 1];
        $this->fake->planetOwners[55] = 2;
        $this->fake->userOnlinetime[2] = TIMESTAMP;
        $this->fake->bashLogCount = 5;

        $this->assertTrue(FleetFunctions::CheckBash(55));
    }
}
