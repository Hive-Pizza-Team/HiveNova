<?php
/**
 * CI Installer — programmatically installs HiveNova for smoke testing.
 *
 * Replicates what the web wizard does in steps 4, 6, and 8:
 *   1. Write includes/config.php from env vars
 *   2. Execute install/install.sql against the DB
 *   3. Create the admin/test user via PlayerUtil::createPlayer
 *   4. Apply any pending DB migrations
 *
 * Required env vars:
 *   DB_HOST, DB_USER, DB_PASSWORD, DB_NAME
 *   ADMIN_NAME, ADMIN_PASSWORD, ADMIN_MAIL
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

define('ROOT_PATH', str_replace('\\', '/', dirname(dirname(__FILE__))) . '/');
chdir(ROOT_PATH);
set_include_path(ROOT_PATH);

// --- 1. Read env vars ---
$dbHost    = getenv('DB_HOST')        ?: '127.0.0.1';
$dbPort    = getenv('DB_PORT')        ?: '3306';
$dbUser    = getenv('DB_USER')        ?: '';
$dbPass    = getenv('DB_PASSWORD')    ?: '';
$dbName    = getenv('DB_NAME')        ?: '';
$adminName = getenv('ADMIN_NAME')     ?: '';
$adminPass = getenv('ADMIN_PASSWORD') ?: '';
$adminMail = getenv('ADMIN_MAIL')     ?: '';

foreach (['DB_USER' => $dbUser, 'DB_NAME' => $dbName, 'ADMIN_NAME' => $adminName,
          'ADMIN_PASSWORD' => $adminPass, 'ADMIN_MAIL' => $adminMail] as $var => $val) {
    if ($val === '') {
        die("Error: environment variable $var is required.\n");
    }
}

// --- 2. Write includes/config.php ---
// Must happen before bootstrapping so common.php can connect to the DB.
$blowfish = substr(str_shuffle('./0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 22);
$configContent = sprintf(
    file_get_contents(ROOT_PATH . 'includes/config.sample.php'),
    $dbHost, $dbPort, $dbUser, $dbPass, $dbName, 'uni1_', $blowfish
);
if (file_put_contents(ROOT_PATH . 'includes/config.php', $configContent) === false) {
    die("Error: cannot write includes/config.php\n");
}

// --- Bootstrap the game framework ---
// common.php calls header() internally; suppress any header-related warnings
// by bootstrapping before any output has been sent, and silencing with @.
define('MODE', 'INSTALL');
@require ROOT_PATH . 'includes/common.php';

// common.php installs a custom exception handler that outputs HTML and hides
// the real error message. Restore the default so failures are visible in CI.
restore_exception_handler();
restore_error_handler();

// Open a raw PDO connection for bulk SQL execution (PDO::exec does not reliably
// handle multi-statement strings; we split and execute statement by statement).
$pdo = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}",
    $dbUser,
    $dbPass,
    [PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET utf8mb4, NAMES utf8mb4"]
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// dbtables.php defines DB_PREFIX, DB_VERSION_REQUIRED, and the table-name
// constants. Normally loaded inside Database::__construct(), but we use a
// raw PDO so we must require it directly.
$database = [];
require ROOT_PATH . 'includes/config.php';
require ROOT_PATH . 'includes/dbtables.php';

try {
// Now it is safe to produce output.
echo "=== HiveNova CI Installer ===\n";
echo "DB   : $dbUser@$dbHost:$dbPort/$dbName\n";
echo "Admin: $adminName <$adminMail>\n\n";
echo "[ 1/4 ] Writing includes/config.php ... OK\n";

// --- 3. Execute install.sql ---
echo "[ 2/4 ] Executing install/install.sql ... ";

$installSQL      = file_get_contents(ROOT_PATH . 'install/install.sql');
$installVersion  = trim(file_get_contents(ROOT_PATH . 'install/VERSION'));
$installRevision = 0;

preg_match('!\$' . 'Id: install.sql ([0-9]+)!', $installSQL, $match);
$versionParts = explode('.', $installVersion);
if (isset($match[1])) {
    $installRevision = (int)$match[1];
    $versionParts[2] = $installRevision;
} else {
    $installRevision = (int)($versionParts[2] ?? 0);
}
$installVersion = implode('.', $versionParts);

$installSQL = str_replace(
    ['%PREFIX%', '%VERSION%', '%REVISION%', '%DB_VERSION%'],
    ['uni1_', $installVersion, $installRevision, DB_VERSION_REQUIRED],
    $installSQL
);

// Split on ";\n" (same strategy as Migrator::parseSql) and execute individually.
$statements = array_filter(array_map('trim', explode(";\n", $installSQL . "\n")));
foreach ($statements as $stmt) {
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        // Skip non-fatal errors (e.g. conditional SET directives).
        $msg = $e->getMessage();
        // Rethrow genuine table/data errors.
        if (!str_contains($msg, '1231') && !str_contains($msg, 'Variable')) {
            throw $e;
        }
    }
}

// Set basic config values (mirrors install/index.php step 6).
// Use Database::get() now that the schema exists.
$config = \HiveNova\Core\Config::get(Universe::current());
$config->timezone         = @date_default_timezone_get();
$config->lang             = 'en';
$config->OverviewNewsText = 'Welcome to HiveNova ' . $installVersion;
$config->uni_name         = 'Universe ' . Universe::current();
$config->close_reason     = 'Maintenance';
$config->moduls           = implode(';', array_fill(0, MODULE_AMOUNT - 1, 1));
$config->save();

echo "OK\n";

// --- 4. Create admin user ---
echo "[ 3/4 ] Creating admin user '$adminName' ... ";

require ROOT_PATH . 'includes/vars.php';
$hashPassword = \HiveNova\Core\PlayerUtil::cryptPassword($adminPass);
\HiveNova\Core\PlayerUtil::createPlayer(
    Universe::current(),
    $adminName,
    $hashPassword,
    $adminMail,
    '',       // hiveaccount
    'en',     // language
    1,        // galaxy
    1,        // system
    2,        // position
    null,     // referrer
    AUTH_ADM  // authority
);
echo "OK\n";

// --- 5. Apply pending migrations ---
echo "[ 4/4 ] Running migrations ... ";

$migrator       = new \HiveNova\Core\Migrator($pdo, ROOT_PATH . 'install/migrations', DB_PREFIX, DB_VERSION_REQUIRED);
$currentVersion = $migrator->getCurrentVersion();
$pending        = $migrator->getPendingMigrations($currentVersion);

if (empty($pending)) {
    echo "already up to date (v{$currentVersion})\n";
} else {
    foreach ($pending as $m) {
        match ($m['extension']) {
            'sql' => $migrator->applySqlMigration($m),
            'php' => $migrator->applyPhpMigration($m),
        };
    }
    $migrator->updateVersion();
    echo "applied " . count($pending) . " migration(s), now v" . DB_VERSION_REQUIRED . "\n";
}

echo "\n=== Install complete ===\n";

} catch (Throwable $e) {
    fwrite(STDERR, "\nFATAL: " . get_class($e) . ': ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
exit(0);
