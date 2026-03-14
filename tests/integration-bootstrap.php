<?php

if (php_sapi_name() !== 'cli') die("CLI only\n");

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
chdir(ROOT_PATH);
set_include_path(ROOT_PATH);

// Fail fast if DB hasn't been set up
if (!file_exists(ROOT_PATH . 'includes/config.php')) {
    die("Missing includes/config.php — run tests/ci-install.php first\n");
}

define('MODE', 'INSTALL');
@require ROOT_PATH . 'includes/common.php';
restore_exception_handler();
restore_error_handler();

// Game data fixtures (same as unit-test bootstrap)
require_once __DIR__ . '/fixtures/game_data.php';

// Additional classes needed by integration tests
require_once ROOT_PATH . 'includes/classes/ResourceUpdate.php';

// Load full $reslist / $resource / $pricelist / $ProdGrid from the live DB
// (vars.php uses Cache::get() which requires a running DB connection)
require ROOT_PATH . 'includes/vars.php';

// Base class for all integration tests
require_once __DIR__ . '/Integration/IntegrationTestCase.php';

// Fleet/mission constants not defined by common.php in INSTALL mode
if (!defined('MIN_FLEET_TIME')) define('MIN_FLEET_TIME', 10);
if (!defined('FLEET_OUTWARD'))  define('FLEET_OUTWARD',  0);
if (!defined('FLEET_RETURN'))   define('FLEET_RETURN',   1);
if (!defined('FLEET_HOLD'))     define('FLEET_HOLD',     2);
