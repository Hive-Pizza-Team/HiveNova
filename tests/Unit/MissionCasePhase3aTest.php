<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Mission\MissionCaseAttack;
use HiveNova\Mission\MissionCaseDestruction;
use HiveNova\Mission\MissionCaseExpedition;
use HiveNova\Mission\MissionCaseTransport;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';
require_once __DIR__ . '/../Support/MissionCombatFixtures.php';

class MissionCasePhase3aTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->defineMissionModules();

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $this->fake->achievement->users[1] = [
            'id' => 1,
            'lang' => 'en',
            'universe' => 1,
        ];

        Config::setInstance(new Config([
            'uni' => 1,
            'Fleet_Cdr' => 0.3,
            'Defs_Cdr' => 0.0,
            'moduls' => implode(';', array_fill(0, 50, 1)),
        ]), 1);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_attack_returns_fleet_when_target_planet_missing(): void
    {
        $fleet = missionFleetFixture(['fleet_end_id' => 404]);
        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_destruction_returns_fleet_when_target_planet_missing(): void
    {
        $fleet = missionFleetFixture(['fleet_end_id' => 404, 'fleet_mission' => 9]);
        $mission = new MissionCaseDestruction($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_expedition_target_event_holds_fleet(): void
    {
        $fleet = missionFleetFixture();
        $mission = new MissionCaseExpedition($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_HOLD, $mission->_fleet['fleet_mess']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_transport_skips_delivery_when_target_destroyed(): void
    {
        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Origin'];
        // fleet_end_id 99 has no planet row → getPlanetName returns null

        $fleet = missionFleetFixture([
            'fleet_mission' => 3,
            'fleet_resource_metal' => 1000,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->achievement->messages);
    }

    public function test_expedition_return_event_restores_fleet(): void
    {
        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Home'];

        $fleet = missionFleetFixture([
            'fleet_array' => '202,3;',
            'fleet_resource_metal' => 100,
        ]);

        $mission = new MissionCaseExpedition($fleet);
        $mission->ReturnEvent();

        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function test_destruction_return_event_restores_fleet(): void
    {
        $combatFake = new MissionCombatFakeDatabase();
        $this->swapDatabaseInstance($combatFake);
        missionCombatEnvironmentSetup();
        Config::setInstance(missionCombatConfig(), 1);
        missionCombatSeedStandardTargets($combatFake);
        $combatFake->planetRowsById[99]['name'] = 'Moon Target';

        $fleet = missionFleetFixture([
            'fleet_mission' => 9,
            'fleet_array' => '202,5;',
            'fleet_resource_metal' => 300,
        ]);

        $mission = new MissionCaseDestruction($fleet);
        $mission->ReturnEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($combatFake->achievement->messages);
        $this->assertNotEmpty($combatFake->fleetUpdates);
    }

    public function test_destruction_moon_destroy_succeeds_with_fixed_seed(): void
    {
        $combatFake = new MissionCombatFakeDatabase();
        $this->swapDatabaseInstance($combatFake);
        missionCombatEnvironmentSetup();
        Config::setInstance(missionCombatConfig(), 1);
        missionCombatSeedStandardTargets($combatFake);
        $fleet = missionCombatMoonDestructionSetup($combatFake);

        mt_srand(18);
        $mission = new MissionCaseDestruction($fleet);
        $mission->TargetEvent();

        $this->assertNotEmpty($combatFake->achievement->deleteLog);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame('Win', missionCombatReportClass($combatFake, 1));
    }

    public function test_destruction_moon_destroy_fails_with_fixed_seed(): void
    {
        $combatFake = new MissionCombatFakeDatabase();
        $this->swapDatabaseInstance($combatFake);
        missionCombatEnvironmentSetup();
        Config::setInstance(missionCombatConfig(), 1);
        missionCombatSeedStandardTargets($combatFake);
        $fleet = missionCombatMoonDestructionSetup($combatFake);

        mt_srand(2);
        $mission = new MissionCaseDestruction($fleet);
        $mission->TargetEvent();

        $this->assertEmpty($combatFake->achievement->deleteLog);
        $this->assertSame(0, $mission->kill);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_destruction_win_can_destroy_attacking_fleet_with_fixed_seed(): void
    {
        $combatFake = new MissionCombatFakeDatabase();
        $this->swapDatabaseInstance($combatFake);
        missionCombatEnvironmentSetup();
        Config::setInstance(missionCombatConfig(), 1);
        missionCombatSeedStandardTargets($combatFake);
        $fleet = missionCombatMoonDestructionSetup($combatFake);

        mt_srand(1);
        $mission = new MissionCaseDestruction($fleet);
        $mission->TargetEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_destruction_with_acs_runs_combat(): void
    {
        $combatFake = new MissionCombatFakeDatabase();
        $this->swapDatabaseInstance($combatFake);
        missionCombatEnvironmentSetup();
        Config::setInstance(missionCombatConfig(), 1);
        missionCombatSeedStandardTargets($combatFake);
        $combatFake->fleetRowsById[2] = missionCombatAcsMemberFleet(2, 11, [
            'fleet_owner' => 3,
            'fleet_mission' => 9,
        ]);
        $combatFake->achievement->users[3] = missionCombatUser(3);

        $fleet = missionFleetFixture([
            'fleet_mission' => 9,
            'fleet_group' => 11,
            'fleet_array' => '202,100;',
            'fleet_amount' => 100,
        ]);

        $mission = new MissionCaseDestruction($fleet);
        $mission->TargetEvent();

        $aksDeletes = array_filter(
            $combatFake->fleetUpdates,
            static fn (array $update): bool => str_contains($update['sql'], '%%AKS%%')
                && !empty($update['delete'])
        );

        $this->assertNotEmpty($aksDeletes);
        $this->assertGreaterThanOrEqual(2, count($combatFake->achievement->messages));
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
            'MODULE_ACHIEVEMENTS' => 46,
        ];
        foreach ($modules as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
