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
            'Fleet_Cdr' => 30,
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
        $this->fake->planetRowsById[10] = missionCombatPlanet(10, 1, ['name' => 'Spy Origin']);
        $this->fake->planetRowsById[99] = missionCombatPlanet(99, 2, [
            'name' => 'Spy Base',
            'rocket_launcher' => 5,
            'light_fighter' => 3,
            'metal_mine' => 12,
        ]);

        $GLOBALS['reslist']['resstype'] = [
            1 => [901, 902, 903],
            2 => [911],
        ];
        $GLOBALS['reslist']['bonus'] = $GLOBALS['reslist']['bonus'] ?? [];
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_spy_target_event_sends_report_and_returns_fleet_when_undetected(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_target_owner' => 2,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(1);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(0, $mission->kill);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_spy_target_event_kills_fleet_when_detected(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_target_owner' => 2,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(13);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($this->fake->fleetUpdates);
        $deleted = array_filter(
            $this->fake->fleetUpdates,
            static fn (array $update): bool => !empty($update['delete'])
        );
        $this->assertNotEmpty($deleted);
    }

    public function test_spy_target_event_includes_moon_text_when_launched_from_moon(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_start_type' => 3,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(1);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $targetMessages = array_filter(
            $this->fake->achievement->messages,
            static fn (array $msg): bool => (int) ($msg[':userId'] ?? 0) === 2
        );
        $this->assertNotEmpty($targetMessages);
        $body = (string) reset($targetMessages)[':text'];
        $this->assertStringContainsString('(Moon)', $body);
    }

    public function test_spy_target_event_merges_stay_fleets_into_planet_ships(): void
    {
        $this->fake->stayFleetsAtPlanet[] = [
            'fleet_end_id' => 99,
            'fleet_mission' => 5,
            'fleet_start_time' => TIMESTAMP - 3600,
            'fleet_end_stay' => TIMESTAMP + 3600,
            'fleet_array' => '202,7;',
        ];

        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(1);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
    }

    public function test_spy_target_event_filters_zero_values_when_compact_mode_enabled(): void
    {
        $this->fake->achievement->users[1]['spyMessagesMode'] = 1;
        $this->fake->planetRowsById[99]['rocket_launcher'] = 0;
        $this->fake->planetRowsById[99]['light_fighter'] = 0;

        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(1);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $ownerMessages = array_filter(
            $this->fake->achievement->messages,
            static fn (array $msg): bool => (int) ($msg[':userId'] ?? 0) === 1
        );
        $this->assertNotEmpty($ownerMessages);
        $report = (string) reset($ownerMessages)[':text'];
        $this->assertStringNotContainsString('rocket_launcher', $report);
    }

    public function test_spy_target_event_limits_intel_when_probe_count_is_low(): void
    {
        $this->fake->achievement->users[1]['spy_tech'] = 1;
        $this->fake->achievement->users[2]['spy_tech'] = 10;

        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 50,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(1);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $this->assertGreaterThanOrEqual(2, count($this->fake->achievement->messages));
    }

    public function test_spy_target_event_updates_moon_debris_when_detected_on_moon(): void
    {
        $this->fake->planetRowsById[99]['planet_type'] = 3;
        $this->fake->planetRowsById[99]['id_luna'] = 99;

        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_amount' => 1000,
            'fleet_end_type' => 3,
            'fleet_array' => '210,1;',
        ]);

        mt_srand(13);
        $mission = new MissionCaseSpy($fleet);
        $mission->TargetEvent();

        $this->assertSame(1, $mission->kill);
        $deleted = array_filter(
            $this->fake->fleetUpdates,
            static fn (array $update): bool => !empty($update['delete'])
        );
        $this->assertNotEmpty($deleted);
    }

    public function test_spy_end_stay_event_is_noop(): void
    {
        $fleet = missionFleetFixture(['fleet_mission' => 6]);
        $mission = new MissionCaseSpy($fleet);

        $mission->EndStayEvent();

        $this->assertEmpty($this->fake->achievement->messages);
        $this->assertEmpty($this->fake->fleetUpdates);
    }

    public function test_spy_return_event_restores_fleet_to_origin(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 6,
            'fleet_array' => '202,5;',
            'fleet_resource_metal' => 250,
        ]);

        $mission = new MissionCaseSpy($fleet);
        $mission->ReturnEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }
}
