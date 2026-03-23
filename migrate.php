<?php
/**
 * CLI Migration Tool for HiveNova
 *
 * Usage:
 *   php migrate.php status       - Show current DB version and pending migrations
 *   php migrate.php run          - Apply all pending migrations
 *   php migrate.php run --dry-run - Preview migrations without applying
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)) . '/');
chdir(ROOT_PATH);
set_include_path(ROOT_PATH);

$command = $argv[1] ?? 'status';
$dryRun  = in_array('--dry-run', $argv, true);

if (!file_exists('includes/config.php') || filesize('includes/config.php') === 0) {
    die("Error: includes/config.php not found or empty. Run the web installer first.\n");
}

$database = [];
require 'includes/config.php';
require 'includes/dbtables.php';
require 'vendor/autoload.php';

use HiveNova\Core\Migrator;

try {
    $pdo = new PDO(
        "mysql:host={$database['host']};port={$database['port']};dbname={$database['databasename']}",
        $database['user'],
        $database['userpw'],
        [PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET utf8mb4, NAMES utf8mb4, sql_mode = 'STRICT_ALL_TABLES'"]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

$migrator       = new Migrator($pdo, ROOT_PATH . 'install/migrations', DB_PREFIX, DB_VERSION_REQUIRED);
$currentVersion = $migrator->getCurrentVersion();

if ($command === 'status') {
    $pending = $migrator->getPendingMigrations($currentVersion);
    echo "Current DB version : {$currentVersion}\n";
    echo "Required DB version: " . DB_VERSION_REQUIRED . "\n";
    if (empty($pending)) {
        echo "Status             : Up to date\n";
    } else {
        echo "Status             : " . count($pending) . " migration(s) pending\n";
        echo "\nPending migrations:\n";
        foreach ($pending as $m) {
            echo "  [{$m['rev']}] {$m['filename']}\n";
        }
    }
    exit(0);
}

if ($command === 'run') {
    $pending = $migrator->getPendingMigrations($currentVersion);

    if (empty($pending)) {
        echo "Already up to date (version {$currentVersion}).\n";
        exit(0);
    }

    if ($dryRun) {
        echo "Dry run — no changes will be made.\n\n";
        foreach ($pending as $m) {
            echo "[{$m['rev']}] {$m['filename']}\n";
            if ($m['extension'] === 'sql') {
                $sql = file_get_contents($m['path']);
                foreach ($migrator->parseSql($sql) as $q) {
                    echo "  " . substr(preg_replace('/\s+/', ' ', $q), 0, 80)
                        . (strlen($q) > 80 ? '...' : '') . "\n";
                }
            } else {
                echo "  (PHP migration)\n";
            }
        }
        echo "\nDry run complete. No changes made.\n";
        exit(0);
    }

    echo "Applying " . count($pending) . " migration(s)...\n\n";
    foreach ($pending as $m) {
        echo "[{$m['rev']}] {$m['filename']} ... ";
        match ($m['extension']) {
            'sql' => $migrator->applySqlMigration($m),
            'php' => $migrator->applyPhpMigration($m),
        };
        echo "OK\n";
    }
    $migrator->updateVersion();
    echo "\nDB version updated to " . DB_VERSION_REQUIRED . ".\n";
    exit(0);
}

echo "Unknown command: {$command}\n";
echo "Usage: php migrate.php [status|run] [--dry-run]\n";
exit(1);
