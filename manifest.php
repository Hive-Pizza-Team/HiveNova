<?php

/**
 * Dynamic PWA manifest — name/short_name from universe config (game_name).
 */

define('MODE', 'MANIFEST');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)) . '/');
set_include_path(ROOT_PATH);

require 'includes/common.php';

use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\PwaManifestService;
use HiveNova\Core\Universe;

require_once ROOT_PATH.'includes/classes/PwaManifestService.php';

$uni = (int) HTTP::_GP('uni', 0);
if ($uni < 1 && isset($_COOKIE['uni'])) {
	$uni = (int) $_COOKIE['uni'];
}

if ($uni > 0 && !Universe::exists($uni)) {
	HTTP::sendHeader('HTTP/1.1 404 Not Found');
	HTTP::sendHeader('Content-Type', 'application/manifest+json; charset=UTF-8');
	echo json_encode(array('error' => 'Unknown universe'), JSON_UNESCAPED_UNICODE);
	exit;
}

$config = $uni > 0 ? Config::get($uni) : Config::get();
$httpRoot = defined('HTTP_ROOT') ? HTTP_ROOT : '/';
$manifest = (new PwaManifestService())->build((string) $config->game_name, $httpRoot);

HTTP::sendHeader('Content-Type', 'application/manifest+json; charset=UTF-8');
HTTP::sendHeader('Cache-Control', 'public, max-age=3600');

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
