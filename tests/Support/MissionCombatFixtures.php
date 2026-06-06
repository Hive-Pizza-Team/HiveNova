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
        'shield_tech' => 0,
        'military_tech' => 0,
        'defence_tech' => 0,
        'armour_tech' => 0,
        'astrophysics_tech' => 0,
        'combustion_tech' => 0,
        'impulse_motor_tech' => 0,
        'hyperspace_motor_tech' => 0,
        'spy_tech' => 0,
        'spyMessagesMode' => 0,
        'timezone' => 'UTC',
        'wons' => 0,
        'loos' => 0,
        'draws' => 0,
        'kbmetal' => 0,
        'kbcrystal' => 0,
        'lostunits' => 0,
        'desunits' => 0,
        'id_planet' => 50,
        'galaxy' => 1,
        'system' => 1,
        'planet' => 8,
        'darkmatter' => 0,
        'b_tech' => 0,
        'b_tech_id' => 0,
        'b_tech_planet' => 0,
        'b_tech_queue' => '',
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
        'diameter' => 12500,
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
        'temp_min' => -10,
        'solar_plant' => 0,
        'solar_plant_porcent' => 100,
        'fusion_reactor' => 0,
        'fusion_reactor_porcent' => 100,
        'solar_satellite' => 0,
        'solar_satellite_porcent' => 100,
    ], $overrides);
}

/**
 * FakeDatabase with planet lookups needed by attack/destruction moon paths.
 */
class MissionCombatFakeDatabase extends FakeDatabase
{
    /** @var array<int, int> moonId => parentPlanetId */
    public array $moonParentMap = [];

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if (str_contains($qry, '%%PLANETS%%') && str_contains($qry, 'id_luna = :moonId')) {
            $moonId = (int) ($params[':moonId'] ?? 0);
            $parentId = $this->moonParentMap[$moonId] ?? null;
            if ($parentId !== null && isset($this->planetRowsById[$parentId])) {
                $row = $this->planetRowsById[$parentId];
                if (str_contains($qry, 'der_metal')) {
                    $debris = [
                        'der_metal' => $row['der_metal'] ?? 0,
                        'der_crystal' => $row['der_crystal'] ?? 0,
                    ];
                    if ($field !== false) {
                        return $debris[$field] ?? false;
                    }

                    return $debris;
                }
                if ($field === 'id') {
                    return $row['id'];
                }
                if ($field !== false) {
                    return $row[$field] ?? false;
                }

                return $row;
            }
        }

        if (str_contains($qry, '%%PLANETS%%')
            && str_contains($qry, 'planet_type = :type')
            && isset($params[':universe'], $params[':galaxy'], $params[':system'], $params[':position'])) {
            foreach ($this->planetRowsById as $row) {
                if ((int) ($row['universe'] ?? 0) !== (int) $params[':universe']) {
                    continue;
                }
                if ((int) ($row['galaxy'] ?? 0) !== (int) $params[':galaxy']) {
                    continue;
                }
                if ((int) ($row['system'] ?? 0) !== (int) $params[':system']) {
                    continue;
                }
                if ((int) ($row['planet'] ?? 0) !== (int) $params[':position']) {
                    continue;
                }
                if ((int) ($row['planet_type'] ?? 0) !== (int) $params[':type']) {
                    continue;
                }

                if ($field !== false) {
                    return $row[$field] ?? false;
                }

                return $row;
            }

            return $field === false ? null : false;
        }

        return parent::selectSingle($qry, $params, $field);
    }
}

function missionCombatEnvironmentSetup(): void
{
    if (!defined('MAX_ATTACK_ROUNDS')) {
        define('MAX_ATTACK_ROUNDS', 6);
    }

    $modules = [
        'MODULE_MISSION_ATTACK' => 1,
        'MODULE_MISSION_ACS' => 42,
        'MODULE_MISSION_DESTROY' => 29,
        'MODULE_ACHIEVEMENTS' => 46,
    ];
    foreach ($modules as $name => $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    $GLOBALS['reslist']['bonus'] = $GLOBALS['reslist']['bonus'] ?? [];
    $GLOBALS['reslist']['resstype'] = $GLOBALS['reslist']['resstype'] ?? [
        1 => [901, 902, 903],
        2 => [911],
    ];

    missionCombatPricelistOverrides();
}

function missionCombatPricelistOverrides(): void
{
    $GLOBALS['pricelist'][202] = array_merge($GLOBALS['pricelist'][202] ?? [], [
        'cost' => [901 => 3000, 902 => 1000, 903 => 0],
    ]);
    $GLOBALS['pricelist'][204] = array_merge($GLOBALS['pricelist'][202] ?? [], [
        'cost' => [901 => 6000, 902 => 4000, 903 => 0],
        'capacity' => 50,
        'consumption' => 300,
        'speed' => 10000,
        'tech' => 1,
    ]);
    $GLOBALS['pricelist'][214] = array_merge($GLOBALS['pricelist'][214] ?? [], [
        'cost' => [901 => 5000000, 902 => 4000000, 903 => 1000000],
        'capacity' => 1,
        'consumption' => 1,
        'speed' => 100,
        'tech' => 3,
    ]);

    $GLOBALS['CombatCaps'][202] = ['attack' => 50, 'shield' => 10];
    $GLOBALS['CombatCaps'][204] = ['attack' => 150, 'shield' => 50];
    $GLOBALS['CombatCaps'][214] = ['attack' => 200000, 'shield' => 50000];
    $GLOBALS['CombatCaps'][401] = ['attack' => 80, 'shield' => 20, 'plunder' => 40000];
}

/**
 * @param array<string, mixed> $overrides
 */
function missionCombatConfig(array $overrides = []): \HiveNova\Core\Config
{
    return new \HiveNova\Core\Config(array_merge([
        'uni' => 1,
        'Fleet_Cdr' => 30,
        'Defs_Cdr' => 0,
        'fleet_speed' => 2500,
        'moon_factor' => 0,
        'moon_chance' => 0,
        'debris_moon' => 0,
        'moduls' => implode(';', array_fill(0, 50, 1)),
    ], $overrides), 1);
}

function missionCombatSeedStandardTargets(MissionCombatFakeDatabase $fake): void
{
    $fake->achievement->users[1] = missionCombatUser(1);
    $fake->achievement->users[2] = missionCombatUser(2);
    $fake->planetRowsById[10] = missionCombatPlanet(10, 1, ['name' => 'Origin']);
    $fake->planetRowsById[99] = missionCombatPlanet(99, 2);
}

/**
 * Link a moon target to its parent planet for debris / destroy lookups.
 */
function missionCombatLinkMoonParent(
    MissionCombatFakeDatabase $fake,
    int $moonId,
    int $parentId,
    int $ownerId,
    array $moonOverrides = [],
    array $parentOverrides = [],
): void {
    $fake->planetRowsById[$parentId] = missionCombatPlanet($parentId, $ownerId, array_merge([
        'id_luna' => $moonId,
        'planet' => 8,
        'galaxy' => 1,
        'system' => 1,
    ], $parentOverrides));
    $fake->planetRowsById[$moonId] = missionCombatPlanet($moonId, $ownerId, array_merge([
        'planet_type' => 3,
        'id_luna' => 0,
        'diameter' => 8100,
        'rocket_launcher' => 0,
        'name' => 'Moon ' . $moonId,
    ], $moonOverrides));
    $fake->moonParentMap[$moonId] = $parentId;
}

/**
 * @return array<string, mixed>
 */
function missionCombatAcsMemberFleet(int $fleetId, int $acsId, array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_id' => $fleetId,
        'fleet_group' => $acsId,
        'fleet_mission' => 1,
        'fleet_array' => '202,100;',
        'fleet_amount' => 100,
    ], $overrides));
}

/**
 * @return array<string, mixed>
 */
function missionCombatStayFleetRow(int $planetId, array $overrides = []): array
{
    return array_merge([
        'fleet_id' => 50,
        'fleet_owner' => 2,
        'fleet_end_id' => $planetId,
        'fleet_mission' => 5,
        'fleet_start_time' => TIMESTAMP - 3600,
        'fleet_end_stay' => TIMESTAMP + 3600,
        'fleet_array' => '202,15;',
        'fleet_amount' => 15,
    ], $overrides);
}

/**
 * Winning attack fleet against light defenses.
 *
 * @return array<string, mixed>
 */
function missionCombatWinningAttackFleet(array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_mission' => 1,
        'fleet_array' => '202,100;',
        'fleet_amount' => 100,
        'fleet_target_owner' => 2,
    ], $overrides));
}

/**
 * Losing attack fleet against heavy defenses.
 *
 * @return array<string, mixed>
 */
function missionCombatLosingAttackFleet(array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_mission' => 1,
        'fleet_array' => '202,5;',
        'fleet_amount' => 5,
        'fleet_target_owner' => 2,
    ], $overrides));
}

/**
 * Death-star destruction fleet against a moon.
 *
 * @return array<string, mixed>
 */
function missionCombatDestructionFleet(array $overrides = []): array
{
    return missionFleetFixture(array_merge([
        'fleet_mission' => 9,
        'fleet_array' => '214,1;',
        'fleet_amount' => 1,
        'fleet_target_owner' => 2,
    ], $overrides));
}

/**
 * Extract raportWin / raportLose / raportDraw from a combat notification.
 */
function missionCombatReportClass(FakeDatabase $fake, int $userId = 1): string
{
    foreach ($fake->achievement->messages as $message) {
        if ((int) ($message[':userId'] ?? 0) !== $userId) {
            continue;
        }

        $text = (string) ($message[':text'] ?? '');
        if (preg_match('/raport(Win|Lose|Draw)/', $text, $matches)) {
            return $matches[1];
        }
    }

    return '';
}

/**
 * Seed a moon target and return the destruction fleet row.
 *
 * @return array<string, mixed>
 */
function missionCombatMoonDestructionSetup(
    MissionCombatFakeDatabase $fake,
    array $moonOverrides = [],
    array $parentOverrides = [],
): array {
    missionCombatLinkMoonParent($fake, 100, 50, 2, $moonOverrides, $parentOverrides);

    return missionCombatDestructionFleet([
        'fleet_end_id' => 100,
        'fleet_end_type' => 1,
    ]);
}
