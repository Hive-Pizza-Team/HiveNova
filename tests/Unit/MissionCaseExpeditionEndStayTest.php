<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseExpedition;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCaseExpeditionEndStayTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function setUp(): void
    {
        if (!defined('MODULE_ACHIEVEMENTS')) {
            define('MODULE_ACHIEVEMENTS', 46);
        }

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $modules = array_fill(0, 50, '0');
        $modules[46] = '0';
        Config::setInstance(new Config([
            'uni' => 1,
            'Fleet_Cdr' => 0.3,
            'Defs_Cdr' => 0.0,
            'moduls' => implode(';', $modules),
        ]), 1);

        $this->fake->achievement->users[1] = [
            'id' => 1,
            'lang' => 'en',
            'universe' => 1,
            'military_tech' => 0,
            'defence_tech' => 0,
            'shield_tech' => 0,
        ];
        $this->fake->expeditionLogCount = 5;
        $this->fake->achievement->statPoints['total_points'] = 100000;

        foreach ([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215] as $shipId) {
            if (!isset($GLOBALS['pricelist'][$shipId])) {
                $GLOBALS['pricelist'][$shipId] = [
                    'cost' => [901 => 1000, 902 => 500],
                    'capacity' => 100,
                ];
            }
        }
    }

    private FakeDatabase $fake;

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_end_stay_finds_resources_without_combat(): void
    {
        mt_srand(2000);

        $fleet = expeditionFleetLongHold(['fleet_array' => '202,10;']);

        $mission = new MissionCaseExpedition($fleet);
        $mission->EndStayEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertNotEmpty($this->fake->achievement->messages);
    }
}
