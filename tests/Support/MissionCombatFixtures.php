<?php

/**
 * Planet and user rows for mission combat unit tests.
 */
function missionCombatUser(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'username' => 'player' . $id,
        'lang' => 'en',
        'universe' => 1,
        'authlevel' => 0,
        'bana' => 0,
        'urlaubs_modus' => 0,
        'onlinetime' => TIMESTAMP,
        'weapons_tech' => 0,
        'shielding_tech' => 0,
        'military_tech' => 0,
        'defence_tech' => 0,
        'armour_tech' => 0,
        'astrophysics_tech' => 0,
    ], $overrides);
}

function missionCombatPlanet(int $id, int $ownerId, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'id_owner' => $ownerId,
        'name' => 'Planet ' . $id,
        'universe' => 1,
        'galaxy' => 1,
        'system' => 1,
        'planet' => 8,
        'planet_type' => 1,
        'id_luna' => 0,
        'metal' => 10000,
        'crystal' => 10000,
        'deuterium' => 5000,
        'energy' => 0,
        'energy_used' => 0,
        'metal_perhour' => 0,
        'crystal_perhour' => 0,
        'deuterium_perhour' => 0,
        'metal_max' => 100000,
        'crystal_max' => 100000,
        'deuterium_max' => 100000,
        'last_update' => TIMESTAMP - 3600,
        'eco_hash' => '',
        'der_metal' => 0,
        'der_crystal' => 0,
        'light_fighter' => 0,
        'rocket_launcher' => 10,
        'interplanetary_missile' => 0,
        'b_building' => 0,
        'b_building_id' => '',
        'b_hangar_id' => '',
        'b_hangar' => 0,
        'field_current' => 0,
        'temp_max' => 30,
        'solar_plant' => 0,
        'solar_plant_porcent' => 100,
        'fusion_reactor' => 0,
        'fusion_reactor_porcent' => 100,
        'solar_satellite' => 0,
        'solar_satellite_porcent' => 100,
    ], $overrides);
}
