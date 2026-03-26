<?php

/**
 * simulate-attack.php — CLI helper to test the "You are under attack!" banner.
 *
 * Usage:
 *   php tests/simulate-attack.php             # create fake inbound attack fleet
 *   php tests/simulate-attack.php --cleanup   # remove fleet + attacker user
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
chdir(ROOT_PATH);
set_include_path(ROOT_PATH);

if (!file_exists(ROOT_PATH . 'includes/config.php')) {
    die("Missing includes/config.php — run tests/ci-install.php first\n");
}

define('MODE', 'INSTALL');
@require ROOT_PATH . 'includes/common.php';
restore_exception_handler();
restore_error_handler();

require ROOT_PATH . 'includes/vars.php';

// Fleet direction constants (not defined in INSTALL mode)
if (!defined('FLEET_OUTWARD')) define('FLEET_OUTWARD', 0);

use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;

$db      = Database::get();
$cleanup = in_array('--cleanup', $argv);

// ── 1. Look up spacepizzadev ─────────────────────────────────────────────────

$targetUser = $db->selectSingle(
    'SELECT u.id, u.universe, u.id_planet,
            p.galaxy, p.`system`, p.planet, p.planet_type, p.name AS planet_name
     FROM %%USERS%% u
     JOIN %%PLANETS%% p ON p.id = u.id_planet
     WHERE u.username = :name;',
    [':name' => 'spacepizzadev']
);

if (empty($targetUser)) {
    die("Error: user 'spacepizzadev' not found.\n");
}

$universe   = (int) $targetUser['universe'];
$targetId   = (int) $targetUser['id'];
$targetPid  = (int) $targetUser['id_planet'];

// ── 2. Find the attacker ─────────────────────────────────────────────────────

$attacker = $db->selectSingle(
    'SELECT u.id, u.id_planet,
            p.galaxy, p.`system`, p.planet, p.planet_type
     FROM %%USERS%% u
     JOIN %%PLANETS%% p ON p.id = u.id_planet
     WHERE u.username = :name AND u.universe = :universe;',
    [':name' => 'attack_simulator', ':universe' => $universe]
);

// ── 3. --cleanup mode ────────────────────────────────────────────────────────

if ($cleanup) {
    if (empty($attacker)) {
        echo "Nothing to clean up — attack_simulator user not found.\n";
        exit(0);
    }

    $attackerId = (int) $attacker['id'];

    // Delete any fleets this attacker sent at spacepizzadev
    // (deletePlayer handles fleet_owner rows, but be explicit for clarity)
    $db->delete(
        'DELETE f, fe
         FROM %%FLEETS%% f
         LEFT JOIN %%FLEETS_EVENT%% fe ON fe.fleetID = f.fleet_id
         WHERE f.fleet_owner = :attacker AND f.fleet_target_owner = :target;',
        [':attacker' => $attackerId, ':target' => $targetId]
    );

    PlayerUtil::deletePlayer($attackerId);

    echo "Cleaned up: removed attack fleet and deleted attack_simulator user.\n";
    exit(0);
}

// ── 4. Create attacker if missing ────────────────────────────────────────────

if (empty($attacker)) {
    echo "Creating attack_simulator user...\n";

    // Pass null coords — let createPlayer auto-place the planet
    [$attackerId, $attackerPid] = PlayerUtil::createPlayer(
        $universe,
        'attack_simulator',
        PlayerUtil::cryptPassword('simulator123'),
        'attack_simulator@localhost',
        '',       // hive_account
        'en',     // language
        null, null, null  // auto-place
    );

    // Re-fetch planet coords
    $attacker = $db->selectSingle(
        'SELECT u.id, u.id_planet,
                p.galaxy, p.`system`, p.planet, p.planet_type
         FROM %%USERS%% u
         JOIN %%PLANETS%% p ON p.id = u.id_planet
         WHERE u.id = :id;',
        [':id' => $attackerId]
    );
} else {
    $attackerId = (int) $attacker['id'];
    echo "Reusing existing attack_simulator user (id={$attackerId}).\n";
}

// ── 5. Insert fake attack fleet ──────────────────────────────────────────────

$now          = TIMESTAMP;
$startTime    = $now + 3600;   // arrives in 1 hour
$endTime      = $now + 7200;   // return (unused but required)

$sql = 'INSERT INTO %%FLEETS%% SET
    fleet_owner                 = :owner,
    fleet_target_owner          = :target,
    fleet_mission               = 1,
    fleet_mess                  = :mess,
    fleet_amount                = 1,
    fleet_array                 = :ships,
    fleet_universe              = :universe,
    fleet_start_time            = :startTime,
    fleet_end_time              = :endTime,
    fleet_end_stay              = 0,
    fleet_start_id              = :startId,
    fleet_start_galaxy          = :sGalaxy,
    fleet_start_system          = :sSystem,
    fleet_start_planet          = :sPlanet,
    fleet_start_type            = :sType,
    fleet_end_id                = :endId,
    fleet_end_galaxy            = :eGalaxy,
    fleet_end_system            = :eSystem,
    fleet_end_planet            = :ePlanet,
    fleet_end_type              = :eType,
    fleet_resource_metal        = 0,
    fleet_resource_crystal      = 0,
    fleet_resource_deuterium    = 0,
    fleet_no_m_return           = 0,
    fleet_group                 = 0,
    fleet_target_obj            = 0,
    start_time                  = :now;';

$db->insert($sql, [
    ':owner'     => $attackerId,
    ':target'    => $targetId,
    ':mess'      => FLEET_OUTWARD,
    ':ships'     => '202,1',
    ':universe'  => $universe,
    ':startTime' => $startTime,
    ':endTime'   => $endTime,
    ':now'       => $now,
    ':startId'   => (int) $attacker['id_planet'],
    ':sGalaxy'   => (int) $attacker['galaxy'],
    ':sSystem'   => (int) $attacker['system'],
    ':sPlanet'   => (int) $attacker['planet'],
    ':sType'     => (int) $attacker['planet_type'],
    ':endId'     => $targetPid,
    ':eGalaxy'   => (int) $targetUser['galaxy'],
    ':eSystem'   => (int) $targetUser['system'],
    ':ePlanet'   => (int) $targetUser['planet'],
    ':eType'     => (int) $targetUser['planet_type'],
]);

$fleetId = $db->lastInsertId();

$db->insert(
    'INSERT INTO %%FLEETS_EVENT%% SET fleetID = :fleetId, `time` = :time;',
    [':fleetId' => $fleetId, ':time' => $startTime]
);

// ── 6. Confirmation ──────────────────────────────────────────────────────────

$attackerCoords = "[{$attacker['galaxy']}:{$attacker['system']}:{$attacker['planet']}]";
$targetCoords   = "[{$targetUser['galaxy']}:{$targetUser['system']}:{$targetUser['planet']}]";

echo "\nAttack fleet created (fleet_id={$fleetId}):\n";
echo "  Attacker : attack_simulator (id={$attackerId}) @ {$attackerCoords}\n";
echo "  Target   : spacepizzadev   (id={$targetId})   @ {$targetCoords}\n";
echo "  Arrives  : " . date('Y-m-d H:i:s', $startTime) . " (in 1 hour)\n";
echo "\nTo verify:\n";
echo "  1. Open http://localhost:8000 and log in as spacepizzadev\n";
echo "  2. Navigate any page — a red \"You are under attack!\" banner should appear\n";
echo "  3. Run: php tests/simulate-attack.php --cleanup\n";
