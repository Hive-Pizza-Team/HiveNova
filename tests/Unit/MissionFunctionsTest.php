<?php

use HiveNova\Core\MissionFunctions;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Tests for MissionFunctions state helpers and RestoreFleet SQL construction.
 */
class MissionFunctionsTest extends TestCase
{
    use SwapDatabaseInstance;

    private MissionFunctions $mf;

    private RecordingDatabase $db;

    protected function setUp(): void
    {
        $this->mf = new MissionFunctions();
        $this->db = new RecordingDatabase();
        $this->swapDatabaseInstance($this->db);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
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

    // -----------------------------------------------------------------------
    // RestoreFleet
    // -----------------------------------------------------------------------

    public function testRestoreFleetWithEmptyFleetArrayDoesNotEmitLeadingSetComma(): void
    {
        $this->mf->_fleet = $this->restoreFleetRow([
            'fleet_array' => '',
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
            'fleet_resource_deuterium' => 87,
            'fleet_resource_darkmatter' => 0,
        ]);

        $this->mf->RestoreFleet();

        $this->assertCount(1, $this->db->updates);
        [$sql, $params] = $this->db->updates[0];
        $this->assertDoesNotMatchRegularExpression('/SET\s*,/i', $sql);
        $this->assertStringContainsString('p.`metal` = p.`metal` + :metal', $sql);
        $this->assertStringContainsString('p.`deuterium` = p.`deuterium` + :deuterium', $sql);
        $this->assertSame(87, $params[':deuterium']);
        $this->assertSame(2172, $params[':planetId']);
        $this->assertCount(1, $this->db->deletes);
        $this->assertSame(1, $this->mf->kill);
    }

    public function testRestoreFleetIncludesShipColumnsWhenFleetHasShips(): void
    {
        $this->mf->_fleet = $this->restoreFleetRow([
            'fleet_array' => '202,5;210,1',
            'fleet_resource_metal' => 10,
            'fleet_resource_crystal' => 20,
            'fleet_resource_deuterium' => 30,
            'fleet_resource_darkmatter' => 0,
        ]);

        $this->mf->RestoreFleet();

        [$sql, $params] = $this->db->updates[0];
        $this->assertStringContainsString('p.`light_fighter` = p.`light_fighter` + :light_fighter', $sql);
        $this->assertStringContainsString('p.`bomber` = p.`bomber` + :bomber', $sql);
        $this->assertStringContainsString('p.`metal` = p.`metal` + :metal', $sql);
        $this->assertDoesNotMatchRegularExpression('/SET\s*,/i', $sql);
        $this->assertSame(5, $params[':light_fighter']);
        $this->assertSame(1, $params[':bomber']);
        $this->assertSame(10, $params[':metal']);
        $this->assertSame(2172, $params[':planetId']);
    }

    public function testRestoreFleetSkipsUnknownShipIds(): void
    {
        $this->mf->_fleet = $this->restoreFleetRow([
            'fleet_array' => '99999,4',
            'fleet_resource_deuterium' => 87,
        ]);

        $this->mf->RestoreFleet();

        [$sql, $params] = $this->db->updates[0];
        $this->assertDoesNotMatchRegularExpression('/SET\s*,/i', $sql);
        $this->assertStringNotContainsString('p.``', $sql);
        $this->assertArrayNotHasKey(':99999', $params);
        $this->assertSame(87, $params[':deuterium']);
    }

    public function testRestoreFleetUsesEndPlanetWhenNotOnStart(): void
    {
        $this->mf->_fleet = $this->restoreFleetRow([
            'fleet_array' => '',
            'fleet_start_id' => 2172,
            'fleet_end_id' => 99,
        ]);

        $this->mf->RestoreFleet(false);

        [, $params] = $this->db->updates[0];
        $this->assertSame(99, $params[':planetId']);
    }

    public function testRestoreFleetLogsDarkmatterWhenAmountIsPositive(): void
    {
        $this->db->selectSingleResult = ['id_owner' => 42];
        $this->mf->_fleet = $this->restoreFleetRow([
            'fleet_array' => '',
            'fleet_resource_darkmatter' => 15,
        ]);

        $this->mf->RestoreFleet();

        $this->assertCount(1, $this->db->selects);
        $this->assertCount(1, $this->db->inserts);
        [$sql, $params] = $this->db->inserts[0];
        $this->assertStringContainsString('%%DM_TRANSACTIONS%%', $sql);
        $this->assertSame(42, $params[':user_id']);
        $this->assertSame(15, $params[':amount_received']);
        $this->assertSame('expedition', $params[':memo']);
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function restoreFleetRow(array $override = []): array
    {
        return array_merge([
            'fleet_id' => 582249,
            'fleet_array' => '',
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
            'fleet_resource_deuterium' => 0,
            'fleet_resource_darkmatter' => 0,
            'fleet_start_id' => 2172,
            'fleet_end_id' => 99,
        ], $override);
    }
}
