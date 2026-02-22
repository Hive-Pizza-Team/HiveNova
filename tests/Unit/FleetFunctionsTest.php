<?php

use PHPUnit\Framework\TestCase;

class FleetFunctionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getMissileRange
    // -------------------------------------------------------------------------

    public function testGetMissileRangeIsZeroAtLevelZero(): void
    {
        // max((0 * 5) - 1, 0) = max(-1, 0) = 0
        $this->assertSame(0, FleetFunctions::getMissileRange(0));
    }

    public function testGetMissileRangeAtLevelOne(): void
    {
        // max((1 * 5) - 1, 0) = 4
        $this->assertSame(4, FleetFunctions::getMissileRange(1));
    }

    public function testGetMissileRangeScalesWithLevel(): void
    {
        // max((10 * 5) - 1, 0) = 49
        $this->assertSame(49, FleetFunctions::getMissileRange(10));
    }

    public function testGetMissileRangeNeverNegative(): void
    {
        $this->assertGreaterThanOrEqual(0, FleetFunctions::getMissileRange(-5));
    }

    // -------------------------------------------------------------------------
    // CheckUserSpeed
    // -------------------------------------------------------------------------

    /** @dataProvider validSpeedProvider */
    public function testCheckUserSpeedAcceptsValidSpeeds(int $speed): void
    {
        $this->assertTrue(FleetFunctions::CheckUserSpeed($speed));
    }

    public function validSpeedProvider(): array
    {
        return array_map(fn($s) => [$s], range(1, 10));
    }

    /** @dataProvider invalidSpeedProvider */
    public function testCheckUserSpeedRejectsInvalidSpeeds(mixed $speed): void
    {
        $this->assertFalse(FleetFunctions::CheckUserSpeed($speed));
    }

    public function invalidSpeedProvider(): array
    {
        return [
            'zero'         => [0],
            'eleven'       => [11],
            'negative'     => [-1],
            'large number' => [100],
        ];
    }

    // -------------------------------------------------------------------------
    // GetTargetDistance
    // -------------------------------------------------------------------------

    public function testGetTargetDistanceSamePlanetReturnsFive(): void
    {
        // Identical coordinates → always 5
        $this->assertSame(5, FleetFunctions::GetTargetDistance([1, 1, 7], [1, 1, 7]));
    }

    public function testGetTargetDistanceSameSystemDifferentPlanet(): void
    {
        // Same galaxy and system, planets differ by 1: 1*5 + 1000 = 1005
        $this->assertSame(1005, FleetFunctions::GetTargetDistance([1, 1, 7], [1, 1, 8]));
    }

    public function testGetTargetDistanceSameGalaxyDifferentSystem(): void
    {
        // Same galaxy, systems differ by 2: 2*95 + 2700 = 2890
        $this->assertSame(2890, FleetFunctions::GetTargetDistance([1, 3, 7], [1, 5, 7]));
    }

    public function testGetTargetDistanceDifferentGalaxies(): void
    {
        // Galaxies differ by 3: 3*20000 = 60000
        $this->assertSame(60000, FleetFunctions::GetTargetDistance([1, 1, 1], [4, 1, 1]));
    }

    public function testGetTargetDistanceGalaxyDifferenceIgnoresSmallerCoords(): void
    {
        // Galaxy takes priority regardless of system/planet differences
        $this->assertSame(20000, FleetFunctions::GetTargetDistance([1, 1, 1], [2, 499, 15]));
    }

    // -------------------------------------------------------------------------
    // unserialize
    // -------------------------------------------------------------------------

    public function testUnserializeSingleShipType(): void
    {
        $result = FleetFunctions::unserialize('201,5');
        $this->assertSame(['201' => 5], $result);
    }

    public function testUnserializeMultipleShipTypes(): void
    {
        $result = FleetFunctions::unserialize('201,10;202,3;210,1');
        $this->assertSame(['201' => 10, '202' => 3, '210' => 1], $result);
    }

    public function testUnserializeEmptyStringReturnsEmptyArray(): void
    {
        $result = FleetFunctions::unserialize('');
        $this->assertSame([], $result);
    }

    public function testUnserializeCombinesDuplicateShipIDs(): void
    {
        // Two entries for ship 201: 5 + 3 = 8
        $result = FleetFunctions::unserialize('201,5;201,3');
        $this->assertSame(['201' => 8], $result);
    }

    // -------------------------------------------------------------------------
    // GetMissionDuration
    // -------------------------------------------------------------------------

    public function testGetMissionDurationRespectsMinFleetTime(): void
    {
        // Very short calculated time should be clamped to MIN_FLEET_TIME
        $USER = [];  // no FlyTime factor
        $duration = FleetFunctions::GetMissionDuration(10, 99999999, 5, 100, $USER);
        $this->assertGreaterThanOrEqual(MIN_FLEET_TIME, $duration);
    }

    public function testGetMissionDurationIsPositive(): void
    {
        $USER = [];
        $duration = FleetFunctions::GetMissionDuration(10, 5000, 1000, 1, $USER);
        $this->assertGreaterThan(0, $duration);
    }

    public function testGetMissionDurationFlyTimeFactorScalesResult(): void
    {
        $USER_no_factor  = [];
        $USER_with_factor = ['factor' => ['FlyTime' => 1.0]];  // doubles travel time

        $base   = FleetFunctions::GetMissionDuration(5, 5000, 1000, 1, $USER_no_factor);
        $scaled = FleetFunctions::GetMissionDuration(5, 5000, 1000, 1, $USER_with_factor);

        // With FlyTime = 1.0: SpeedFactor *= max(0, 1 + 1.0) = 2 → roughly double
        $this->assertGreaterThan($base, $scaled);
    }

    // -------------------------------------------------------------------------
    // GetMaxFleetSlots
    // -------------------------------------------------------------------------

    public function testGetMaxFleetSlotsBaseSlotPlusComputerPlusBonus(): void
    {
        // GetMaxFleetSlots returns 1 + $USER[$resource[108]] + $USER['factor']['FleetSlots']
        $GLOBALS['resource'][108] = 108;
        $USER = [108 => 4, 'factor' => ['FleetSlots' => 2]];
        $this->assertSame(7, FleetFunctions::GetMaxFleetSlots($USER));
    }

    public function testGetMaxFleetSlotsMinimumIsOneWithNoTechOrBonus(): void
    {
        $GLOBALS['resource'][108] = 108;
        $USER = [108 => 0, 'factor' => ['FleetSlots' => 0]];
        $this->assertSame(1, FleetFunctions::GetMaxFleetSlots($USER));
    }

    // -------------------------------------------------------------------------
    // GetFleetRoom
    // -------------------------------------------------------------------------

    public function testGetFleetRoomCalculatesCapacityForSingleShipType(): void
    {
        // capacity 1000, 3 ships, no storage bonus → 3000
        $GLOBALS['pricelist'][201]['capacity'] = 1000;
        $GLOBALS['USER'] = ['factor' => ['ShipStorage' => 0]];
        $this->assertEquals(3000, FleetFunctions::GetFleetRoom([201 => 3]));
    }

    public function testGetFleetRoomAppliesStorageBonus(): void
    {
        // capacity 1000, 1 ship, 50% bonus → 1500
        $GLOBALS['pricelist'][201]['capacity'] = 1000;
        $GLOBALS['USER'] = ['factor' => ['ShipStorage' => 0.5]];
        $this->assertSame(1500.0, FleetFunctions::GetFleetRoom([201 => 1]));
    }

    public function testGetFleetRoomIsZeroForEmptyFleet(): void
    {
        $GLOBALS['USER'] = ['factor' => ['ShipStorage' => 0]];
        $this->assertEquals(0, FleetFunctions::GetFleetRoom([]));
    }

    // -------------------------------------------------------------------------
    // getExpeditionLimit
    // -------------------------------------------------------------------------

    public function testGetExpeditionLimitBasedOnAstrophysicsLevel(): void
    {
        // floor(sqrt(level)) + bonus; level=9 → floor(3.0) + 0 = 3.0
        $GLOBALS['resource'][124] = 124;
        $USER = [124 => 9, 'factor' => ['Expedition' => 0]];
        $this->assertEquals(3, FleetFunctions::getExpeditionLimit($USER));
    }

    public function testGetExpeditionLimitAddsBonus(): void
    {
        $GLOBALS['resource'][124] = 124;
        $USER = [124 => 4, 'factor' => ['Expedition' => 2]];
        // floor(sqrt(4)) + 2 = 2.0 + 2 = 4.0
        $this->assertEquals(4, FleetFunctions::getExpeditionLimit($USER));
    }

    // -------------------------------------------------------------------------
    // GetFleetMaxSpeed — empty fleet edge case
    // -------------------------------------------------------------------------

    public function testGetFleetMaxSpeedReturnsZeroForEmptyFleet(): void
    {
        $this->assertSame(0, FleetFunctions::GetFleetMaxSpeed([], []));
    }
}
