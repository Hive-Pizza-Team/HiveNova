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
use HiveNova\Core\Universe;

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

$gameName = trim((string) $config->game_name);
if ($gameName === '') {
	$gameName = 'HiveNova';
}

$shortName = $gameName;
if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($gameName, 'UTF-8') > 12) {
	$shortName = mb_substr($gameName, 0, 12, 'UTF-8');
} elseif (strlen($gameName) > 12) {
	$shortName = substr($gameName, 0, 12);
}

$manifest = array(
	'name'             => $gameName,
	'short_name'       => $shortName,
	'description'      => $gameName . ' — space empire browser game',
	'start_url'        => '/game.php?page=overview',
	'scope'            => '/',
	'display'          => 'standalone',
	'background_color' => '#1a1a2e',
	'theme_color'      => '#1a1a2e',
	'orientation'      => 'any',
	'icons'            => array(
		array(
			'src'   => '/favicon.ico',
			'sizes' => '64x64',
			'type'  => 'image/x-icon',
		),
	),
);

HTTP::sendHeader('Content-Type', 'application/manifest+json; charset=UTF-8');
HTTP::sendHeader('Cache-Control', 'public, max-age=3600');

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
