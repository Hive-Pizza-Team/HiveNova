#!/usr/bin/env php
<?php
/**
 * CI gate: PRs that touch rendering paths must include a screenshot in the body.
 *
 * Usage (GitHub Actions pull_request):
 *   php .github/scripts/check-pr-screenshot.php
 *
 * Usage (local / tests):
 *   php .github/scripts/check-pr-screenshot.php \
 *     --files=styles/theme/hive/formate.css,scripts/game/base.js \
 *     --body='## Shot\n![ui](https://example.com/a.png)' \
 *     --labels=enhancement
 *
 * Exit 0 = pass / not applicable; exit 1 = missing required screenshot.
 */

declare(strict_types=1);

require __DIR__ . '/PrScreenshotCheck.php';

/**
 * @return array{files: list<string>, body: string, labels: list<string>, fromEvent: bool}
 */
function parseArgs(array $argv): array
{
	$files = null;
	$body = null;
	$labels = [];

	foreach (array_slice($argv, 1) as $arg) {
		if (str_starts_with($arg, '--files=')) {
			$raw = substr($arg, strlen('--files='));
			$files = $raw === '' ? [] : (preg_split('/\s*,\s*/', $raw) ?: []);
		} elseif (str_starts_with($arg, '--body=')) {
			$body = str_replace('\\n', "\n", substr($arg, strlen('--body=')));
		} elseif (str_starts_with($arg, '--labels=')) {
			$raw = substr($arg, strlen('--labels='));
			$labels = $raw === '' ? [] : (preg_split('/\s*,\s*/', $raw) ?: []);
		} elseif ($arg === '--help' || $arg === '-h') {
			fwrite(STDOUT, "Usage: check-pr-screenshot.php [--files=a,b] [--body=...] [--labels=a,b]\n");
			exit(0);
		} else {
			fwrite(STDERR, "Unknown argument: $arg\n");
			exit(2);
		}
	}

	if ($files !== null && $body !== null) {
		return ['files' => array_values($files), 'body' => $body, 'labels' => array_values($labels), 'fromEvent' => false];
	}

	$eventPath = getenv('GITHUB_EVENT_PATH') ?: '';
	if ($eventPath === '' || !is_readable($eventPath)) {
		fwrite(STDERR, "No --files/--body and GITHUB_EVENT_PATH missing; nothing to check.\n");
		exit(0);
	}

	$event = json_decode((string) file_get_contents($eventPath), true);
	$pr = is_array($event) ? ($event['pull_request'] ?? null) : null;
	if (!is_array($pr)) {
		fwrite(STDOUT, "Not a pull_request event payload; skipping screenshot check.\n");
		exit(0);
	}

	$bodyText = (string) ($pr['body'] ?? '');
	$labelNames = [];
	foreach ($pr['labels'] ?? [] as $label) {
		if (is_array($label) && isset($label['name'])) {
			$labelNames[] = (string) $label['name'];
		}
	}

	$baseRef = (string) ($pr['base']['ref'] ?? (getenv('GITHUB_BASE_REF') ?: 'master'));
	$files = changedFilesAgainstBase($baseRef);

	return ['files' => $files, 'body' => $bodyText, 'labels' => $labelNames, 'fromEvent' => true];
}

/**
 * @return list<string>
 */
function changedFilesAgainstBase(string $baseRef): array
{
	$candidates = [
		'origin/' . $baseRef . '...HEAD',
		$baseRef . '...HEAD',
	];

	foreach ($candidates as $range) {
		$cmd = 'git diff --name-only ' . escapeshellarg($range) . ' 2>/dev/null';
		$output = shell_exec($cmd);
		if ($output === null || trim($output) === '') {
			continue;
		}
		$paths = preg_split('/\R/', trim($output)) ?: [];
		return array_values(array_filter($paths, static fn($p) => $p !== ''));
	}

	fwrite(STDERR, "WARN: could not list changed files via git diff; treating as no changes.\n");
	return [];
}

$input = parseArgs($argv);
$result = PrScreenshotCheck::evaluate($input['files'], $input['body'], $input['labels']);

fwrite(STDOUT, $result['reason'] . "\n");
if ($result['rendering'] !== []) {
	fwrite(STDOUT, "Rendering paths:\n");
	foreach ($result['rendering'] as $path) {
		fwrite(STDOUT, "  - $path\n");
	}
}

if ($result['ok']) {
	exit(0);
}

fwrite(STDERR, "\nAdd a screenshot to the PR description (markdown image / <img> / user-attachments URL),\n");
fwrite(STDERR, "or set label 'skip-screenshot' / include [skip-screenshot] with a reason.\n");
fwrite(STDERR, "See .cursor/rules/pr-screenshot-for-rendering.mdc\n");
exit(1);
