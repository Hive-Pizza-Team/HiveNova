<?php

/**
 * Verifies mobile bottom navigation markup on ingame pages.
 *
 * Usage:
 *   php tests/check-bottom-nav.php [base-url] [username] [password]
 */

$baseUrl  = $argv[1] ?? getenv('SMOKE_BASE_URL') ?: 'http://localhost:8000';
$username = $argv[2] ?? getenv('ADMIN_NAME')     ?: 'spacepizzadev';
$password = $argv[3] ?? getenv('ADMIN_PASSWORD') ?: '2hBR2wC0BcS^A%vsLvw9XgXy5$aBF*';

$baseUrl = rtrim($baseUrl, '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'bottom_nav_cookies_');

function curl_post(string $url, array $fields, string $cookieFile): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}

function curl_get(string $url, string $cookieFile): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}

curl_post("$baseUrl/index.php?page=login", [
    'username' => $username,
    'password' => $password,
], $cookieFile);

$fullPages = [
    'overview', 'resources', 'buildings', 'research', 'shipyard',
    'fleet', 'fleettable', 'fleetTable', 'fleetdealer', 'galaxy',
    'alliance', 'messages', 'statistics', 'search', 'settings',
    'imperium', 'marketplace', 'trader', 'officier', 'techtree',
    'battlesimulator', 'battlehall', 'records', 'changelog', 'chat',
    'buddylist', 'banlist', 'board', 'questions', 'ticket', 'viz',
];

$popupPages = [
    ['page' => 'messages', 'mode' => 'write'],
    ['page' => 'raport', 'mode' => 'show'],
    ['page' => 'notes', 'mode' => 'show'],
    ['page' => 'information', 'mode' => 'show', 'id' => '1'],
    ['page' => 'playerCard', 'id' => '1'],
    ['page' => 'buddyList', 'mode' => 'request', 'id' => '1'],
    ['page' => 'overview', 'mode' => 'actions'],
];

$fail = 0;

echo "=== Bottom nav markup check ===\n";
echo "Base URL: $baseUrl\n\n";

foreach ($fullPages as $page) {
    $body = curl_get("$baseUrl/game.php?page=$page", $cookieFile);
    if (strpos($body, 'id="bottom-nav"') === false) {
        echo "[ FAIL ] full layout missing bottom-nav: $page\n";
        $fail++;
    }
}

foreach ($popupPages as $query) {
    $label = http_build_query($query);
    $body = curl_get("$baseUrl/game.php?$label", $cookieFile);
    if (strpos($body, 'id="bottom-nav"') === false) {
        echo "[ FAIL ] popup layout missing bottom-nav: $label\n";
        $fail++;
    }
}

if ($fail === 0) {
    echo "All checked pages include #bottom-nav.\n";
    exit(0);
}

echo "\n$fail page(s) missing bottom navigation.\n";
exit(1);
