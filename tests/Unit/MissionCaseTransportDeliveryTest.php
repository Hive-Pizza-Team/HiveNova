<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseTransport;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCaseTransportDeliveryTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(new Config(['uni' => 1]), 1);
        transportMissionEnvironmentSetup();
        transportDatabaseFixture($this->fake);
    }

    protected function tearDown(): void
    {
        transportMissionEnvironmentTeardown();

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_transport_delivers_resources_to_own_planet(): void
    {
        $this->fake->planetRowsById[99] = ['id' => 99, 'name' => 'Colony', 'id_owner' => 1];

        $fleet = transportFleetSelf([
            'fleet_resource_metal' => 5000,
            'fleet_resource_crystal' => 2000,
            'fleet_resource_deuterium' => 800,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame('0', $mission->_fleet['fleet_resource_metal']);
        $this->assertSame('0', $mission->_fleet['fleet_resource_crystal']);
        $this->assertSame('0', $mission->_fleet['fleet_resource_deuterium']);
        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame(1, $this->fake->achievement->messages[0][':userId']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
    }

    public function test_transport_notifies_both_players_on_foreign_delivery(): void
    {
        $fleet = transportFleetForeign([
            'fleet_resource_metal' => 3000,
            'fleet_resource_crystal' => 1000,
            'fleet_resource_deuterium' => 500,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertCount(2, $this->fake->achievement->messages);

        $recipients = array_map(
            static fn (array $msg): int => (int) $msg[':userId'],
            $this->fake->achievement->messages
        );
        $this->assertContains(1, $recipients);
        $this->assertContains(2, $recipients);
    }

    public function test_transport_clears_cargo_after_store_goods(): void
    {
        $fleet = transportFleetForeign([
            'fleet_resource_metal' => 1200,
            'fleet_resource_crystal' => 600,
            'fleet_resource_deuterium' => 300,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame('0', $mission->_fleet['fleet_resource_metal']);
        $this->assertSame('0', $mission->_fleet['fleet_resource_crystal']);
        $this->assertSame('0', $mission->_fleet['fleet_resource_deuterium']);

        $saveUpdate = $this->findFleetSaveUpdate();
        $this->assertNotNull($saveUpdate);
        $this->assertSame(FLEET_RETURN, $saveUpdate['params'][':fleet_mess']);
    }

    public function test_transport_skips_delivery_when_target_destroyed(): void
    {
        unset($this->fake->planetRowsById[99]);

        $fleet = transportFleetForeign([
            'fleet_resource_metal' => 1000,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertSame(1000, $mission->_fleet['fleet_resource_metal']);
        $this->assertEmpty($this->fake->achievement->messages);
    }

    public function test_transport_delivers_zero_cargo_without_error(): void
    {
        $fleet = transportFleetSelf([
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
            'fleet_resource_deuterium' => 0,
        ]);
        $this->fake->planetRowsById[99] = ['id' => 99, 'name' => 'Colony', 'id_owner' => 1];

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame('0', $mission->_fleet['fleet_resource_metal']);
    }

    /**
     * @return array{sql: string, params: array<string, mixed>}|null
     */
    private function findFleetSaveUpdate(): ?array
    {
        foreach ($this->fake->fleetUpdates as $update) {
            if (str_contains($update['sql'], 'UPDATE %%FLEETS%%')
                && str_contains($update['sql'], 'fleet_mess')) {
                return $update;
            }
        }

        return null;
    }
}
