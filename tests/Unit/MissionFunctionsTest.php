<?php

use HiveNova\Core\MissionFunctions;

use PHPUnit\Framework\TestCase;

/**
 * Tests for MissionFunctions — the pure state-management methods only.
 * DB-dependent methods (SaveFleet, KillFleet, RestoreFleet, StoreGoodsToPlanet,
 * getLanguage) are excluded as they require a live database connection.
 */
class MissionFunctionsTest extends TestCase
{
    private MissionFunctions $mf;

    protected function setUp(): void
    {
        $this->mf = new MissionFunctions();
    }

    // -----------------------------------------------------------------------
    // Initial state
    // -----------------------------------------------------------------------

    public function testInitialKillFlagIsZero(): void
    {
        $this->assertSame(0, $this->mf->kill);
    }

    public function testInitialFleetArrayIsEmpty(): void
    {
        $this->assertSame([], $this->mf->_fleet);
    }

    public function testInitialUpdateArrayIsEmpty(): void
    {
        $this->assertSame([], $this->mf->_upd);
    }

    public function testInitialEventTimeIsZero(): void
    {
        $this->assertSame(0, $this->mf->eventTime);
    }

    // -----------------------------------------------------------------------
    // UpdateFleet
    // -----------------------------------------------------------------------

    public function testUpdateFleetSetsFleetKey(): void
    {
        $this->mf->UpdateFleet('fleet_mess', 1);
        $this->assertSame(1, $this->mf->_fleet['fleet_mess']);
    }

    public function testUpdateFleetSetsUpdKey(): void
    {
        $this->mf->UpdateFleet('fleet_mess', 1);
        $this->assertSame(1, $this->mf->_upd['fleet_mess']);
    }

    public function testUpdateFleetOverwritesPreviousValue(): void
    {
        $this->mf->UpdateFleet('fleet_mess', 0);
        $this->mf->UpdateFleet('fleet_mess', 2);
        $this->assertSame(2, $this->mf->_fleet['fleet_mess']);
        $this->assertSame(2, $this->mf->_upd['fleet_mess']);
    }

    public function testUpdateFleetHandlesMultipleKeys(): void
    {
        $this->mf->UpdateFleet('fleet_resource_metal', 500);
        $this->mf->UpdateFleet('fleet_resource_crystal', 250);

        $this->assertSame(500, $this->mf->_fleet['fleet_resource_metal']);
        $this->assertSame(250, $this->mf->_fleet['fleet_resource_crystal']);
        $this->assertSame(500, $this->mf->_upd['fleet_resource_metal']);
        $this->assertSame(250, $this->mf->_upd['fleet_resource_crystal']);
    }

    public function testUpdateFleetAcceptsStringValue(): void
    {
        $this->mf->UpdateFleet('fleet_array', '202,5;210,1');
        $this->assertSame('202,5;210,1', $this->mf->_fleet['fleet_array']);
    }

    // -----------------------------------------------------------------------
    // setState — FLEET_OUTWARD
    // -----------------------------------------------------------------------

    public function testSetStateOutwardSetsFleetMess(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 1000;
        $this->mf->_fleet['fleet_end_time']   = 2000;
        $this->mf->_fleet['fleet_end_stay']   = 3000;

        $this->mf->setState(FLEET_OUTWARD);

        $this->assertSame(FLEET_OUTWARD, $this->mf->_fleet['fleet_mess']);
        $this->assertSame(FLEET_OUTWARD, $this->mf->_upd['fleet_mess']);
    }

    public function testSetStateOutwardSetsEventTimeToStartTime(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 1000;
        $this->mf->_fleet['fleet_end_time']   = 2000;
        $this->mf->_fleet['fleet_end_stay']   = 3000;

        $this->mf->setState(FLEET_OUTWARD);

        $this->assertSame(1000, $this->mf->eventTime);
    }

    // -----------------------------------------------------------------------
    // setState — FLEET_RETURN
    // -----------------------------------------------------------------------

    public function testSetStateReturnSetsFleetMess(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 1000;
        $this->mf->_fleet['fleet_end_time']   = 2000;
        $this->mf->_fleet['fleet_end_stay']   = 3000;

        $this->mf->setState(FLEET_RETURN);

        $this->assertSame(FLEET_RETURN, $this->mf->_fleet['fleet_mess']);
    }

    public function testSetStateReturnSetsEventTimeToEndTime(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 1000;
        $this->mf->_fleet['fleet_end_time']   = 2000;
        $this->mf->_fleet['fleet_end_stay']   = 3000;

        $this->mf->setState(FLEET_RETURN);

        $this->assertSame(2000, $this->mf->eventTime);
    }

    // -----------------------------------------------------------------------
    // setState — FLEET_HOLD
    // -----------------------------------------------------------------------

    public function testSetStateHoldSetsFleetMess(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 1000;
        $this->mf->_fleet['fleet_end_time']   = 2000;
        $this->mf->_fleet['fleet_end_stay']   = 3000;

        $this->mf->setState(FLEET_HOLD);

        $this->assertSame(FLEET_HOLD, $this->mf->_fleet['fleet_mess']);
    }

    public function testSetStateHoldSetsEventTimeToEndStay(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 1000;
        $this->mf->_fleet['fleet_end_time']   = 2000;
        $this->mf->_fleet['fleet_end_stay']   = 3000;

        $this->mf->setState(FLEET_HOLD);

        $this->assertSame(3000, $this->mf->eventTime);
    }

    // -----------------------------------------------------------------------
    // setState — each state sets a distinct eventTime
    // -----------------------------------------------------------------------

    public function testEachStateSetsDifferentEventTime(): void
    {
        $this->mf->_fleet['fleet_start_time'] = 100;
        $this->mf->_fleet['fleet_end_time']   = 200;
        $this->mf->_fleet['fleet_end_stay']   = 300;

        $this->mf->setState(FLEET_OUTWARD);
        $outward = $this->mf->eventTime;

        $this->mf->setState(FLEET_RETURN);
        $return = $this->mf->eventTime;

        $this->mf->setState(FLEET_HOLD);
        $hold = $this->mf->eventTime;

        $this->assertSame(100, $outward);
        $this->assertSame(200, $return);
        $this->assertSame(300, $hold);
    }
}
