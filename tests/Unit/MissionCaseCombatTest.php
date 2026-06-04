<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseAttack;
use HiveNova\Mission\MissionCaseDestruction;
use HiveNova\Mission\MissionCaseMIP;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';
require_once __DIR__ . '/../Support/MissionCombatFixtures.php';

class MissionCaseCombatTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        if (!defined('MAX_ATTACK_ROUNDS')) {
            define('MAX_ATTACK_ROUNDS', 6);
        }

        $this->defineMissionModules();

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $modules = implode(';', array_fill(0, 50, 1));
        Config::setInstance(new Config([
            'uni' => 1,
            'Fleet_Cdr' => 30,
            'Defs_Cdr' => 0,
            'fleet_speed' => 2500,
            'moon_factor' => 0,
            'moon_chance' => 0,
            'debris_moon' => 0,
            'moduls' => $modules,
        ]), 1);

        $this->fake->achievement->users[1] = missionCombatUser(1);
        $this->fake->achievement->users[2] = missionCombatUser(2);
        $this->fake->planetRowsById[99] = missionCombatPlanet(99, 2);

        $GLOBALS['pricelist'][202]['cost'] = [901 => 3000, 902 => 1000];
        $GLOBALS['CombatCaps'][202] = ['attack' => 50, 'shield' => 10];
        $GLOBALS['CombatCaps'][401] = ['attack' => 80, 'shield' => 20, 'plunder' => 40000];
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_attack_runs_combat_against_planet_defenses(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 1,
            'fleet_array' => '202,100;',
            'fleet_amount' => 100,
            'fleet_target_owner' => 2,
        ]);

        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $this->assertNotEmpty($this->fake->achievement->messages);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_destruction_runs_combat_against_planet(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 9,
            'fleet_array' => '202,50;',
            'fleet_amount' => 50,
            'fleet_target_owner' => 2,
        ]);

        $mission = new MissionCaseDestruction($fleet);
        $mission->TargetEvent();

        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function test_mip_destroys_defenses_when_missiles_outnumber_interceptors(): void
    {
        $this->fake->planetRowsById[99]['interplanetary_missile'] = 0;
        $this->fake->planetRowsById[99]['rocket_launcher'] = 5;
        $this->fake->planetRowsById[99]['light_laser'] = 3;

        $fleet = missionFleetFixture([
            'fleet_mission' => 10,
            'fleet_amount' => 5,
            'fleet_target_obj' => 401,
            'fleet_array' => '503,5;',
        ]);

        $mission = new MissionCaseMIP($fleet);
        $mission->TargetEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
    }

    private function defineMissionModules(): void
    {
        if (!defined('MODULE_MISSION_ATTACK')) {
            define('MODULE_MISSION_ATTACK', 1);
        }
        if (!defined('MODULE_ACHIEVEMENTS')) {
            define('MODULE_ACHIEVEMENTS', 46);
        }
    }
}
