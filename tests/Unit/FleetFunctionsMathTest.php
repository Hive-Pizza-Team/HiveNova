<?php

use HiveNova\Core\Config;
use HiveNova\Core\FleetFunctions;

use PHPUnit\Framework\TestCase;

class FleetFunctionsMathTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        Config::setInstance(new Config(['uni' => 1, 'fleet_speed' => 2500]), 1);

        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $GLOBALS['USER'] = ['factor' => ['ShipStorage' => 0]];
    }

    public function testGetMissileRangeScalesWithLevel(): void
    {
        $this->assertSame(0, FleetFunctions::getMissileRange(0));
        $this->assertSame(4, FleetFunctions::getMissileRange(1));
        $this->assertSame(14, FleetFunctions::getMissileRange(3));
    }

    public function testGetTargetDistanceAcrossGalaxies(): void
    {
        $this->assertSame(20000, FleetFunctions::GetTargetDistance([1, 1, 1], [2, 1, 1]));
    }

    public function testGetTargetDistanceSameCoords(): void
    {
        $this->assertSame(5, FleetFunctions::GetTargetDistance([1, 5, 8], [1, 5, 8]));
    }

    public function testGetMIPDurationUsesSystemGap(): void
    {
        $duration = FleetFunctions::GetMIPDuration(100, 105);
        $this->assertGreaterThanOrEqual(MIN_FLEET_TIME, $duration);
    }

    public function testGetFleetRoomSumsCapacity(): void
    {
        $GLOBALS['pricelist'][202]['capacity'] = 50;
        $room = FleetFunctions::GetFleetRoom([202 => 10]);
        $this->assertEqualsWithDelta(500, $room, 0.001);
    }

    public function testUnserializeParsesFleetString(): void
    {
        $parsed = FleetFunctions::unserialize('202,5;210,2;');
        $this->assertSame(5, $parsed[202]);
        $this->assertSame(2, $parsed[210]);
    }

    public function testGetExpeditionLimitUsesAstrophysics(): void
    {
        $user = [
            'astrophysics_tech' => 9,
            'factor' => ['Expedition' => 1],
        ];
        $this->assertEquals(4, FleetFunctions::getExpeditionLimit($user));
    }

    public function testCheckUserSpeedAcceptsAllowedValues(): void
    {
        foreach (range(1, 10) as $speed) {
            $this->assertTrue(FleetFunctions::CheckUserSpeed($speed), "Speed {$speed} should be allowed");
        }
        $this->assertFalse(FleetFunctions::CheckUserSpeed(0));
        $this->assertFalse(FleetFunctions::CheckUserSpeed(11));
        $this->assertFalse(FleetFunctions::CheckUserSpeed(99));
    }

    public function testGetMaxFleetSlotsIncludesComputerTech(): void
    {
        $GLOBALS['resource'][108] = 'computer_tech';
        $user = ['computer_tech' => 3, 'factor' => ['FleetSlots' => 1]];
        $this->assertSame(5, FleetFunctions::GetMaxFleetSlots($user));
    }

    public function testGetGameSpeedFactorFromConfig(): void
    {
        $this->assertEqualsWithDelta(1.0, FleetFunctions::GetGameSpeedFactor(), 0.001);
    }

    public function testGetMissionDurationRespectsMinFleetTime(): void
    {
        $user = ['factor' => ['FlyTime' => 0]];
        $duration = FleetFunctions::GetMissionDuration(1, 10000, 1000, 1, $user);
        $this->assertGreaterThanOrEqual(MIN_FLEET_TIME, $duration);
    }

    public function testGetFleetMaxSpeedReturnsZeroForEmptyFleet(): void
    {
        $player = ['combustion_tech' => 0, 'impulse_motor_tech' => 0, 'hyperspace_motor_tech' => 0];
        $this->assertSame(0, FleetFunctions::GetFleetMaxSpeed([], $player));
    }
}
