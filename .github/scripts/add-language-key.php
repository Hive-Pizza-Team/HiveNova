#!/usr/bin/env php
<?php
/**
 * Add a language key to English and stub it into every other locale.
 *
 * Usage:
 *   php .github/scripts/add-language-key.php INGAME.php my_key "English text"
 *   php .github/scripts/add-language-key.php --dry-run FLEET.php fl_foo "Hello"
 *
 * Existing keys are left unchanged. EN text is used as the interim translation
 * for other locales (matches project convention).
 */

declare(strict_types=1);

$argvList = array_values(array_filter(array_slice($argv, 1), static fn($a) => $a !== '--'));
$dryRun = false;
if (($argvList[0] ?? '') === '--dry-run') {
	$dryRun = true;
	array_shift($argvList);
}

if (count($argvList) < 3) {
	fwrite(STDERR, "Usage: php .github/scripts/add-language-key.php [--dry-run] <FILE.php> <key> <english text>\n");
	exit(2);
}

$file = $argvList[0];
$key = $argvList[1];
$text = implode(' ', array_slice($argvList, 2));

if (!str_ends_with($file, '.php')) {
	$file .= '.php';
}

if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
	fwrite(STDERR, "ERROR: key must be a simple identifier (got: {$key})\n");
	exit(1);
}

$langDir = dirname(__DIR__, 2) . '/language';
$locales = array_values(array_filter(
	scandir($langDir) ?: [],
	static fn($entry) => $entry !== '.' && $entry !== '..' && is_dir("$langDir/$entry")
));

if (!in_array('en', $locales, true)) {
	fwrite(STDERR, "ERROR: language/en missing\n");
	exit(1);
}

/**
 * Escape a PHP single-quoted string literal.
 */
function phpSingleQuoted(string $value): string
{
	return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
}

/**
 * True if $LNG['key'] already appears in file contents.
 */
function hasLngKey(string $contents, string $key): bool
{
	return (bool) preg_match('/\$LNG\s*\[\s*[\'"]' . preg_quote($key, '/') . '[\'"]\s*\]/', $contents);
}

$line = "\$LNG['{$key}'] = '" . phpSingleQuoted($text) . "';\n";
$changed = 0;
$skipped = 0;

foreach ($locales as $locale) {
	$path = "$langDir/$locale/$file";
	if (!is_file($path)) {
		fwrite(STDERR, "ERROR: missing $path\n");
		exit(1);
	}

	$contents = file_get_contents($path);
	if ($contents === false) {
		fwrite(STDERR, "ERROR: cannot read $path\n");
		exit(1);
	}

	if (hasLngKey($contents, $key)) {
		fwrite(STDOUT, "skip  $locale/$file (key exists)\n");
		$skipped++;
		continue;
	}

	$newContents = rtrim($contents) . "\n" . $line;
	if ($dryRun) {
		fwrite(STDOUT, "dry   $locale/$file\n");
	} else {
		if (file_put_contents($path, $newContents) === false) {
			fwrite(STDERR, "ERROR: cannot write $path\n");
			exit(1);
		}
		fwrite(STDOUT, "add   $locale/$file\n");
	}
	$changed++;
}

fwrite(STDOUT, ($dryRun ? 'Would update' : 'Updated') . " $changed locale(s); skipped $skipped.\n");
exit(0);
