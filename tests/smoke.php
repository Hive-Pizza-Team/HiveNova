<?php

/**
 * Smoke test: logs into a HiveNova instance and hits all game pages,
 * reporting HTTP status and any PHP errors/warnings found in the response.
 *
 * Usage:
 *   php tests/smoke.php [base-url] [username] [password]
 *
 * Arguments override env vars (SMOKE_BASE_URL, ADMIN_NAME, ADMIN_PASSWORD),
 * which in turn override the built-in local-dev defaults.
 *
 * Examples:
 *   php tests/smoke.php                                      # local dev
 *   php tests/smoke.php https://staging.moon.hive.pizza admin s3cr3t
 *   SMOKE_BASE_URL=https://staging.moon.hive.pizza php tests/smoke.php
 */

$baseUrl  = $argv[1] ?? getenv('SMOKE_BASE_URL') ?: 'http://localhost:8000';
$username = $argv[2] ?? getenv('ADMIN_NAME')     ?: 'spacepizzadev';
$password = $argv[3] ?? getenv('ADMIN_PASSWORD') ?: '2hBR2wC0BcS^A%vsLvw9XgXy5$aBF*';

$baseUrl = rtrim($baseUrl, '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'smoke_cookies_');

// Pages to test — derived from Show{Name}Page.php files.
// Skipping pages that require POST/special state or are AJAX-only:
//   FleetAjax (AJAX), FleetStep2/3 (multi-step form), FleetMissile (POST),
//   Logout (would end the session), PlayerCard (needs id param)
$pages = [
    'overview',
    'resources',
    'buildings',
    'research',
    'shipyard',
    'fleet',        // FleetStep1
    'fleettable',
    'fleetdealer',
    'galaxy',
    'alliance',
    'messages',
    'raport',
    'statistics',
    'search',
    'notes',
    'settings',
    'imperium',
    'information',
    'marketplace',
    'trader',
    'officier',
    'techtree',
    'battlesimulator',
    'battlehall',
    'records',
    'changelog',
    'chat',
    'buddylist',
    'banlist',
    'board',
    'questions',
    'ticket',
    'phalanx',
    'viz',
];

$pass = 0;
$fail = 0;
$warn = 0;

function curl_get(string $url, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);
    return [$status, $body ?: '', $error];
}

function curl_post(string $url, array $fields, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);
    return [$status, $body ?: '', $error];
}

function detectErrors(string $body): array {
    $issues = [];
    // PHP fatal/warning/notice patterns
    if (preg_match('/<b>(Fatal error|Parse error)<\/b>/i', $body)) {
        $issues[] = 'PHP Fatal/Parse error';
    }
    if (preg_match('/<b>(Warning|Notice|Deprecated)<\/b>/i', $body)) {
        $issues[] = 'PHP Warning/Notice';
    }
    // Plain-text errors (CLI-style, or display_errors=1 without HTML)
    if (preg_match('/\b(Fatal error|Parse error):/i', $body)) {
        $issues[] = 'PHP Fatal/Parse error (plain)';
    }
    if (preg_match('/\bPHP (Warning|Notice):/i', $body) || preg_match('/(Warning|Notice): \S+::/i', $body)) {
        $issues[] = 'PHP Warning/Notice (plain)';
    }
    // Uncaught exceptions
    if (stripos($body, 'Uncaught') !== false) {
        $issues[] = 'Uncaught exception';
    }
    // Stack traces
    if (stripos($body, 'Stack trace') !== false) {
        $issues[] = 'Stack trace';
    }
    // HiveNova custom exception handler page (exceptionHandler in GeneralFunctions.php)
    // This page may return HTTP 200 if headers were already sent when the exception occurred
    if (stripos($body, '<th>Unknown error</th>') !== false
        || stripos($body, '<b>Debug Backtrace:</b>') !== false
        || preg_match('/<b>Message: <\/b>.+<br>/i', $body)) {
        $issues[] = 'Application error page';
    }
    // Installer page — common.php redirects here when DB is unavailable or upgrade needed
    if (stripos($body, 'id="stepintro"') !== false
        || preg_match('/<title>[^<]*Installer/i', $body)) {
        $issues[] = 'Redirected to installer (DB unavailable or upgrade required)';
    }
    return $issues;
}

function status(int $code): string {
    if ($code >= 200 && $code < 300) return 'OK';
    if ($code >= 300 && $code < 400) return 'REDIRECT';
    return "HTTP $code";
}

echo "=== HiveNova Smoke Test ===\n";
echo "Base URL : $baseUrl\n";
echo "User     : $username\n\n";

// --- Login ---
echo "[ LOGIN ] POST $baseUrl/index.php?page=login ... ";
[$status,$body,] = curl_post("$baseUrl/index.php?page=login", [
    'username' => $username,
    'password' => $password,
], $cookieFile);

// After login we expect to land on overview or similar — check we're not still on login
if (stripos($body, 'logout') !== false || stripos($body, 'page=logout') !== false) {
    echo "OK (logged in)\n\n";
} elseif ($status >= 400 || stripos($body, 'invalid') !== false || stripos($body, 'wrong') !== false || stripos($body, 'password') !== false) {
    echo "FAILED (bad credentials?)\n";
    exit(1);
} else {
    // Try checking cookies exist — may still be fine
    echo "WARNING (could not confirm login from response body)\n\n";
}

// --- Hive signature login path ---
// The dev user has a hive_account set. Sending a 64-char hex password bypasses the
// strlen < 32 early-return in isHiveSignValid, exercising new Hive() instantiation.
// A class-not-found error here would return HTTP 503 or an application error page.
echo "[ HIVE  ] POST $baseUrl/index.php?page=login (Hive signature path) ... ";
$fakeSignature = str_repeat('a', 64);
[$status, $body,] = curl_post("$baseUrl/index.php?page=login", [
    'username' => $username,
    'password' => $fakeSignature,
], tempnam(sys_get_temp_dir(), 'smoke_hive_'));  // throwaway cookie — don't overwrite real session
$hiveIssues = detectErrors($body);
$hiveLoginRejected = stripos($body, 'loginError') !== false
    || stripos($body, 'code=1') !== false
    || stripos($body, 'page=login') !== false;
$hiveLoggedIn = stripos($body, 'page=logout') !== false
    || stripos($body, 'game.php') !== false;

if ($status >= 400) {
    echo "FAIL (HTTP $status — likely class-not-found in Hive integration)\n";
    $fail++;
} elseif (!empty($hiveIssues)) {
    echo "FAIL (" . implode(', ', $hiveIssues) . ")\n";
    $fail++;
} elseif ($hiveLoggedIn) {
    echo "FAIL (fake signature was accepted — authentication bypass!)\n";
    $fail++;
} elseif (!$hiveLoginRejected) {
    echo "WARN (could not confirm login was rejected)\n";
    $warn++;
} else {
    echo "OK (login rejected as expected)\n";
    $pass++;
}
echo "\n";

// --- Hit each page ---
$colW = max(array_map('strlen', $pages)) + 2;
foreach ($pages as $page) {
    $url = "$baseUrl/game.php?page=$page";
    [$status, $body, $curlErr] = curl_get($url, $cookieFile);

    $issues = detectErrors($body);

    // Check for redirect to login (session lost / page not found redirect)
    $redirectedToLogin = (stripos($body, 'action="game.php?page=login"') !== false
        || (preg_match('/action=["\'][^"\']*page=login/i', $body)
            && stripos($body, 'name="username"') !== false));

    $label = str_pad($page, $colW);

    if ($curlErr) {
        echo "[ FAIL ] $label curl error: $curlErr\n";
        $fail++;
    } elseif ($redirectedToLogin) {
        echo "[ FAIL ] $label redirected to login (session lost or page requires more state)\n";
        $fail++;
    } elseif ($status >= 400) {
        echo "[ FAIL ] $label HTTP $status\n";
        $fail++;
    } elseif (!empty($issues)) {
        foreach ($issues as $issue) {
            echo "[ WARN ] $label $issue\n";
        }
        $warn++;
    } else {
        echo "[ OK   ] $label HTTP $status\n";
        $pass++;
    }
}

// Extract session ID from cookie jar to supply as ?sid= for CSRF-protected admin pages
$sid = '';
foreach (file($cookieFile) ?: [] as $line) {
    if (preg_match('/\b(?:PHPSESSID|2Moons)\b.*\s(\S+)\s*$/', $line, $m)) {
        $sid = trim($m[1]);
        break;
    }
}

// --- Admin panel ---
$adminPages = [
    '',             // default → ShowIndexPage
    'infos',
    'rights',
    'config',
    'configuni',
    'chat',
    'teamspeak',
    'facebook',
    'module',
    'statsconf',
    'disclamer',
    'create',
    'accounteditor',
    'active',
    'bans',
    'messagelist',
    'globalmessage',
    'fleets',
    'accountdata',
    'support',
    'password',
    'search',
    'qeditor',
    'statsupdate',
    'reset',
    'news',
    'topnav',
    'overview',
    'menu',
    'clearcache',
    'universe',
    'multiips',
    'botdetect',
    'log',
    'vertify',
    'cronjob',
    'giveaway',
    'autocomplete',
    'dump',
    'transactions',
    'buildlog',
    // skipping: logout (ends session)
];

echo "\n--- Admin Panel ---\n";
echo "[ ADMIN LOGIN ] POST $baseUrl/admin.php ... ";
[$status, $body,] = curl_post("$baseUrl/admin.php", ['admin_pw' => $password], $cookieFile);

// Logged in = no admin password form present in the response
$adminLoggedIn = $status === 200 && stripos($body, 'name="admin_pw"') === false;

if ($adminLoggedIn) {
    echo "OK\n\n";
} else {
    echo "FAILED (could not confirm admin login — skipping admin pages)\n";
    $fail++;
    $adminPages = [];
}

$adminColW = max(array_map('strlen', array_map(fn($p) => $p ?: '(index)', $adminPages)) ?: [7]) + 2;
foreach ($adminPages as $page) {
    $url   = "$baseUrl/admin.php" . ($page !== '' ? "?page=$page" : '') . ($sid !== '' ? ($page !== '' ? '&' : '?') . "sid=$sid" : '');
    $label = str_pad($page ?: '(index)', $adminColW);
    [$status, $body, $curlErr] = curl_get($url, $cookieFile);

    $issues = detectErrors($body);

    $redirectedToAdminLogin = stripos($body, 'admin_pw') !== false
        && stripos($body, '<input') !== false;

    if ($curlErr) {
        echo "[ FAIL ] $label curl error: $curlErr\n";
        $fail++;
    } elseif ($redirectedToAdminLogin) {
        echo "[ FAIL ] $label redirected to admin login (session lost)\n";
        $fail++;
    } elseif ($status >= 400) {
        echo "[ FAIL ] $label HTTP $status\n";
        $fail++;
    } elseif (!empty($issues)) {
        foreach ($issues as $issue) {
            echo "[ WARN ] $label $issue\n";
        }
        $warn++;
    } else {
        echo "[ OK   ] $label HTTP $status\n";
        $pass++;
    }
}

echo "\n=== Results: $pass passed, $warn warnings, $fail failed ===\n";

@unlink($cookieFile);
exit($fail > 0 ? 1 : 0);
