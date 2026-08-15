<?php

use HiveNova\Core\Database;
use HiveNova\Core\FlyingFleetsTable;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FlyingFleetsTableTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        $this->bootstrapGlobals();
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        unset($GLOBALS['LNG'], $GLOBALS['USER']);
        parent::tearDown();
    }

    public function test_set_missions_filters_non_numeric_values(): void
    {
        $table = new FlyingFleetsTable();
        $table->setMissions('1,foo,3,bar,5');

        $prop = new ReflectionProperty(FlyingFleetsTable::class, 'missions');
        $prop->setAccessible(true);

        $this->assertSame('1,3,5', $prop->getValue($table));
    }

    public function test_render_table_empty_when_no_fleets(): void
    {
        $table = new FlyingFleetsTable();
        $table->setUser(1);

        $this->assertSame([], $table->renderTable());
    }

    public function test_render_table_includes_own_outward_fleet(): void
    {
        $this->seedUsersAndPlanets();
        $this->fake->fleetRowsById[1] = $this->fleetRow([
            'fleet_id' => 1,
            'fleet_owner' => 1,
            'fleet_target_owner' => 2,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
            'fleet_start_time' => TIMESTAMP + 3600,
            'fleet_end_time' => TIMESTAMP + 7200,
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        $events = $table->renderTable();

        $this->assertCount(2, $events);
        $outwardKey = (TIMESTAMP + 3600) . '1';
        $returnKey = (TIMESTAMP + 7200) . '1';
        $this->assertArrayHasKey($outwardKey, $events);
        $this->assertArrayHasKey($returnKey, $events);
        $this->assertStringContainsString('flight', $events[$outwardKey]['text']);
        $this->assertStringContainsString('return', $events[$returnKey]['text']);
        $this->assertSame(3600, $events[$outwardKey]['resttime']);
    }

    public function test_render_table_filters_by_mission_list(): void
    {
        $this->seedUsersAndPlanets();
        $this->fake->fleetRowsById[1] = $this->fleetRow([
            'fleet_id' => 1,
            'fleet_owner' => 1,
            'fleet_mission' => 1,
            'fleet_start_time' => TIMESTAMP + 3600,
            'fleet_end_time' => TIMESTAMP + 7200,
        ]);
        $this->fake->fleetRowsById[2] = $this->fleetRow([
            'fleet_id' => 2,
            'fleet_owner' => 1,
            'fleet_mission' => 3,
            'fleet_start_time' => TIMESTAMP + 1800,
            'fleet_end_time' => TIMESTAMP + 5400,
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        $table->setMissions('3');
        $events = $table->renderTable();

        $this->assertArrayHasKey((TIMESTAMP + 1800) . '2', $events);
        $this->assertArrayNotHasKey((TIMESTAMP + 3600) . '1', $events);
    }

    public function test_render_table_includes_hold_for_mission_five(): void
    {
        $this->seedUsersAndPlanets();
        $this->fake->fleetRowsById[1] = $this->fleetRow([
            'fleet_id' => 1,
            'fleet_owner' => 1,
            'fleet_mission' => 5,
            'fleet_start_time' => TIMESTAMP - 3600,
            'fleet_end_stay' => TIMESTAMP + 2400,
            'fleet_end_time' => TIMESTAMP + 7200,
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        $events = $table->renderTable();

        $holdKey = (TIMESTAMP + 2400) . '1';
        $this->assertArrayHasKey($holdKey, $events);
        $this->assertStringContainsString('holding', $events[$holdKey]['text']);
    }

    public function test_render_table_acs_group_aggregates_member_fleets(): void
    {
        $this->seedUsersAndPlanets();
        $this->fake->fleetRowsById[1] = $this->fleetRow([
            'fleet_id' => 1,
            'fleet_owner' => 1,
            'fleet_group' => 9,
            'fleet_mission' => 1,
            'fleet_start_time' => TIMESTAMP + 5000,
            'fleet_end_time' => TIMESTAMP + 9000,
        ]);
        $this->fake->fleetRowsById[2] = $this->fleetRow([
            'fleet_id' => 2,
            'fleet_owner' => 1,
            'fleet_group' => 9,
            'fleet_mission' => 1,
            'fleet_array' => '203,5;',
            'fleet_start_time' => TIMESTAMP + 5000,
            'fleet_end_time' => TIMESTAMP + 9000,
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        $events = $table->renderTable();

        $acsKey = (TIMESTAMP + 5000) . '1';
        $this->assertArrayHasKey($acsKey, $events);
        $this->assertStringContainsString('<br><br>', $events[$acsKey]['text']);
        $this->assertStringContainsString('Small Cargo', $events[$acsKey]['text']);
        $this->assertStringContainsString('Large Cargo', $events[$acsKey]['text']);
    }

    public function test_render_table_phalanx_mode_shows_incoming_hold(): void
    {
        $this->seedUsersAndPlanets();
        $this->fake->fleetRowsById[1] = $this->fleetRow([
            'fleet_id' => 1,
            'fleet_owner' => 2,
            'fleet_target_owner' => 1,
            'fleet_mission' => 5,
            'fleet_end_id' => 10,
            'fleet_start_time' => TIMESTAMP - 7200,
            'fleet_end_stay' => TIMESTAMP + 1800,
            'fleet_end_time' => TIMESTAMP + 5400,
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        $table->setPlanet(10);
        $table->setPhalanxMode();
        $events = $table->renderTable();

        $holdKey = (TIMESTAMP + 1800) . '1';
        $this->assertArrayHasKey($holdKey, $events);
        $this->assertStringContainsString('holding', $events[$holdKey]['text']);
    }

    public function test_get_event_data_own_attack_outward(): void
    {
        $this->seedUsersAndPlanets();
        $row = $this->fleetRow([
            'fleet_owner' => 1,
            'fleet_target_owner' => 2,
            'fleet_mission' => 1,
            'own_username' => 'attacker',
            'target_username' => 'defender',
            'own_planetname' => 'Homeworld',
            'target_planetname' => 'Target',
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        [$rest, $text, $time] = $table->getEventData($row, FLEET_OUTWARD);

        $this->assertSame(TIMESTAMP + 3600, $time);
        $this->assertSame(3600, $rest);
        $this->assertStringContainsString('flight', $text);
        $this->assertStringContainsString('ownattack', $text);
        $this->assertStringContainsString('Homeworld', $text);
    }

    public function test_get_event_data_incoming_transport_good(): void
    {
        $this->seedUsersAndPlanets();
        $row = $this->fleetRow([
            'fleet_owner' => 2,
            'fleet_target_owner' => 1,
            'fleet_mission' => 3,
            'fleet_resource_metal' => 1000,
            'own_username' => 'trader',
            'target_username' => 'receiver',
            'own_planetname' => 'Trade Hub',
            'target_planetname' => 'Colony',
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        [$rest, $text, $time] = $table->getEventData($row, FLEET_OUTWARD);

        $this->assertStringContainsString('trader', $text);
        $this->assertStringContainsString('transport', $text);
        $this->assertStringContainsString('data-tooltip-content', $text);
    }

    public function test_get_event_data_hides_ship_details_without_spy_tech(): void
    {
        $GLOBALS['USER'][$GLOBALS['resource'][106]] = 0;
        $this->seedUsersAndPlanets();
        $row = $this->fleetRow([
            'fleet_owner' => 2,
            'fleet_target_owner' => 1,
            'fleet_mission' => 1,
            'own_username' => 'raider',
            'target_username' => 'victim',
            'own_planetname' => 'Raid Base',
            'target_planetname' => 'Homeworld',
        ]);

        $table = new FlyingFleetsTable();
        $table->setUser(1);
        [, $text] = $table->getEventData($row, FLEET_OUTWARD);

        $this->assertStringContainsString('No fleet data', $text);
    }

    private function bootstrapGlobals(): void
    {
        $GLOBALS['resource'][106] = $GLOBALS['resource'][106] ?? 'spy_tech';
        $GLOBALS['resource'][202] = $GLOBALS['resource'][202] ?? 'light_fighter';
        $GLOBALS['resource'][203] = $GLOBALS['resource'][203] ?? 'large_cargo';

        $GLOBALS['USER'] = [
            'id' => 1,
            'spy_tech' => 8,
        ];

        $GLOBALS['LNG'] = [
            'ov_fleet' => 'Fleet',
            'cff_acs_fleet' => 'ACS Fleet',
            'PM' => 'PM',
            'cff_aproaching' => 'Approaching ',
            'cff_ships' => ' ships',
            'cff_no_fleet_data' => 'No fleet data',
            'type_planet_1' => 'Planet',
            'type_mission_1' => 'Attack',
            'type_mission_3' => 'Transport',
            'type_mission_5' => 'Hold',
            'cff_mission_own_0' => 'Own outward %s %s %s %s %s %s %s %s',
            'cff_mission_own_1' => 'Own return %s %s %s %s %s %s %s %s',
            'cff_mission_own_2' => 'Own hold %s %s %s %s %s %s %s %s',
            'cff_mission_target_good' => 'Incoming good %s from %s %s %s %s %s %s %s %s',
            'cff_mission_target_bad' => 'Incoming bad %s from %s %s %s %s %s %s %s %s',
            'cff_mission_target_stay' => 'Incoming stay %s from %s %s %s %s %s %s %s %s',
            'tech' => [
                900 => 'Resources',
                901 => 'Metal',
                902 => 'Crystal',
                903 => 'Deuterium',
                921 => 'Dark Matter',
                202 => 'Small Cargo',
                203 => 'Large Cargo',
            ],
        ];
    }

    private function seedUsersAndPlanets(): void
    {
        $this->fake->achievement->users[1] = ['id' => 1, 'username' => 'player1'];
        $this->fake->achievement->users[2] = ['id' => 2, 'username' => 'player2'];
        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Homeworld'];
        $this->fake->planetRowsById[20] = ['id' => 20, 'name' => 'Colony'];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fleetRow(array $overrides = []): array
    {
        return array_merge([
            'fleet_id' => 1,
            'fleet_owner' => 1,
            'fleet_target_owner' => 2,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
            'fleet_group' => 0,
            'fleet_amount' => 10,
            'fleet_start_id' => 10,
            'fleet_end_id' => 20,
            'fleet_start_galaxy' => 1,
            'fleet_start_system' => 1,
            'fleet_start_planet' => 3,
            'fleet_start_type' => 1,
            'fleet_end_galaxy' => 1,
            'fleet_end_system' => 2,
            'fleet_end_planet' => 5,
            'fleet_end_type' => 1,
            'fleet_start_time' => TIMESTAMP + 3600,
            'fleet_end_stay' => TIMESTAMP + 3600,
            'fleet_end_time' => TIMESTAMP + 7200,
            'fleet_array' => '202,10;',
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
            'fleet_resource_deuterium' => 0,
            'fleet_resource_darkmatter' => 0,
        ], $overrides);
    }
}
