<?php

/**
 * One-off: wipe seasonal progress for a universe (keeps accounts / Hive links).
 *
 * Usage (from repo root on the game host, after this PR is deployed):
 *   php scripts/oneoff-season-wipe.php 3
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

$universe = isset($argv[1]) ? (int) $argv[1] : 0;
if ($universe < 1) {
	fwrite(STDERR, "Usage: php scripts/oneoff-season-wipe.php <universeId>\n");
	exit(1);
}

define('MODE', 'CLI');
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
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
use HiveNova\Core\SeasonWipeService;

Database::get();
require 'includes/vars.php';

$config = Config::get($universe);
SeasonWipeService::fromGlobals(null, null, $config)->wipe($universe, $config);
$config->save();

fwrite(STDOUT, "Wiped universe {$universe}.\n");
