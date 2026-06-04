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

        $this->fake->achievement->users[1] = ['id' => 1, 'lang' => 'en', 'universe' => 1];
        $this->fake->achievement->users[2] = ['id' => 2, 'lang' => 'en', 'universe' => 1];

        $this->fake->planetRowsById[10] = ['id' => 10, 'name' => 'Origin', 'id_owner' => 1];
        $this->fake->planetRowsById[99] = ['id' => 99, 'name' => 'Colony', 'id_owner' => 2];
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_transport_delivers_resources_to_target(): void
    {
        $fleet = missionFleetFixture([
            'fleet_mission' => 3,
            'fleet_target_owner' => 1,
            'fleet_resource_metal' => 5000,
            'fleet_resource_crystal' => 2000,
        ]);

        $mission = new MissionCaseTransport($fleet);
        $mission->TargetEvent();

        $this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
        $this->assertCount(1, $this->fake->achievement->messages);
    }
}
