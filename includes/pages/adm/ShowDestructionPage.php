<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\Log;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\SQLDumper;
use HiveNova\Core\Template;
use HiveNova\Core\Universe;

/** Session payload key for two-step accept → execute destruction flow */
const DESTRUCTION_SESSION_KEY = 'destruction_review_v1';

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) {
    throw new Exception("Permission error!");
}

/**
 * Find a free planet slot for relocation, scanning outward from $targetSystem.
 * Scan order: target → max_system, then target-1 → 1.
 * Uses positions 4..(max_planets-3) to avoid astrophysics-gated edge slots.
 * Returns [system, position] or null if the galaxy is full.
 */
function findFreeSlot(int $universe, int $galaxy, int $targetSystem): ?array
{
    $config     = Config::get($universe);
    $maxSystem  = (int) $config->max_system;
    $minPos     = 4;
    $maxPos     = (int) $config->max_planets - 3;

    // Build scan order: target..max_system, then target-1..1
    $systems = array_merge(
        range($targetSystem, $maxSystem),
        range($targetSystem - 1, 1)
    );

    foreach ($systems as $sys) {
        if ($sys < 1 || $sys > $maxSystem) {
            continue;
        }
        for ($pos = $minPos; $pos <= $maxPos; $pos++) {
            if (PlayerUtil::isPositionFree($universe, $galaxy, $sys, $pos)) {
                return [$sys, $pos];
            }
        }
    }

    return null;
}

/**
 * Find a random free slot anywhere in $galaxy.
 * Returns [system, position] or null if the galaxy is full.
 */
function findRandomFreeSlot(int $universe, int $galaxy): ?array
{
    $config    = Config::get($universe);
    $maxSystem = (int) $config->max_system;
    $minPos    = 4;
    $maxPos    = (int) $config->max_planets - 3;

    // Build all candidate [system, position] pairs and shuffle
    $candidates = [];
    for ($sys = 1; $sys <= $maxSystem; $sys++) {
        for ($pos = $minPos; $pos <= $maxPos; $pos++) {
            $candidates[] = [$sys, $pos];
        }
    }
    shuffle($candidates);

    foreach ($candidates as [$sys, $pos]) {
        if (PlayerUtil::isPositionFree($universe, $galaxy, $sys, $pos)) {
            return [$sys, $pos];
        }
    }

    return null;
}

function executeRelocation(
    int $universe, string $mode, int $galaxy, int $system,
    int $relocate, string $relocMode, int $relocGal, int $relocSys
): array {
    $db = Database::get();

    // Zone condition for planets table (aliased p)
    $zoneCondition = 'p.universe = :uni AND p.galaxy = :gal';
    $params = [':uni' => $universe, ':gal' => $galaxy];
    if ($mode === 'system') {
        $params[':sys'] = $system;
        $zoneCondition .= ' AND p.system = :sys';
        $outsideZone = 'p2.universe = :uni AND NOT (p2.galaxy = :gal AND p2.system = :sys)';
    } else {
        $outsideZone = 'p2.universe = :uni AND p2.galaxy != :gal';
    }

    // Fetch all users whose main planet (id_planet) is in the zone
    $displaced = $db->select(
        "SELECT u.id AS userId, u.id_planet
         FROM %%USERS%% u
         JOIN %%PLANETS%% p ON p.id = u.id_planet
         WHERE $zoneCondition",
        $params
    );

    $relocated = 0;
    $skipped   = 0;

    foreach ($displaced as $user) {
        $userId = (int) $user['userId'];

        if (!$relocate) {
            // Relocation OFF — promote their first surviving planet outside the zone
            $fallback = $db->selectSingle(
                "SELECT p2.id, p2.galaxy, p2.system, p2.planet
                 FROM %%PLANETS%% p2
                 WHERE p2.id_owner = :userId AND p2.destruyed = 0 AND $outsideZone
                 ORDER BY p2.id ASC LIMIT 1",
                array_merge($params, [':userId' => $userId])
            );

            if ($fallback) {
                $db->update(
                    "UPDATE %%USERS%% SET id_planet = :pid, galaxy = :gal2, `system` = :sys2, planet = :pos
                     WHERE id = :userId",
                    [
                        ':pid'    => $fallback['id'],
                        ':gal2'   => $fallback['galaxy'],
                        ':sys2'   => $fallback['system'],
                        ':pos'    => $fallback['planet'],
                        ':userId' => $userId,
                    ]
                );
                $relocated++;
            } else {
                // Homeless — no surviving planets; set id_planet = 0
                $db->update(
                    "UPDATE %%USERS%% SET id_planet = 0 WHERE id = :userId",
                    [':userId' => $userId]
                );
                $skipped++;
            }
        } else {
            // Relocation ON — create a new planet for this player
            $targetGalaxy = $relocGal > 0 ? $relocGal : $galaxy;

            if ($relocMode === 'exact') {
                $slot = findFreeSlot($universe, $targetGalaxy, $relocSys);
            } else {
                $slot = findRandomFreeSlot($universe, $targetGalaxy);
            }

            if ($slot === null) {
                // No space found — fall back to id_planet = 0
                $db->update(
                    "UPDATE %%USERS%% SET id_planet = 0 WHERE id = :userId",
                    [':userId' => $userId]
                );
                $skipped++;
                continue;
            }

            [$newSystem, $newPosition] = $slot;
            $newPlanetId = PlayerUtil::createPlanet(
                $targetGalaxy, $newSystem, $newPosition,
                $universe, $userId, null, true
            );

            $db->update(
                "UPDATE %%USERS%% SET id_planet = :pid, galaxy = :gal2, `system` = :sys2, planet = :pos
                 WHERE id = :userId",
                [
                    ':pid'    => $newPlanetId,
                    ':gal2'   => $targetGalaxy,
                    ':sys2'   => $newSystem,
                    ':pos'    => $newPosition,
                    ':userId' => $userId,
                ]
            );
            $relocated++;
        }
    }

    return ['relocated' => $relocated, 'skipped' => $skipped];
}

function executeFleets(int $universe, string $mode, int $galaxy, int $system): array
{
    $db = Database::get();

    $params = [':uni' => $universe, ':gal' => $galaxy, ':now' => TIMESTAMP];

    $endInZone   = 'fleet_universe = :uni AND fleet_end_galaxy = :gal';
    $startInZone = 'fleet_universe = :uni AND fleet_start_galaxy = :gal';

    if ($mode === 'system') {
        $params[':sys'] = $system;
        $endInZone   .= ' AND fleet_end_system = :sys';
        $startInZone .= ' AND fleet_start_system = :sys';
        $endNotInZone = 'NOT (fleet_end_galaxy = :gal AND fleet_end_system = :sys)';
    } else {
        $endNotInZone = 'fleet_end_galaxy != :gal';
    }

    // Lost fleets — destination is in the zone, no refund
    $db->delete(
        "DELETE %%FLEETS%%, %%FLEETS_EVENT%%
         FROM %%FLEETS%% LEFT JOIN %%FLEETS_EVENT%% ON fleet_id = fleetID
         WHERE $endInZone",
        $params
    );
    $lost = (int) $db->rowCount();

    // Force-completed fleets — origin in zone, destination safe.
    // Set start = end (fleet considers destination its new base) and
    // set event time to now so the next cron run processes arrival.
    $db->update(
        "UPDATE %%FLEETS%%, %%FLEETS_EVENT%%
         SET fleet_start_id     = fleet_end_id,
             fleet_start_galaxy = fleet_end_galaxy,
             fleet_start_system = fleet_end_system,
             fleet_start_planet = fleet_end_planet,
             fleet_start_type   = fleet_end_type,
             time               = :now
         WHERE fleet_id = fleetID
           AND $startInZone
           AND $endNotInZone",
        $params
    );
    $survived = (int) $db->rowCount();

    return ['lost' => $lost, 'survived' => $survived];
}

function executePlanets(
    int $universe, string $mode, int $galaxy, int $system,
    int $debris, float $debrisMetal, float $debrisCrystal
): array {
    $db = Database::get();

    $zoneWhere = 'universe = :uni AND galaxy = :gal';
    $params    = [':uni' => $universe, ':gal' => $galaxy];

    if ($mode === 'system') {
        $params[':sys'] = $system;
        $zoneWhere     .= ' AND `system` = :sys';
    }

    // Fetch affected users before deleting — planets will be gone after the DELETE.
    // We need their IDs for notifications (Step 9) and their id_planet for debris.
    $affectedUsers = $db->select(
        "SELECT DISTINCT u.id, u.id_planet
         FROM %%USERS%% u
         JOIN %%PLANETS%% p ON p.id_owner = u.id
         WHERE p.$zoneWhere",
        $params
    );

    // Add debris to each affected player's current home planet before deleting.
    // By the time this runs, Step 7 has already updated id_planet to the new home
    // (or 0 for homeless players, which we skip).
    if ($debris && ($debrisMetal > 0 || $debrisCrystal > 0)) {
        foreach ($affectedUsers as $row) {
            if (!(int) $row['id_planet']) continue;
            $db->update(
                "UPDATE %%PLANETS%% SET
                    der_metal   = der_metal   + :metal,
                    der_crystal = der_crystal + :crystal
                 WHERE id = :pid",
                [
                    ':metal'   => $debrisMetal,
                    ':crystal' => $debrisCrystal,
                    ':pid'     => (int) $row['id_planet'],
                ]
            );
        }
    }

    // Delete all planets and moons in the zone.
    // Moons (planet_type = 3) share the same coordinates and are caught by the
    // same WHERE clause — no separate DELETE needed.
    $db->delete(
        "DELETE FROM %%PLANETS%% WHERE $zoneWhere",
        $params
    );

    $affectedUserIds = array_column($affectedUsers, 'id');

    return ['count' => (int) $db->rowCount(), 'affectedUserIds' => $affectedUserIds];
}

function executeNotifications(
    int $universe, string $mode, int $galaxy, int $system,
    array $affectedUserIds, string $message, int $broadcast,
    string $adminName
): void {
    global $LNG;

    if (empty($affectedUserIds) && !$broadcast) {
        return;
    }

    $db = Database::get();

    $subject = $mode === 'system'
        ? sprintf($LNG['dest_msg_subject_system'], $galaxy, $system)
        : sprintf($LNG['dest_msg_subject_galaxy'], $galaxy);

    $sender = '<span class="admin">' . htmlspecialchars($adminName) . '</span>';

    // Individual messages to every player who owned a planet in the destroyed zone
    if (!empty($affectedUserIds)) {
        $placeholders = implode(',', array_fill(0, count($affectedUserIds), '?'));
        $users = $db->select(
            "SELECT id, username FROM %%USERS%% WHERE id IN ($placeholders)",
            $affectedUserIds
        );

        foreach ($users as $user) {
            PlayerUtil::sendMessage(
                (int) $user['id'], 0, $sender, 50,
                $subject, $message, TIMESTAMP, null, 1, $universe
            );
        }
    }

    // Optional universe-wide broadcast to all players in the universe
    if ($broadcast) {
        $allUsers = $db->select(
            "SELECT id FROM %%USERS%% WHERE universe = :uni",
            [':uni' => $universe]
        );

        $alreadyNotified = array_flip($affectedUserIds);

        foreach ($allUsers as $user) {
            // Skip players already sent an individual message
            if (isset($alreadyNotified[$user['id']])) {
                continue;
            }
            PlayerUtil::sendMessage(
                (int) $user['id'], 0, $sender, 50,
                $subject, $message, TIMESTAMP, null, 1, $universe
            );
        }
    }
}

function executeLog(
    int $universe, string $mode, int $galaxy, int $system,
    int $adminId, int $planetCount,
    array $fleetResult, array $relocResult,
    float $debrisMetal, float $debrisCrystal, int $broadcast,
    ?string $backupRelPath = null
): void {
    $log           = new Log(5);
    $log->target   = 0;
    $log->universe = $universe;
    $log->new      = [
        'mode'            => $mode,
        'galaxy'          => $galaxy,
        'system'          => $system,
        'planets_deleted' => $planetCount,
        'fleets_lost'     => $fleetResult['lost'],
        'fleets_survived' => $fleetResult['survived'],
        'relocated'       => $relocResult['relocated'],
        'skipped'         => $relocResult['skipped'],
        'debris_metal'    => $debrisMetal,
        'debris_crystal'  => $debrisCrystal,
        'broadcast'       => $broadcast,
        'backup_file'     => $backupRelPath ?? '',
    ];
    $log->saveTr();
}

/**
 * Full backup of game tables (same scope as Admin → Database Backup).
 *
 * @return string Relative path under ROOT_PATH for display/logging
 */
function runDestructionDatabaseBackup(): string
{
    $db        = Database::get();
    $prefixLen = strlen((string) DB_PREFIX);
    $rows      = $db->nativeQuery('SHOW TABLE STATUS FROM `' . str_replace('`', '``', DB_NAME) . '`');
    $tables    = [];
    foreach ($rows as $table) {
        $name = (string) ($table['Name'] ?? '');
        if ($name === 'transactions' || ($prefixLen > 0 && strncmp($name, DB_PREFIX, $prefixLen) === 0)) {
            $tables[] = $name;
        }
    }
    if ($tables === []) {
        throw new Exception('No tables matched for backup — aborting destruction.');
    }
    $fileName = '2MoonsBackup_destruction_' . date('Y_m_d_H_i_s', TIMESTAMP) . '.sql';
    $relPath  = 'includes/backups/' . $fileName;
    $filePath = ROOT_PATH . $relPath;

    $dir = dirname($filePath);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new Exception('Cannot create backup directory: ' . $dir);
    }

    $dump = new SQLDumper();
    $dump->dumpTablesToFile($tables, $filePath);

    return $relPath;
}

/**
 * Update %%CONFIG%% registration spiral cursor for new accounts (PlayerUtil::createPlayer auto-placement).
 */
function applySpawnCursor(int $universe, int $galaxy, int $system, int $planet): void
{
    $cfg = Config::get($universe);
    if ($galaxy < 1 || $galaxy > (int) $cfg->max_galaxy
        || $system < 1 || $system > (int) $cfg->max_system
        || $planet < 1 || $planet > (int) $cfg->max_planets
    ) {
        throw new Exception('Spawn coordinates are outside universe bounds.');
    }
    if (!PlayerUtil::checkPosition($universe, $galaxy, $system, $planet, 1)) {
        throw new Exception('Invalid spawn coordinates.');
    }
    $cfg->LastSettedGalaxyPos  = $galaxy;
    $cfg->LastSettedSystemPos = $system;
    $cfg->LastSettedPlanetPos = $planet;
    $cfg->save();
}

/**
 * Human-readable summary of write operations (actual queries use %%TABLE%% placeholders in Database layer).
 *
 * @return list<string>
 */
function destructionSqlPreviewLines(
    int $universe, string $mode, int $galaxy, int $system,
    int $relocate, bool $spawnApply,
    int $debris, int $broadcast, float $debrisMetal, float $debrisCrystal
): array {
    $pfx = DB_PREFIX;
    $zoneSql = $mode === 'system'
        ? sprintf('universe = %d AND galaxy = %d AND `system` = %d', $universe, $galaxy, $system)
        : sprintf('universe = %d AND galaxy = %d', $universe, $galaxy);
    $fleetEnd = $mode === 'system'
        ? sprintf('fleet_universe = %d AND fleet_end_galaxy = %d AND fleet_end_system = %d', $universe, $galaxy, $system)
        : sprintf('fleet_universe = %d AND fleet_end_galaxy = %d', $universe, $galaxy);

    $lines = [
        '-- HiveNova destruction — Database layer expands %%TABLE%% to prefixed MySQL tables',
        sprintf('DELETE `%sfleets` / `%sfleets_event` rows WHERE fleet destination matches zone (%s)', $pfx, $pfx, $fleetEnd),
        sprintf(
            'UPDATE `%sfleets` SET fleet_start_* = fleet_end_*, time = :now WHERE origin in zone and destination outside zone (%s …)',
            $pfx,
            $pfx
        ),
        sprintf(
            'UPDATE `%susers` / `%splanets` — relocate displaced home planets when relocation rules apply',
            $pfx,
            $pfx
        ),
    ];
    if ($relocate) {
        $lines[] = sprintf(
            'INSERT INTO `%splanets` + UPDATE `%susers` — PlayerUtil::createPlanet() for each displaced account under relocation',
            $pfx,
            $pfx
        );
    }
    if ($debris && ($debrisMetal > 0 || $debrisCrystal > 0)) {
        $lines[] = sprintf(
            'UPDATE `%splanets` SET der_metal += %.2f, der_crystal += %.2f WHERE id IN (current homes before zone DELETE)',
            $pfx,
            $debrisMetal,
            $debrisCrystal
        );
    }
    $lines[] = sprintf('DELETE FROM `%splanets` WHERE %s', $pfx, $zoneSql);

    if ($broadcast) {
        $lines[] = sprintf('INSERT INTO `%smessages` — DM/broadcast to affected players (and universe when enabled)', $pfx);
    }
    $lines[] = sprintf('INSERT INTO `%slog` — audit trail mode 5', $pfx);
    if ($spawnApply) {
        $lines[] = sprintf(
            'UPDATE `%sconfig` SET LastSettedGalaxyPos, LastSettedSystemPos, LastSettedPlanetPos WHERE `uni` = %d',
            $pfx,
            $universe
        );
    }

    return $lines;
}

/**
 * Pack POST tuning knobs used by preview / destruction into one array for session round-trip.
 *
 * @return array<string, mixed>
 */
function destructionPackParams(): array
{
    return [
        'universe'       => HTTP::_GP('universe', 0),
        'mode'           => HTTP::_GP('mode', 'system'),
        'galaxy'         => HTTP::_GP('galaxy', 0),
        'system'         => HTTP::_GP('system', 0),
        'relocate'       => HTTP::_GP('relocate', 0),
        'relocMode'      => HTTP::_GP('relocMode', 'random'),
        'relocGal'       => HTTP::_GP('relocGal', 0),
        'relocSys'       => HTTP::_GP('relocSys', 0),
        'relocSlot'      => HTTP::_GP('relocSlot', 0),
        'debris'         => HTTP::_GP('debris', 1),
        'debris_metal'   => HTTP::_GP('debris_metal', 1000000),
        'debris_crystal' => HTTP::_GP('debris_crystal', 0),
        'broadcast'      => HTTP::_GP('broadcast', 1),
        'message'        => HTTP::_GP('message', ''),
        'spawn_apply'    => HTTP::_GP('spawn_apply', 0),
        'spawn_galaxy'   => HTTP::_GP('spawn_galaxy', 0),
        'spawn_system'   => HTTP::_GP('spawn_system', 0),
        'spawn_planet'   => HTTP::_GP('spawn_planet', 0),
    ];
}

function destructionUnpackParams(array $p): array
{
    return [
        'universe'       => (int) $p['universe'],
        'mode'           => (string) $p['mode'],
        'galaxy'         => (int) $p['galaxy'],
        'system'         => (int) $p['system'],
        'relocate'       => (int) $p['relocate'],
        'relocMode'      => (string) $p['relocMode'],
        'relocGal'       => (int) $p['relocGal'],
        'relocSys'       => (int) $p['relocSys'],
        'relocSlot'      => (int) $p['relocSlot'],
        'debris'         => (int) $p['debris'],
        'debris_metal'   => (float) $p['debris_metal'],
        'debris_crystal' => (float) $p['debris_crystal'],
        'broadcast'      => (int) $p['broadcast'],
        'message'        => (string) $p['message'],
        'spawn_apply'    => (int) $p['spawn_apply'],
        'spawn_galaxy'   => (int) $p['spawn_galaxy'],
        'spawn_system'   => (int) $p['spawn_system'],
        'spawn_planet'   => (int) $p['spawn_planet'],
    ];
}

function destructionValidateInputs(array $p): bool
{
    return $p['universe'] > 0 && $p['galaxy'] > 0 && ($p['mode'] === 'galaxy' || $p['system'] > 0)
        && in_array($p['mode'], ['galaxy', 'system'], true);
}

function destructionPreview(int $universe, string $mode, int $galaxy, int $system): array
{
    $db = Database::get();

    // Base params shared by all queries
    $params = [':uni' => $universe, ':gal' => $galaxy];

    // Zone conditions expressed with table alias prefixes so they can be
    // embedded in JOINs and subqueries without ambiguity.
    $planetZone = 'p.universe = :uni AND p.galaxy = :gal';
    $fleetEndZone   = 'f.fleet_universe = :uni AND f.fleet_end_galaxy = :gal';
    $fleetStartZone = 'f.fleet_universe = :uni AND f.fleet_start_galaxy = :gal';

    if ($mode === 'system') {
        $params[':sys']  = $system;
        $planetZone     .= ' AND p.system = :sys';
        $fleetEndZone   .= ' AND f.fleet_end_system = :sys';
        $fleetStartZone .= ' AND f.fleet_start_system = :sys';
    }

    // 1. Planets & moons in the zone
    $planets = (int) $db->selectSingle(
        "SELECT COUNT(*) AS cnt FROM %%PLANETS%% p WHERE $planetZone",
        $params, 'cnt'
    );

    // 2. Players whose main planet (id_planet) is in the zone
    $players = (int) $db->selectSingle(
        "SELECT COUNT(*) AS cnt
         FROM %%USERS%% u
         JOIN %%PLANETS%% p ON p.id = u.id_planet
         WHERE $planetZone",
        $params, 'cnt'
    );

    // 3. Fleets lost — destination is inside the zone (no refund)
    $fleetsLost = (int) $db->selectSingle(
        "SELECT COUNT(*) AS cnt FROM %%FLEETS%% f WHERE $fleetEndZone",
        $params, 'cnt'
    );

    // 4. Fleets surviving — origin in zone, destination outside the zone
    if ($mode === 'system') {
        $notInZone = 'NOT (f.fleet_end_galaxy = :gal AND f.fleet_end_system = :sys)';
    } else {
        $notInZone = 'f.fleet_end_galaxy != :gal';
    }
    $fleetssurvive = (int) $db->selectSingle(
        "SELECT COUNT(*) AS cnt FROM %%FLEETS%% f
         WHERE $fleetStartZone AND $notInZone",
        $params, 'cnt'
    );

    // 5. Homeless players — own planets ONLY inside the zone (will have nothing left)
    if ($mode === 'system') {
        $outsideZone = 'NOT (p2.galaxy = :gal AND p2.system = :sys)';
    } else {
        $outsideZone = 'p2.galaxy != :gal';
    }
    $homeless = (int) $db->selectSingle(
        "SELECT COUNT(DISTINCT u.id) AS cnt
         FROM %%USERS%% u
         WHERE u.universe = :uni
           AND EXISTS (
               SELECT 1 FROM %%PLANETS%% p
               WHERE p.id_owner = u.id AND $planetZone
           )
           AND NOT EXISTS (
               SELECT 1 FROM %%PLANETS%% p2
               WHERE p2.id_owner = u.id AND p2.universe = :uni AND $outsideZone
           )",
        $params, 'cnt'
    );

    return [
        'planets'        => $planets,
        'players'        => $players,
        'fleets_lost'    => $fleetsLost,
        'fleets_survive' => $fleetssurvive,
        'homeless'       => $homeless,
    ];
}

function ShowDestructionPage()
{
    global $LNG, $USER;

    $template = new Template();

    $action = HTTP::_GP('action', '');

    if ($action === 'cancel_preview' || $action === 'cancel_review') {
        unset($_SESSION[DESTRUCTION_SESSION_KEY]);
        HTTP::redirectTo('admin.php?page=destruction&sid=' . session_id());
    }

    $packed = destructionPackParams();
    $p      = destructionUnpackParams($packed);

    $universeList = Universe::availableUniverses();
    // Single-universe installs: preselect the only universe so preview is one step simpler.
    if ($action === '' && $p['universe'] < 1 && count($universeList) === 1) {
        $only              = (int) reset($universeList);
        $p['universe']     = $only;
        $packed['universe'] = $only;
    }

    $preview         = null;
    $result          = null;
    $reviewStage     = false;
    $sqlPreviewLines = [];
    $reviewToken     = '';
    $backupPath      = null;
    $previewError    = null;

    // --- Step A: accept preview → execution review (session token + SQL summary) ---
    if ($action === 'accept_review' && destructionValidateInputs($p)) {
        $previewTry = destructionPreview($p['universe'], $p['mode'], $p['galaxy'], $p['system']);
        if ($previewTry['planets'] === 0) {
            $template->message($LNG['dest_preview_empty'], 'admin.php?page=destruction&sid=' . session_id(), 5, true);
            exit;
        }
        $reviewToken = bin2hex(random_bytes(16));
        $_SESSION[DESTRUCTION_SESSION_KEY] = [
            'token'  => $reviewToken,
            'params' => $packed,
        ];
        $preview           = $previewTry;
        $reviewStage       = true;
        $sqlPreviewLines   = destructionSqlPreviewLines(
            $p['universe'],
            $p['mode'],
            $p['galaxy'],
            $p['system'],
            $p['relocate'],
            (bool) $p['spawn_apply'],
            $p['debris'],
            $p['broadcast'],
            (float) $p['debris_metal'],
            (float) $p['debris_crystal']
        );
    }

    // --- Preview counts only ---
    if ($action === 'preview') {
        if (destructionValidateInputs($p)) {
            $preview = destructionPreview($p['universe'], $p['mode'], $p['galaxy'], $p['system']);
        } else {
            $previewError = $LNG['dest_preview_invalid'];
        }
    }

    // --- Execute (requires prior accept_review token) ---
    if ($action === 'destroy') {
        $reviewTokenPost = HTTP::_GP('review_token', '');
        $sess            = $_SESSION[DESTRUCTION_SESSION_KEY] ?? null;
        if (!is_array($sess) || empty($sess['token']) || empty($sess['params'])
            || !hash_equals((string) $sess['token'], (string) $reviewTokenPost)) {
            $template->message($LNG['dest_review_expired'], 'admin.php?page=destruction&sid=' . session_id(), 5, true);
            exit;
        }
        unset($_SESSION[DESTRUCTION_SESSION_KEY]);

        $p = destructionUnpackParams($sess['params']);
        if (!destructionValidateInputs($p)) {
            $template->message($LNG['dest_review_expired'], 'admin.php?page=destruction&sid=' . session_id(), 5, true);
            exit;
        }

        $universe      = $p['universe'];
        $mode          = $p['mode'];
        $galaxy        = $p['galaxy'];
        $system        = $p['system'];
        $relocate      = $p['relocate'];
        $relocMode     = $p['relocMode'];
        $relocGal      = $p['relocGal'];
        $relocSys      = $p['relocSys'];
        $debris        = $p['debris'];
        $debrisMetal   = (float) $p['debris_metal'];
        $debrisCrystal = (float) $p['debris_crystal'];
        $broadcast     = $p['broadcast'];
        $message       = $p['message'];

        $backupBefore = HTTP::_GP('backup_before', 1);

        try {
            if ($backupBefore) {
                $backupPath = runDestructionDatabaseBackup();
            }

            if ($p['spawn_apply']) {
                if ($p['spawn_galaxy'] < 1 || $p['spawn_system'] < 1 || $p['spawn_planet'] < 1) {
                    throw new Exception($LNG['dest_spawn_incomplete']);
                }
                applySpawnCursor($universe, $p['spawn_galaxy'], $p['spawn_system'], $p['spawn_planet']);
            }

            $fleetResult = executeFleets($universe, $mode, $galaxy, $system);

            $relocResult = executeRelocation(
                $universe, $mode, $galaxy, $system,
                $relocate, $relocMode, $relocGal, $relocSys
            );

            $planetResult = executePlanets(
                $universe, $mode, $galaxy, $system,
                $debris, $debrisMetal, $debrisCrystal
            );

            executeNotifications(
                $universe, $mode, $galaxy, $system,
                $planetResult['affectedUserIds'], $message, $broadcast,
                $USER['username']
            );

            executeLog(
                $universe, $mode, $galaxy, $system,
                (int) $USER['id'], $planetResult['count'],
                $fleetResult, $relocResult,
                $debrisMetal, $debrisCrystal, $broadcast,
                $backupPath
            );

            $result = [
                'planets'         => $planetResult['count'],
                'fleets_lost'     => $fleetResult['lost'],
                'fleets_survived' => $fleetResult['survived'],
                'relocated'       => $relocResult['relocated'],
                'skipped'         => $relocResult['skipped'],
                'backup_path'     => $backupPath,
            ];
        } catch (Throwable $e) {
            $template->message(
                $LNG['dest_exec_failed'] . ': ' . htmlspecialchars($e->getMessage()),
                'admin.php?page=destruction&sid=' . session_id(),
                10,
                true
            );
            exit;
        }
    }

    $template->assign_vars([
        'SID'               => session_id(),
        'universeList'      => $universeList,
        'action'            => $action,
        'universe'          => $p['universe'],
        'mode'              => $p['mode'],
        'galaxy'            => $p['galaxy'],
        'system'            => $p['system'],
        'relocate'          => $p['relocate'],
        'relocMode'         => $p['relocMode'],
        'relocGal'          => $p['relocGal'],
        'relocSys'          => $p['relocSys'],
        'relocSlot'         => $p['relocSlot'],
        'debris'            => $p['debris'],
        'debris_metal'      => $p['debris_metal'],
        'debris_crystal'    => $p['debris_crystal'],
        'broadcast'         => $p['broadcast'],
        'message'           => $p['message'],
        'spawn_apply'       => $p['spawn_apply'],
        'spawn_galaxy'      => $p['spawn_galaxy'],
        'spawn_system'      => $p['spawn_system'],
        'spawn_planet'      => $p['spawn_planet'],
        'preview'           => $preview,
        'result'            => $result,
        'reviewStage'       => $reviewStage,
        'sqlPreviewLines'   => $sqlPreviewLines,
        'reviewToken'       => $reviewToken,
        'backup_default_on' => 1,
        'preview_error'     => $previewError,
    ]);

    $template->show('DestructionPage.tpl');
}
