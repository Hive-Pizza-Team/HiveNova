<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseTransport;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

class MissionCaseTransportTest extends TestCase
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

    public function test_transport_end_stay_event_is_noop(): void
    {
        $fleet = transportFleetFixture();
        $mission = new MissionCaseTransport($fleet);

        $mission->EndStayEvent();

        $this->assertSame(FLEET_OUTWARD, $mission->_fleet['fleet_mess']);
        $this->assertEmpty($this->fake->achievement->messages);
        $this->assertEmpty($this->fake->fleetUpdates);
    }

    public function test_transport_return_event_sends_message_and_restores_fleet(): void
    {
        $fleet = transportFleetFixture([
            'fleet_mess' => FLEET_RETURN,
            'fleet_array' => '202,3;',
            'fleet_resource_metal' => 100,
            'fleet_resource_crystal' => 50,
            'fleet_resource_deuterium' => 25,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->ReturnEvent();

        $this->assertSame(1, $mission->kill);
        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame(1, $this->fake->achievement->messages[0][':userId']);
        $this->assertSame(4, $this->fake->achievement->messages[0][':type']);
        $this->assertNotEmpty($this->fake->fleetUpdates);
        $this->assertTrue(
            (bool) array_filter(
                $this->fake->fleetUpdates,
                static fn (array $update): bool => !empty($update['delete'])
            )
        );
    }

    public function test_transport_return_event_with_empty_start_planet_name(): void
    {
        unset($this->fake->planetRowsById[10]);

        $fleet = transportFleetFixture(['fleet_mess' => FLEET_RETURN]);
        $mission = new MissionCaseTransport($fleet);
        $mission->ReturnEvent();

        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame(1, $mission->kill);
    }
}
