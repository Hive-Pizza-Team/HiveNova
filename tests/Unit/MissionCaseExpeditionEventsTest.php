<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseExpedition;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCaseExpeditionEventsTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        mt_srand(42);

        if (!defined('MODULE_ACHIEVEMENTS')) {
            define('MODULE_ACHIEVEMENTS', 46);
        }
        if (!defined('MAX_ATTACK_ROUNDS')) {
            define('MAX_ATTACK_ROUNDS', 6);
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
            'military_tech' => 5,
            'defence_tech' => 5,
            'shield_tech' => 5,
            'weapons_tech' => 5,
            'shielding_tech' => 5,
            'armour_tech' => 5,
        ];
        $this->fake->expeditionLogCount = 5;
        $this->fake->achievement->statPoints['total_points'] = 5_000_000;

        foreach ([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215] as $shipId) {
            if (!isset($GLOBALS['pricelist'][$shipId])) {
                $GLOBALS['pricelist'][$shipId] = [
                    'cost' => [901 => 1000, 902 => 500],
                    'capacity' => 5000,
                ];
                continue;
            }
            $cost = $GLOBALS['pricelist'][$shipId]['cost'] ?? [];
            $sum = (int) ($cost[901] ?? 0) + (int) ($cost[902] ?? 0) + (int) ($cost[903] ?? 0);
            if ($sum <= 0) {
                $GLOBALS['pricelist'][$shipId]['cost'] = [901 => 1000, 902 => 500, 903 => 0];
            }
        }

        foreach ([204, 205, 206, 207, 213, 215] as $shipId) {
            $GLOBALS['CombatCaps'][$shipId] = ['attack' => 100, 'shield' => 50];
        }
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_end_stay_long_hold_always_finds_resources(): void
    {
        $fleet = expeditionFleetLongHold(['fleet_array' => '202,20;']);
        $mission = new MissionCaseExpedition($fleet);
        $mission->EndStayEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertGreaterThan(
            0,
            $this->fleetResourceTotal($mission->_fleet),
            'Long hold should force resource expedition outcome'
        );
        $this->assertExpeditionReportMessage();
    }

    public function test_end_stay_finds_darkmatter(): void
    {
        $mission = $this->runEndStayUntil(function (MissionCaseExpedition $m): bool {
            return (int) ($m->_fleet['fleet_resource_darkmatter'] ?? 0) > 0;
        }, expeditionFleetLongHold([
            'fleet_end_stay' => TIMESTAMP + 3600,
            'fleet_start_time' => TIMESTAMP,
            'fleet_array' => '202,50;',
        ]));

        $this->assertGreaterThan(0, (int) $mission->_fleet['fleet_resource_darkmatter']);
        $this->assertExpeditionReportMessage();
    }

    public function test_end_stay_pirate_combat_sends_attack_report(): void
    {
        $mission = $this->runEndStayUntil(function (MissionCaseExpedition $m): bool {
            return $this->hasMessageType(3);
        }, expeditionFleetCombatReady());

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertGreaterThan(0, (int) $mission->_fleet['fleet_amount']);
        $this->assertTrue($this->hasMessageType(3), 'Combat branch should send attack report (type 3)');
    }

    public function test_end_stay_black_hole_kills_fleet(): void
    {
        $mission = $this->runEndStayUntil(function (MissionCaseExpedition $m): bool {
            return $m->kill === 1;
        }, expeditionFleetCombatReady([
            'fleet_array' => '202,5;',
            'fleet_amount' => 5,
        ]));

        $this->assertSame(1, $mission->kill);
        $this->assertTrue(
            $this->fleetWasDeleted(),
            'Black hole branch should delete the fleet'
        );
    }

    public function test_end_stay_adjusts_return_time(): void
    {
        $baseEndTime = TIMESTAMP + 5400;
        $mission = $this->runEndStayUntil(function (MissionCaseExpedition $m) use ($baseEndTime): bool {
            return isset($m->_upd['fleet_end_time'])
                && (int) $m->_upd['fleet_end_time'] !== $baseEndTime;
        }, expeditionFleetCombatReady([
            'fleet_end_time' => $baseEndTime,
        ]));

        $this->assertArrayHasKey('fleet_end_time', $mission->_upd);
        $this->assertNotSame($baseEndTime, (int) $mission->_upd['fleet_end_time']);
        $this->assertExpeditionReportMessage();
    }

    public function test_end_stay_heavy_depletion_can_block_resource_loot(): void
    {
        $this->fake->expeditionLogCount = 100;

        $blocked = false;
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $fleet = expeditionFleetLongHold(['fleet_id' => 100 + $attempt]);
            $mission = new MissionCaseExpedition($fleet);
            $mission->EndStayEvent();

            if ($this->fleetResourceTotal($mission->_fleet) === 0) {
                $blocked = true;
                break;
            }
        }

        $this->assertTrue($blocked, 'Depleted system should sometimes force empty resource outcome');
        $this->assertExpeditionReportMessage();
    }

    public function test_target_event_holds_fleet_at_expedition_position(): void
    {
        $fleet = missionFleetFixture(['fleet_mission' => 15]);
        $mission = new MissionCaseExpedition($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_HOLD, $mission->_fleet['fleet_mess']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_return_event_restores_fleet_and_notifies_owner(): void
    {
        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Home', 'id_owner' => 1];

        $fleet = missionFleetFixture([
            'fleet_mission' => 15,
            'fleet_array' => '202,5;',
            'fleet_resource_metal' => 500,
            'fleet_resource_crystal' => 200,
            'fleet_resource_darkmatter' => 50,
        ]);

        $mission = new MissionCaseExpedition($fleet);
        $mission->ReturnEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertTrue($this->hasMessageType(4), 'Return should send fleet-back message (type 4)');
        $this->assertTrue($this->fleetWasDeleted());
    }

    /**
     * @param callable(MissionCaseExpedition): bool $predicate
     */
    private function runEndStayUntil(callable $predicate, array $fleetBase, int $maxAttempts = 400): MissionCaseExpedition
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $fleet = array_merge($fleetBase, ['fleet_id' => 200 + $attempt]);
            $mission = new MissionCaseExpedition($fleet);
            $mission->EndStayEvent();

            if ($predicate($mission)) {
                return $mission;
            }
        }

        $this->fail('Expedition EndStayEvent did not reach expected branch within ' . $maxAttempts . ' attempts');
    }

    private function fleetResourceTotal(array $fleet): int
    {
        return (int) ($fleet['fleet_resource_metal'] ?? 0)
            + (int) ($fleet['fleet_resource_crystal'] ?? 0)
            + (int) ($fleet['fleet_resource_deuterium'] ?? 0);
    }

    private function hasMessageType(int $type): bool
    {
        foreach ($this->fake->achievement->messages as $message) {
            if ((int) ($message[':type'] ?? 0) === $type) {
                return true;
            }
        }

        return false;
    }

    private function fleetWasDeleted(): bool
    {
        foreach ($this->fake->fleetUpdates as $update) {
            if (!empty($update['delete'])) {
                return true;
            }
        }

        return false;
    }

    private function assertExpeditionReportMessage(): void
    {
        $this->assertTrue(
            $this->hasMessageType(15),
            'Expedition should send sys_expe_report message (type 15)'
        );
    }

}
