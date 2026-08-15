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

    private MissionCombatFakeDatabase $fake;

    protected function setUp(): void
    {
        missionCombatEnvironmentSetup();

        $this->fake = new MissionCombatFakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(missionCombatConfig(), 1);
        missionCombatSeedStandardTargets($this->fake);
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
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
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
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
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

    public function test_attack_return_event_restores_fleet_and_notifies_owner(): void
    {
        $this->fake->planetRowsById[99]['name'] = 'Target World';

        $fleet = missionFleetFixture([
            'fleet_mission' => 1,
            'fleet_array' => '202,5;',
            'fleet_resource_metal' => 500,
        ]);

        $mission = new MissionCaseAttack($fleet);
        $mission->ReturnEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($this->fake->fleetUpdates);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }

    public function test_attack_end_stay_event_is_noop(): void
    {
        $mission = new MissionCaseAttack(missionFleetFixture(['fleet_mission' => 1]));

        $mission->EndStayEvent();

        $this->assertEmpty($this->fake->achievement->messages);
        $this->assertEmpty($this->fake->fleetUpdates);
    }

    public function test_attack_with_acs_runs_combat_for_group_fleets(): void
    {
        $this->fake->fleetRowsById[2] = missionCombatAcsMemberFleet(2, 7, ['fleet_owner' => 3]);
        $this->fake->achievement->users[3] = missionCombatUser(3);

        $fleet = missionCombatWinningAttackFleet(['fleet_group' => 7]);

        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $aksDeletes = array_filter(
            $this->fake->fleetUpdates,
            static fn (array $update): bool => str_contains($update['sql'], '%%AKS%%')
                && !empty($update['delete'])
        );

        $this->assertNotEmpty($aksDeletes);
        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_attack_on_undefended_planet_still_generates_report(): void
    {
        $this->fake->planetRowsById[99]['rocket_launcher'] = 0;
        $this->fake->planetRowsById[99]['light_fighter'] = 0;

        $fleet = missionCombatWinningAttackFleet(['fleet_array' => '202,20;', 'fleet_amount' => 20]);

        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
        $this->assertSame('Win', missionCombatReportClass($this->fake, 1));
    }

    public function test_attack_attacker_wins_and_plunders_resources(): void
    {
        $this->fake->planetRowsById[99]['rocket_launcher'] = 0;
        $this->fake->planetRowsById[99]['metal'] = 50000;
        $this->fake->planetRowsById[99]['crystal'] = 25000;
        $this->fake->planetRowsById[99]['deuterium'] = 10000;

        $fleet = missionCombatWinningAttackFleet();

        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $ownerMessages = array_filter(
            $this->fake->achievement->messages,
            static fn (array $message): bool => (int) ($message[':userId'] ?? 0) === 1
        );
        $report = (string) reset($ownerMessages)[':text'];

        $this->assertStringContainsString('reportSteal element901', $report);
        $this->assertSame('Win', missionCombatReportClass($this->fake, 1));
    }

    public function test_attack_accumulates_existing_debris_field(): void
    {
        $this->fake->planetRowsById[99]['der_metal'] = 7000;
        $this->fake->planetRowsById[99]['der_crystal'] = 3000;

        $fleet = missionFleetFixture([
            'fleet_mission' => 1,
            'fleet_array' => '202,100;',
            'fleet_amount' => 100,
        ]);

        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $ownerMessages = array_filter(
            $this->fake->achievement->messages,
            static fn (array $message): bool => (int) ($message[':userId'] ?? 0) === 1
        );
        $report = (string) reset($ownerMessages)[':text'];

        $this->assertStringContainsString('reportDebris element901', $report);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_attack_on_moon_merges_parent_debris(): void
    {
        missionCombatLinkMoonParent(
            $this->fake,
            100,
            50,
            2,
            ['rocket_launcher' => 0],
            ['der_metal' => 9000, 'der_crystal' => 4000]
        );

        $fleet = missionCombatWinningAttackFleet([
            'fleet_end_id' => 100,
            'fleet_end_type' => 3,
        ]);

        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $ownerMessages = array_filter(
            $this->fake->achievement->messages,
            static fn (array $message): bool => (int) ($message[':userId'] ?? 0) === 1
        );
        $report = (string) reset($ownerMessages)[':text'];

        $this->assertStringContainsString('reportDebris element901', $report);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_attack_skips_moon_creation_when_chance_is_zero(): void
    {
        Config::setInstance(missionCombatConfig([
            'moon_factor' => 0,
            'moon_chance' => 0,
        ]), 1);

        $this->fake->planetRowsById[99]['rocket_launcher'] = 0;

        $fleet = missionCombatWinningAttackFleet(['fleet_array' => '202,100;', 'fleet_amount' => 100]);

        mt_srand(1);
        $mission = new MissionCaseAttack($fleet);
        $mission->TargetEvent();

        $this->assertEmpty($this->fake->planetInserts);
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
    }

    public function test_destruction_end_stay_event_is_noop(): void
    {
        $mission = new MissionCaseDestruction(missionFleetFixture(['fleet_mission' => 9]));

        $mission->EndStayEvent();

        $this->assertEmpty($this->fake->achievement->messages);
        $this->assertEmpty($this->fake->fleetUpdates);
    }
}
