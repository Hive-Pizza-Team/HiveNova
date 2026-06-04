<?php

/**
 * Minimal fleet row for mission TargetEvent / EndStayEvent tests.
 *
 * @return array<string, mixed>
 */
function missionFleetFixture(array $overrides = []): array
{
    return array_merge([
        'fleet_id' => 1,
        'fleet_owner' => 1,
        'fleet_target_owner' => 2,
        'fleet_universe' => 1,
        'fleet_mission' => 15,
        'fleet_mess' => FLEET_OUTWARD,
        'fleet_group' => 0,
        'fleet_start_id' => 10,
        'fleet_end_id' => 99,
        'fleet_start_galaxy' => 1,
        'fleet_start_system' => 1,
        'fleet_start_planet' => 3,
        'fleet_start_type' => 1,
        'fleet_end_galaxy' => 1,
        'fleet_end_system' => 1,
        'fleet_end_planet' => 16,
        'fleet_end_type' => 1,
        'fleet_start_time' => TIMESTAMP - 7200,
        'fleet_end_stay' => TIMESTAMP + 1800,
        'fleet_end_time' => TIMESTAMP + 5400,
        'fleet_array' => '202,10;',
        'fleet_resource_metal' => 0,
        'fleet_resource_crystal' => 0,
        'fleet_resource_deuterium' => 500,
        'fleet_resource_darkmatter' => 0,
    ], $overrides);
}
