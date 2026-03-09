#!/usr/bin/env php
<?php
/**
 * Validates all language files against the English base:
 *   - PHP syntax is valid
 *   - No translation keys missing compared to English
 */

$langDir = dirname(__DIR__, 2) . '/language';
$baseLanguage = 'en';
$languageFiles = [
    'ADMIN.php', 'BANNER.php', 'CUSTOM.php', 'FAQ.php', 'FLEET.php',
    'INGAME.php', 'INSTALL.php', 'L18N.php', 'PUBLIC.php', 'TECH.php',
];

$errors = [];

/**
 * Include a language PHP file in isolation and return its $LNG array.
 * Warnings (e.g. undefined keys used as indices) are suppressed since
 * we only care about what keys got defined, not runtime correctness.
 *
 * Returns ['keys' => array, 'output' => string] where 'output' is any
 * unexpected text the file echoed (e.g. due to a stray ?> closing tag).
 */
function extractLngKeys(string $path): array
{
    $LNG = [];
    $prev = error_reporting(E_ERROR);
    ob_start();
    include $path;
    $output = ob_get_clean();
    error_reporting($prev);
    return ['keys' => array_keys($LNG), 'output' => $output];
}

/**
 * Classify unexpected output from an included file and return human-readable
 * error strings. Returns an empty array if there is no output.
 */
function classifyOutput(string $output, string $label): array
{
    if ($output === '') {
        return [];
    }
    $utf8Bom = "\xEF\xBB\xBF";
    // Strip leading BOM before checking for other content
    $rest = str_starts_with($output, $utf8Bom) ? substr($output, 3) : $output;
    $messages = [];
    if (str_starts_with($output, $utf8Bom)) {
        $messages[] = "$label File starts with a UTF-8 BOM";
    }
    if ($rest !== '') {
        $messages[] = "$label Unexpected output from file (stray '?>' closing tag?)";
    }
    return $messages;
}

function syntaxCheck(string $path): ?string
{
    $output = shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1');
    if (strpos($output, 'No syntax errors') === false) {
        // Return only the first non-empty line (the actual parse error message)
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }
        return 'unknown syntax error';
    }
    return null;
}

// --- Build base (English) key sets ---
$baseKeys = [];
foreach ($languageFiles as $file) {
    $path = "$langDir/$baseLanguage/$file";
    if (!file_exists($path)) {
        $errors[] = "Base language file missing: language/$baseLanguage/$file";
        continue;
    }
    $syntaxError = syntaxCheck($path);
    if ($syntaxError !== null) {
        $errors[] = "Syntax error in language/$baseLanguage/$file: $syntaxError";
        continue;
    }
    $result = extractLngKeys($path);
    foreach (classifyOutput($result['output'], "language/$baseLanguage/$file") as $e) {
        $errors[] = $e;
    }
    $baseKeys[$file] = $result['keys'];
}

// --- Check every other language ---
$languages = array_values(array_filter(
    scandir($langDir),
    fn($entry) => $entry !== '.' && $entry !== '..' && is_dir("$langDir/$entry")
));

foreach ($languages as $lang) {
    if ($lang === $baseLanguage) {
        continue;
    }
    foreach ($languageFiles as $file) {
        $path = "$langDir/$lang/$file";
        if (!file_exists($path)) {
            $errors[] = "[$lang] Missing file: $file";
            continue;
        }
        $syntaxError = syntaxCheck($path);
        if ($syntaxError !== null) {
            $errors[] = "[$lang] Syntax error in $file: $syntaxError";
            continue;
        }
        if (!isset($baseKeys[$file])) {
            continue; // base file had errors, skip comparison
        }
        $result = extractLngKeys($path);
        foreach (classifyOutput($result['output'], "[$lang/$file]") as $e) {
            $errors[] = $e;
        }
        $missing = array_diff($baseKeys[$file], $result['keys']);
        foreach ($missing as $key) {
            $errors[] = "[$lang/$file] Missing key: $key";
        }
    }
}

// --- Report ---
if (empty($errors)) {
    echo "All language files OK.\n";
    exit(0);
}

foreach ($errors as $error) {
    echo "ERROR: $error\n";
}
exit(1);
