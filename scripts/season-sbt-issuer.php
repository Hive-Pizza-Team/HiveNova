<?php

/**
 * Dry-run / record-only issuer for Uni3 prestige SBTs (#628 / #630).
 *
 * Does NOT broadcast Hive custom_json. Use --dry-run to print payloads, or
 * --record-only after an offline mint to write season_sbt_grants rows.
 *
 * Usage:
 *   php scripts/season-sbt-issuer.php --csv=path.csv --season=u3-s1 --namespace=sablephalanx --symbol=MOONU3S1 --dry-run
 *   php scripts/season-sbt-issuer.php --csv=path.csv --season=u3-s1 --namespace=sablephalanx --symbol=MOONU3S1 --record-only --retro
 *
 * CSV headers (minimum): hive_user,tier
 * Optional: rank,feat_id,user_id,game_name,points,image_url,instance_id,trx_id
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

$opts = getopt('', [
	'csv:',
	'season:',
	'namespace:',
	'symbol:',
	'universe:',
	'recap:',
	'image:',
	'window-start:',
	'window-end:',
	'dry-run',
	'record-only',
	'retro',
	'help',
]);

if (isset($opts['help']) || !isset($opts['csv'], $opts['season'], $opts['namespace'], $opts['symbol'])) {
	fwrite(STDOUT, "Usage: php scripts/season-sbt-issuer.php --csv=file.csv --season=u3-s1 --namespace=ns --symbol=MOONU3S1 [--dry-run|--record-only] [--retro]\n");
	exit(isset($opts['help']) ? 0 : 1);
}

$modeDry = isset($opts['dry-run']);
$modeRecord = isset($opts['record-only']);
if ($modeDry === $modeRecord) {
	// Default to dry-run when neither/both set.
	$modeDry = true;
	$modeRecord = false;
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

use HiveNova\Core\ArraySeasonSbtGrantStore;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseSeasonSbtGrantStore;
use HiveNova\Core\SeasonSbtIssuerService;

$csvPath = (string) $opts['csv'];
if (!is_file($csvPath) || !is_readable($csvPath)) {
	fwrite(STDERR, "CSV not readable: {$csvPath}\n");
	exit(1);
}

$csv = file_get_contents($csvPath);
if (!is_string($csv)) {
	fwrite(STDERR, "Failed to read CSV.\n");
	exit(1);
}

if ($modeRecord) {
	Database::get();
	require 'includes/vars.php';
	$store = new DatabaseSeasonSbtGrantStore();
	if (!DatabaseSeasonSbtGrantStore::isSchemaReady()) {
		fwrite(STDERR, "season_sbt_grants schema not ready (migrate to DB version 51).\n");
		exit(1);
	}
} else {
	$store = new ArraySeasonSbtGrantStore();
}

$issuer = new SeasonSbtIssuerService($store);
$rows = SeasonSbtIssuerService::parseCsv($csv);
$plan = $issuer->plan($rows, [
	'season_id'     => (string) $opts['season'],
	'nft_namespace' => (string) $opts['namespace'],
	'nft_symbol'    => (string) $opts['symbol'],
	'universe'      => isset($opts['universe']) ? (int) $opts['universe'] : 0,
	'retroactive'   => isset($opts['retro']),
	'recap_url'     => (string) ($opts['recap'] ?? ''),
	'image_url'     => (string) ($opts['image'] ?? ''),
	'window_start'  => (string) ($opts['window-start'] ?? ''),
	'window_end'    => (string) ($opts['window-end'] ?? ''),
	'issued_at'     => TIMESTAMP,
], $modeRecord);

$out = [
	'mode'     => $modeRecord ? 'record-only' : 'dry-run',
	'count'    => count($plan['payloads']),
	'recorded' => $plan['recorded'],
	'skipped'  => $plan['skipped'],
	'payloads' => array_map(static function (array $row): array {
		return [
			'hive_user'   => $row['hive_user'],
			'custom_json' => $row['custom_json'],
			'id'          => 'NFT',
			'required_auths_note' => 'Broadcast with house active key offline; this script never signs.',
		];
	}, $plan['payloads']),
];

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(0);
