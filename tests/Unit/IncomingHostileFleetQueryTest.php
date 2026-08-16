<?php

use HiveNova\Core\IncomingHostileFleetQuery;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class IncomingHostileFleetQueryTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testZeroUserIdReturnsZeroWithoutQueryRows(): void
    {
        $this->assertSame(0, IncomingHostileFleetQuery::countForUser(0));
    }

    public function testOutwardAttackAtUserCountsOne(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet([
            'fleet_owner' => 2,
            'fleet_target_owner' => 99,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
        ]);
        $this->assertSame(1, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testOwnFleetIsIgnored(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet([
            'fleet_owner' => 99,
            'fleet_target_owner' => 99,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
        ]);
        $this->assertSame(0, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testReturningFleetIsIgnored(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet([
            'fleet_owner' => 2,
            'fleet_target_owner' => 99,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_RETURN,
        ]);
        $this->assertSame(0, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testHarvestAndTransportAreIgnored(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet([
            'fleet_owner' => 2,
            'fleet_target_owner' => 99,
            'fleet_mission' => 8,
            'fleet_mess' => FLEET_OUTWARD,
        ]);
        $this->fake->fleetRowsById[2] = $this->fleet([
            'fleet_owner' => 2,
            'fleet_target_owner' => 99,
            'fleet_mission' => 3,
            'fleet_mess' => FLEET_OUTWARD,
        ]);
        $this->assertSame(0, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testSpyAcsDestroyMissileAreCounted(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet(['fleet_mission' => 6]);
        $this->fake->fleetRowsById[2] = $this->fleet(['fleet_mission' => 2]);
        $this->fake->fleetRowsById[3] = $this->fleet(['fleet_mission' => 9]);
        $this->fake->fleetRowsById[4] = $this->fleet(['fleet_mission' => 10]);
        $this->assertSame(4, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testNpcOwnerZeroAttackCountsAsHostile(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet([
            'fleet_owner' => 0,
            'fleet_target_owner' => 99,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
        ]);
        $this->assertSame(1, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testAcsTargetingSomeoneElseIsIgnored(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet([
            'fleet_owner' => 2,
            'fleet_target_owner' => 50,
            'fleet_mission' => 2,
            'fleet_mess' => FLEET_OUTWARD,
        ]);
        $this->assertSame(0, IncomingHostileFleetQuery::countForUser(99));
    }

    public function testTwoInboundRowsCountTwo(): void
    {
        $this->fake->fleetRowsById[1] = $this->fleet(['fleet_mission' => 1]);
        $this->fake->fleetRowsById[2] = $this->fleet(['fleet_mission' => 1]);
        $this->assertSame(2, IncomingHostileFleetQuery::countForUser(99));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fleet(array $overrides = []): array
    {
        return array_merge([
            'fleet_owner' => 2,
            'fleet_target_owner' => 99,
            'fleet_mission' => 1,
            'fleet_mess' => FLEET_OUTWARD,
        ], $overrides);
    }
}
