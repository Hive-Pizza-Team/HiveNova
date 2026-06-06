<?php

/**
 * Minimal fleet row for mission TargetEvent / EndStayEvent tests.
 *
 * @return array<string, mixed>
 */
/**
 * Expedition stay long enough that hold-time penalty forces the resource-find branch.
 *
 * @return array<string, mixed>
 */
function expeditionFleetLongHold(array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_mission' => 15,
        'fleet_end_stay' => TIMESTAMP + 400000,
        'fleet_start_time' => TIMESTAMP,
        'fleet_array' => '202,10;',
        'fleet_resource_metal' => 0,
        'fleet_resource_crystal' => 0,
        'fleet_resource_deuterium' => 0,
        'fleet_resource_darkmatter' => 0,
    ], $overrides));
}

/**
 * Expedition fleet tuned for combat / ship-find branches (strong fleet, short hold).
 *
 * @return array<string, mixed>
 */
function expeditionFleetCombatReady(array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_mission' => 15,
        'fleet_end_stay' => TIMESTAMP + 7200,
        'fleet_start_time' => TIMESTAMP,
        'fleet_array' => '204,500;206,50;207,20;',
        'fleet_amount' => 570,
        'fleet_resource_metal' => 0,
        'fleet_resource_crystal' => 0,
        'fleet_resource_deuterium' => 0,
        'fleet_resource_darkmatter' => 0,
    ], $overrides));
}

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
        'start_time' => TIMESTAMP - 8000,
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
