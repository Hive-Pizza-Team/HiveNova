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

/**
 * Transport mission (mission 3) with typical cargo.
 *
 * @return array<string, mixed>
 */
function transportFleetFixture(array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_mission' => 3,
        'fleet_array' => '202,5;',
        'fleet_resource_metal' => 1000,
        'fleet_resource_crystal' => 500,
        'fleet_resource_deuterium' => 250,
        'fleet_resource_darkmatter' => 0,
    ], $overrides));
}

/**
 * Transport to the fleet owner's own planet (single notification).
 *
 * @return array<string, mixed>
 */
function transportFleetSelf(array $overrides = []): array
{
    return transportFleetFixture(array_merge([
        'fleet_owner' => 1,
        'fleet_target_owner' => 1,
        'fleet_start_id' => 10,
        'fleet_end_id' => 99,
    ], $overrides));
}

/**
 * Transport to another player's planet (owner + recipient notifications).
 *
 * @return array<string, mixed>
 */
function transportFleetForeign(array $overrides = []): array
{
    return transportFleetFixture(array_merge([
        'fleet_owner' => 1,
        'fleet_target_owner' => 2,
        'fleet_start_id' => 10,
        'fleet_end_id' => 99,
    ], $overrides));
}

/**
 * Seed FakeDatabase rows shared by transport mission tests.
 */
function transportDatabaseFixture(FakeDatabase $fake): void
{
    $fake->achievement->users[1] = ['id' => 1, 'lang' => 'en', 'universe' => 1];
    $fake->achievement->users[2] = ['id' => 2, 'lang' => 'en', 'universe' => 1];

    $fake->planetRowsById[10] = ['id' => 10, 'name' => 'Origin', 'id_owner' => 1];
    $fake->planetRowsById[99] = ['id' => 99, 'name' => 'Colony', 'id_owner' => 2];
}

/**
 * Constants and Universe state required by Log::saveTr on foreign deliveries.
 */
function transportMissionEnvironmentSetup(): void
{
    foreach ([
        'SESSION_LIFETIME' => 3600,
        'HTTP_ROOT' => '/',
        'HTTPS' => false,
        'PREVENT_MULTISESSIONS' => false,
    ] as $name => $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    $available = new ReflectionProperty(\HiveNova\Core\Universe::class, 'availableUniverses');
    $available->setAccessible(true);
    $available->setValue([1]);

    $emulated = new ReflectionProperty(\HiveNova\Core\Universe::class, 'emulatedUniverse');
    $emulated->setAccessible(true);
    $emulated->setValue(1);
}

/**
 * Reset Universe singleton state after transport tests.
 */
function transportMissionEnvironmentTeardown(): void
{
    foreach (['availableUniverses', 'currentUniverse', 'emulatedUniverse'] as $prop) {
        $ref = new ReflectionProperty(\HiveNova\Core\Universe::class, $prop);
        $ref->setAccessible(true);
        $ref->setValue(null);
    }
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
