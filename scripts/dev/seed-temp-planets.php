<?php
/**
 * Seed one planet per temperature slot for the spacepizzadev dev account.
 *
 * Creates colonies at galaxy 1, system 399, positions 1–15 (game temp bands).
 * Safe to re-run: skips occupied slots, renames existing dev planets.
 *
 * Usage:
 *   php scripts/dev/seed-temp-planets.php
 *   php scripts/dev/seed-temp-planets.php --user=spacepizzadev --galaxy=1 --system=399
 *   php scripts/dev/seed-temp-planets.php --dry-run
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('MODE', 'CLI');
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__, 2)) . '/');
define('TIMESTAMP', time());
chdir(ROOT_PATH);
set_include_path(ROOT_PATH);

require 'includes/constants.php';
require 'includes/config.php';
require 'includes/dbtables.php';
require 'vendor/autoload.php';
require 'includes/GeneralFunctions.php';

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;

Database::get();
require 'includes/vars.php';

$options = getopt('', ['user:', 'galaxy:', 'system:', 'universe:', 'dry-run', 'buildup:']);
$username = $options['user'] ?? 'spacepizzadev';
$galaxy = (int) ($options['galaxy'] ?? 1);
$system = (int) ($options['system'] ?? 399);
$universe = (int) ($options['universe'] ?? 1);
$dryRun = array_key_exists('dry-run', $options);
$buildup = max(0, min(1, (float) ($options['buildup'] ?? 0.53)));

$slotNames = [
    1  => 'Dev Lava',
    2  => 'Dev Hot Desert',
    3  => 'Dev Warm Desert',
    4  => 'Dev Jungle',
    5  => 'Dev Jungle Belt',
    6  => 'Dev Green Belt',
    7  => 'Dev Temperate',
    8  => 'Dev Mild',
    9  => 'Dev Coast',
    10 => 'Dev Oceanic',
    11 => 'Dev Cool',
    12 => 'Dev Chilly',
    13 => 'Dev Frost',
    14 => 'Dev Ice',
    15 => 'Dev Deep Ice',
];

$db = Database::get();

$user = $db->selectSingle(
    'SELECT id, username FROM %%USERS%% WHERE username = :username AND universe = :universe LIMIT 1',
    [':username' => $username, ':universe' => $universe]
);

if (empty($user)) {
    fwrite(STDERR, "User not found: {$username} (universe {$universe})\n");
    exit(1);
}

$userId = (int) $user['id'];
$config = Config::get($universe);
$maxPlanet = (int) $config->max_planets;

echo ($dryRun ? '[dry-run] ' : '') . "Seeding temp planets for {$username} (id {$userId})\n";
echo "Target: [{$galaxy}:{$system}:1-{$maxPlanet}] universe {$universe}\n";
echo "Buildup fill: " . round($buildup * 100) . "%\n\n";

require 'includes/PlanetData.php';
$planetDataCount = count($planetData);

$created = 0;
$skipped = 0;
$updated = 0;

for ($position = 1; $position <= $maxPlanet; $position++) {
    $name = $slotNames[$position] ?? ('Dev Temp ' . $position);

    $existing = $db->selectSingle(
        'SELECT id, id_owner, name FROM %%PLANETS%%
         WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system
         AND planet = :planet AND planet_type = 1 AND destruyed = 0',
        [
            ':universe' => $universe,
            ':galaxy'   => $galaxy,
            ':system'   => $system,
            ':planet'   => $position,
        ]
    );

    if (!empty($existing)) {
        if ((int) $existing['id_owner'] === $userId) {
            $dataIndex = (int) ceil($position / ($maxPlanet / $planetDataCount));
            $maxFields = (int) floor($planetData[$dataIndex]['fields'] * $config->planet_factor);
            $fieldCurrent = (int) round($maxFields * $buildup);

            echo "  [{$galaxy}:{$system}:{$position}] exists — {$existing['name']} (id {$existing['id']})\n";

            if (!$dryRun && ($existing['name'] !== $name || $fieldCurrent > 0)) {
                $db->update(
                    'UPDATE %%PLANETS%% SET name = :name, field_current = :current WHERE id = :id',
                    [':name' => $name, ':current' => $fieldCurrent, ':id' => $existing['id']]
                );
                $updated++;
            }
            $skipped++;
            continue;
        }

        echo "  [{$galaxy}:{$system}:{$position}] SKIP — occupied by another player (id {$existing['id']})\n";
        $skipped++;
        continue;
    }

    echo "  [{$galaxy}:{$system}:{$position}] CREATE — {$name}\n";

    if ($dryRun) {
        $created++;
        continue;
    }

    try {
        $planetId = PlayerUtil::createPlanet($galaxy, $system, $position, $universe, $userId, $name, false, 0);
        $dataIndex = (int) ceil($position / ($maxPlanet / $planetDataCount));
        $maxFields = (int) floor($planetData[$dataIndex]['fields'] * $config->planet_factor);
        $fieldCurrent = (int) round($maxFields * $buildup);

        $db->update(
            'UPDATE %%PLANETS%% SET field_current = :current WHERE id = :id',
            [':current' => $fieldCurrent, ':id' => $planetId]
        );
        $created++;
    } catch (Exception $e) {
        fwrite(STDERR, "    ERROR: " . $e->getMessage() . "\n");
    }
}

echo "\nDone. created={$created} updated={$updated} skipped={$skipped}\n";
echo "Gallery: http://localhost:8000/scripts/dev/planet-temp-gallery.html\n";
echo "In-game: switch colonies at [{$galaxy}:{$system}:1-{$maxPlanet}]\n";
