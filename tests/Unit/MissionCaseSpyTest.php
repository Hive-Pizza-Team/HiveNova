<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseSpy;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';
require_once __DIR__ . '/../Support/MissionCombatFixtures.php';

class MissionCaseSpyTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        if (!defined('SPY_DIFFENCE_FACTOR')) {
            define('SPY_DIFFENCE_FACTOR', 1);
        }
        if (!defined('SPY_VIEW_FACTOR')) {
            define('SPY_VIEW_FACTOR', 1);
        }
        if (!defined('ENABLE_SIMULATOR_LINK')) {
            define('ENABLE_SIMULATOR_LINK', false);
        }
        if (!defined('MODULE_SIMULATOR')) {
            define('MODULE_SIMULATOR', 25);
        }

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $modules = implode(';', array_fill(0, 50, 1));
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => $modules,
        ]), 1);

        $this->fake->achievement->users[1] = missionCombatUser(1, [
            'spy_tech' => 10,
            'spyMessagesMode' => 0,
            'timezone' => 'UTC',
        ]);
        $this->fake->achievement->users[2] = missionCombatUser(2, [
            'spy_tech' => 1,
            'timezone' => 'UTC',
        ]);
        $this->fake->planetRowsById[10] = missionCombatPlanet(10, 1, ['name' => 'Spy Base']);
        $this->fake->planetRowsById[99] = missionCombatPlanet(99, 2, [
            'rocket_launcher' => 5,
            'light_fighter' => 0,
        ]);

        $GLOBALS['reslist']['resstype'] = [
            1 => [901, 902, 903],
            2 => [911],
        ];
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_spy_target_event_sends_report_to_owner_and_target(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_target_owner' => 2,
            'fleet_array' => '210,1;',
        ]);

        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }
}
