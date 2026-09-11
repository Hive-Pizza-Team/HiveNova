<?php

use HiveNova\Core\FleetPlayService;
use PHPUnit\Framework\TestCase;

class FleetPlayServiceTest extends TestCase
{
	public function testLngKeyFallsBack(): void
	{
		$this->assertSame('Spy', FleetPlayService::lngKey(['type_mission_6' => 'Spy'], 'type_mission_6', 'Mission 6'));
		$this->assertSame('Mission 6', FleetPlayService::lngKey(['type_mission_6' => ''], 'type_mission_6', 'Mission 6'));
		$this->assertSame('Mission 6', FleetPlayService::lngKey(null, 'type_mission_6', 'Mission 6'));
	}

	public function testMissionChoicesUseLabels(): void
	{
		$choices = FleetPlayService::missionChoices(['type_mission_1' => 'Attack']);
		$ids = array_column($choices, 'id');
		$this->assertContains(FLEET_MISSION_ATTACK, $ids);
		$this->assertContains(FLEET_MISSION_EXPEDITION, $ids);
		$byId = [];
		foreach ($choices as $row) {
			$byId[$row['id']] = $row['name'];
		}
		$this->assertSame('Attack', $byId[FLEET_MISSION_ATTACK]);
		$this->assertSame('Mission '.FLEET_MISSION_SPY, $byId[FLEET_MISSION_SPY]);
	}

	public function testDescribeFlightStationUsesArrivalAsReturn(): void
	{
		$flight = FleetPlayService::describeFlight([
			'fleet_id' => 8,
			'fleet_mission' => FLEET_MISSION_STATION,
			'fleet_mess' => FLEET_OUTWARD,
			'fleet_start_galaxy' => 1,
			'fleet_start_system' => 2,
			'fleet_start_planet' => 3,
			'fleet_end_galaxy' => 4,
			'fleet_end_system' => 5,
			'fleet_end_planet' => 6,
			'fleet_array' => '202,5;',
			'fleet_resource_metal' => 10,
			'fleet_start_time' => 100,
			'fleet_end_time' => 200,
			'fleet_no_m_return' => 0,
		], [], 90);
		$this->assertSame('outbound', $flight['heading']);
		$this->assertSame(100, $flight['return']);
		$this->assertTrue($flight['recallable']);
		$this->assertSame(5, (int) $flight['ships'][202]);
	}

	public function testDescribeFlightReturnIsNotRecallable(): void
	{
		$flight = FleetPlayService::describeFlight([
			'fleet_id' => 1,
			'fleet_mission' => FLEET_MISSION_ATTACK,
			'fleet_mess' => FLEET_RETURN,
			'fleet_start_galaxy' => 1,
			'fleet_start_system' => 1,
			'fleet_start_planet' => 1,
			'fleet_end_galaxy' => 1,
			'fleet_end_system' => 1,
			'fleet_end_planet' => 2,
			'fleet_array' => '',
			'fleet_start_time' => 50,
			'fleet_end_time' => 80,
			'fleet_no_m_return' => 1,
		], [], 70);
		$this->assertSame('return', $flight['heading']);
		$this->assertSame(80, $flight['return']);
		$this->assertFalse($flight['recallable']);
		$this->assertSame(10, $flight['restSeconds']);
	}

	public function testPickShipsIgnoresSatellitesAndEmptyCounts(): void
	{
		$previous = $GLOBALS['resource'] ?? null;
		$GLOBALS['resource'] = [
			202 => 'small_ship',
			SHIP_SOLAR_SATELLITE => 'solar_sat',
		];
		try {
			$planet = ['small_ship' => 10, 'solar_sat' => 4];
			$this->assertSame([202 => 3], FleetPlayService::pickShips($planet, [202 => 3, SHIP_SOLAR_SATELLITE => 2, 203 => 9]));
			$this->assertSame([], FleetPlayService::pickShips($planet, [202 => 0]));
		} finally {
			if ($previous === null) {
				unset($GLOBALS['resource']);
			} else {
				$GLOBALS['resource'] = $previous;
			}
		}
	}

	public function testPreviewWithoutShipsIsNotReady(): void
	{
		$preview = FleetPlayService::preview(
			['id' => 1],
			['galaxy' => 1, 'system' => 1, 'planet' => 1, 'deuterium' => 80],
			[],
			['galaxy' => 1, 'system' => 1, 'planet' => 2],
			10,
			[]
		);
		$this->assertFalse($preview['ready']);
		$this->assertSame(80, $preview['deuterium']);
		$this->assertSame(0, $preview['consumption']);
	}
}
