<?php

use HiveNova\Core\Config;
use HiveNova\Core\GalaxyRows;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class GalaxyRowsTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->defineModules();
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        $this->bootstrapGlobals();
        $this->bootstrapConfig();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['USER'], $GLOBALS['PLANET'], $GLOBALS['LNG']);

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_set_galaxy_and_system_return_self(): void
    {
        $rows = new GalaxyRows();

        $this->assertSame($rows, $rows->setGalaxy(2));
        $this->assertSame($rows, $rows->setSystem(42));
    }

    public function test_get_galaxy_data_returns_empty_when_no_rows(): void
    {
        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertNull($data);
    }

    public function test_get_galaxy_data_marks_destroyed_planets(): void
    {
        $this->seedGalaxyRow(['planet' => 3, 'destruyed' => TIMESTAMP]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertFalse($data[3]);
    }

    public function test_get_galaxy_data_own_planet_has_no_action_buttons(): void
    {
        $this->seedGalaxyRow([
            'planet' => 4,
            'id_owner' => 1,
            'userid' => 1,
            'username' => 'me',
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertTrue($data[4]['ownPlanet']);
        $this->assertFalse($data[4]['action']);
        $this->assertTrue($data[4]['missions'][4]);
        $this->assertFalse($data[4]['missions'][1]);
    }

    public function test_get_galaxy_data_other_player_has_action_buttons(): void
    {
        $this->seedGalaxyRow([
            'planet' => 6,
            'id_owner' => 2,
            'userid' => 2,
            'username' => 'rival',
            'buddy' => 0,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertFalse($data[6]['ownPlanet']);
        $this->assertIsArray($data[6]['action']);
        $this->assertTrue($data[6]['action']['esp']);
        $this->assertTrue($data[6]['action']['message']);
        $this->assertTrue($data[6]['action']['buddy']);
    }

    public function test_get_galaxy_data_escapes_player_and_planet_names(): void
    {
        $this->seedGalaxyRow([
            'planet' => 7,
            'name' => '<b>Colony</b>',
            'username' => '<script>x</script>',
            'userid' => 2,
            'id_owner' => 2,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertSame('&lt;b&gt;Colony&lt;/b&gt;', $data[7]['planet']['name']);
        $this->assertSame('&lt;script&gt;x&lt;/script&gt;', $data[7]['user']['username']);
    }

    public function test_get_galaxy_data_with_alliance_and_diplomacy(): void
    {
        $this->seedGalaxyRow([
            'planet' => 8,
            'id_owner' => 2,
            'userid' => 2,
            'allyid' => 10,
            'ally_id' => 10,
            'ally_name' => 'Hive Pact',
            'ally_tag' => 'HIVE',
            'ally_web' => 'https://example.test',
            'ally_members' => 3,
            'ally_rank' => 2,
            'diploLevel' => 1,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertIsArray($data[8]['alliance']);
        $this->assertSame(10, $data[8]['alliance']['id']);
        $this->assertSame('Hive Pact', $data[8]['alliance']['name']);
        $this->assertSame(['friend'], $data[8]['alliance']['class']);
        $this->assertSame('3 Members', $data[8]['alliance']['member']);
    }

    public function test_get_galaxy_data_own_alliance_marks_member_class(): void
    {
        $GLOBALS['USER']['ally_id'] = 10;
        $this->seedGalaxyRow([
            'planet' => 9,
            'id_owner' => 2,
            'userid' => 2,
            'allyid' => 10,
            'ally_id' => 10,
            'ally_name' => 'Hive Pact',
            'ally_tag' => 'HIVE',
            'ally_web' => '',
            'ally_members' => 1,
            'ally_rank' => 1,
            'diploLevel' => 5,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertSame(['member'], $data[9]['alliance']['class']);
        $this->assertSame('1 Member', $data[9]['alliance']['member']);
    }

    public function test_get_galaxy_data_without_alliance(): void
    {
        $this->seedGalaxyRow(['planet' => 10, 'allyid' => null]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertFalse($data[10]['alliance']);
    }

    public function test_get_galaxy_data_with_debris_and_moon(): void
    {
        $this->seedGalaxyRow([
            'planet' => 11,
            'der_metal' => 1200,
            'der_crystal' => 800,
            'm_id' => 501,
            'm_name' => 'Luna',
            'm_temp_min' => -50,
            'm_diameter' => 4200,
            'm_last_update' => TIMESTAMP - 120,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertSame(['metal' => 1200, 'crystal' => 800], $data[11]['debris']);
        $this->assertSame(501, $data[11]['moon']['id']);
        $this->assertSame('Luna', $data[11]['moon']['name']);
    }

    public function test_get_galaxy_data_without_debris_or_moon(): void
    {
        $this->seedGalaxyRow([
            'planet' => 12,
            'der_metal' => 0,
            'der_crystal' => 0,
            'm_id' => null,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertFalse($data[12]['debris']);
        $this->assertFalse($data[12]['moon']);
    }

    public function test_get_galaxy_data_recent_activity_label(): void
    {
        $this->seedGalaxyRow([
            'planet' => 2,
            'last_update' => TIMESTAMP - 300,
            'm_last_update' => TIMESTAMP - 600,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertSame('(*)', $data[2]['lastActivity']);
    }

    public function test_get_galaxy_data_inactive_activity_label(): void
    {
        $this->seedGalaxyRow([
            'planet' => 2,
            'last_update' => TIMESTAMP - 1800,
            'm_last_update' => 0,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertSame('(30 min)', $data[2]['lastActivity']);
    }

    public function test_get_galaxy_data_long_inactive_activity_is_empty(): void
    {
        $this->seedGalaxyRow([
            'planet' => 2,
            'last_update' => TIMESTAMP - 7200,
            'm_last_update' => 0,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(5)->getGalaxyData();

        $this->assertSame('', $data[2]['lastActivity']);
    }

    public function test_get_galaxy_data_missile_mission_within_range(): void
    {
        $GLOBALS['USER']['impulse_motor_tech'] = 3;
        $GLOBALS['PLANET']['interplanetary_missile'] = 5;
        $this->seedGalaxyRow([
            'planet' => 13,
            'id_owner' => 2,
            'galaxy' => 1,
            'system' => 7,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(7)->getGalaxyData();

        $this->assertTrue($data[13]['missions'][10]);
        $this->assertTrue($data[13]['action']['missle']);
    }

    public function test_get_galaxy_data_missile_mission_out_of_range(): void
    {
        $GLOBALS['USER']['impulse_motor_tech'] = 1;
        $GLOBALS['PLANET']['interplanetary_missile'] = 5;
        $this->seedGalaxyRow([
            'planet' => 14,
            'id_owner' => 2,
            'galaxy' => 1,
            'system' => 20,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(20)->getGalaxyData();

        $this->assertFalse($data[14]['missions'][10]);
    }

    public function test_get_galaxy_data_phalanx_available_in_range(): void
    {
        $GLOBALS['PLANET']['sensor_phalanx'] = 2;
        $GLOBALS['PLANET']['deuterium'] = PHALANX_DEUTERIUM;
        $this->seedGalaxyRow([
            'planet' => 15,
            'galaxy' => 1,
            'system' => 6,
        ]);

        $data = $this->galaxyRows()->setGalaxy(1)->setSystem(6)->getGalaxyData();

        $this->assertTrue($data[15]['planet']['phalanx']);
    }

    public function test_get_system_control_data_returns_controlling_alliance(): void
    {
        $this->fake->systemControlAlliances = [['ally_name' => 'Dominators']];

        $result = $this->galaxyRows()->getSystemControlData(1, 5);

        $this->assertSame('Dominators', $result);
    }

    public function test_get_system_control_data_returns_dash_when_ambiguous(): void
    {
        $this->fake->systemControlAlliances = [
            ['ally_name' => 'Alpha'],
            ['ally_name' => 'Beta'],
        ];

        $result = $this->galaxyRows()->getSystemControlData(1, 5);

        $this->assertSame('-', $result);
    }

    public function test_in_missile_range_returns_false_for_other_galaxy(): void
    {
        $rows = $this->galaxyRows();
        $this->setGalaxyRow($rows, [
            'galaxy' => 2,
            'system' => 5,
        ]);

        $method = new ReflectionMethod(GalaxyRows::class, 'inMissileRange');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($rows));
    }

    public function test_in_missile_range_returns_true_within_system_band(): void
    {
        $GLOBALS['USER']['impulse_motor_tech'] = 3;
        $rows = $this->galaxyRows();
        $this->setGalaxyRow($rows, [
            'galaxy' => 1,
            'system' => 7,
        ]);

        $method = new ReflectionMethod(GalaxyRows::class, 'inMissileRange');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($rows));
    }

    private function galaxyRows(): GalaxyRows
    {
        return new GalaxyRows();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedGalaxyRow(array $overrides = []): void
    {
        $this->fake->galaxyRows[] = $this->galaxyRow($overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function galaxyRow(array $overrides = []): array
    {
        return array_merge([
            'galaxy' => 1,
            'system' => 5,
            'planet' => 1,
            'id' => 100,
            'id_owner' => 2,
            'name' => 'Homeworld',
            'image' => 'normaltempplanet01',
            'last_update' => TIMESTAMP - 60,
            'diameter' => 12800,
            'temp_min' => 10,
            'destruyed' => 0,
            'der_metal' => 0,
            'der_crystal' => 0,
            'id_luna' => null,
            'userid' => 2,
            'ally_id' => 0,
            'username' => 'player2',
            'onlinetime' => TIMESTAMP,
            'urlaubs_modus' => 0,
            'banaday' => 0,
            'm_id' => null,
            'm_diameter' => null,
            'm_name' => null,
            'm_temp_min' => null,
            'm_last_update' => null,
            'total_points' => 50000,
            'total_rank' => 12,
            'allyid' => null,
            'ally_tag' => null,
            'ally_web' => null,
            'ally_members' => null,
            'ally_name' => null,
            'ally_rank' => null,
            'buddy' => 1,
            'diploLevel' => null,
            'universe' => 1,
            'planet_type' => 1,
        ], $overrides);
    }

    private function setGalaxyRow(GalaxyRows $rows, array $row): void
    {
        $prop = new ReflectionProperty(GalaxyRows::class, 'galaxyRow');
        $prop->setAccessible(true);
        $prop->setValue($rows, $row);
    }

    private function bootstrapGlobals(): void
    {
        $GLOBALS['resource'][42] = 'sensor_phalanx';
        $GLOBALS['resource'][117] = 'impulse_motor_tech';
        $GLOBALS['resource'][214] = 'deathstar';
        $GLOBALS['resource'][503] = 'interplanetary_missile';
        $GLOBALS['resource'][903] = 'deuterium';

        $GLOBALS['USER'] = [
            'id' => 1,
            'ally_id' => 0,
            'settings_esp' => 1,
            'settings_wri' => 1,
            'settings_bud' => 1,
            'settings_mis' => 1,
            'impulse_motor_tech' => 0,
            'onlinetime' => TIMESTAMP,
            'banaday' => 0,
            'total_points' => 100000,
            'universe' => 1,
        ];

        $GLOBALS['PLANET'] = [
            'galaxy' => 1,
            'system' => 5,
            'sensor_phalanx' => 0,
            'deuterium' => 0,
            'deathstar' => 0,
            'interplanetary_missile' => 0,
        ];

        $GLOBALS['LNG'] = [
            'gl_activity' => '(*)',
            'gl_activity_inactive' => '(%d min)',
            'gl_member' => '%d Members',
            'gl_member_add' => '%d Member',
            'gl_in_the_rank' => 'Player %s in pos. %d',
        ];
    }

    private function bootstrapConfig(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => implode(';', array_fill(0, 50, 1)),
            'noobprotection' => 0,
            'noobprotectiontime' => 5000,
            'noobprotectionmulti' => 5,
        ]), 1);
    }

    private function defineModules(): void
    {
        $modules = [
            'MODULE_MISSION_ATTACK' => 1,
            'MODULE_MISSION_TRANSPORT' => 34,
            'MODULE_MISSION_STATION' => 36,
            'MODULE_MISSION_HOLD' => 33,
            'MODULE_MISSION_SPY' => 24,
            'MODULE_MISSION_RECYCLE' => 32,
            'MODULE_MISSION_DESTROY' => 29,
            'MODULE_BUDDYLIST' => 6,
            'MODULE_MESSAGES' => 16,
            'MODULE_PHALANX' => 19,
            'MODULE_MISSILEATTACK' => 40,
            'MODULE_STATISTICS' => 25,
            'PHALANX_DEUTERIUM' => 5000,
        ];

        foreach ($modules as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
