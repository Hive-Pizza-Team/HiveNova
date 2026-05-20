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

/**
 * @return array{0: string, 1: string} body and final URL after redirects
 */
function curl_get(string $url, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    curl_close($ch);
    return [$body ?: '', $effectiveUrl];
}

function isIngameGameUrl(string $baseUrl, string $url): bool
{
    $prefix = rtrim($baseUrl, '/') . '/game.php';
    return str_starts_with($url, $prefix);
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
    $url = "$baseUrl/game.php?page=$page";
    [$body, $effectiveUrl] = curl_get($url, $cookieFile);
    if (!isIngameGameUrl($baseUrl, $effectiveUrl)) {
        echo "[ SKIP ] full layout leaves ingame: $page → $effectiveUrl\n";
        continue;
    }
    if (strpos($body, 'id="bottom-nav"') === false) {
        echo "[ FAIL ] full layout missing bottom-nav: $page\n";
        $fail++;
    }
}

foreach ($popupPages as $query) {
    $label = http_build_query($query);
    $url = "$baseUrl/game.php?$label";
    [$body, $effectiveUrl] = curl_get($url, $cookieFile);
    if (!isIngameGameUrl($baseUrl, $effectiveUrl)) {
        echo "[ SKIP ] popup layout leaves ingame: $label → $effectiveUrl\n";
        continue;
    }
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
