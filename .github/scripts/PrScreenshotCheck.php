<?php
/**
 * Pure helpers for the PR rendering-screenshot CI gate.
 * Kept free of GitHub I/O so unit tests can cover the rules.
 */

final class PrScreenshotCheck
{
	/** Path prefixes (repo-relative, forward slashes) that count as rendering. */
	public const RENDERING_PREFIXES = [
		'styles/',
		'scripts/',
		'chat/',
		'includes/pages/',
	];

	public const SKIP_LABEL = 'skip-screenshot';
	public const SKIP_BODY_MARKER = '[skip-screenshot]';

	/**
	 * @param list<string> $paths Repo-relative paths from the PR diff
	 * @return list<string> Paths that trigger the screenshot requirement
	 */
	public static function renderingPaths(array $paths): array
	{
		$hits = [];
		foreach ($paths as $path) {
			$normalized = ltrim(str_replace('\\', '/', (string) $path), '/');
			$matched = false;
			foreach (self::RENDERING_PREFIXES as $prefix) {
				if (str_starts_with($normalized, $prefix)) {
					$hits[] = $normalized;
					$matched = true;
					break;
				}
			}
			// Any Smarty template, including outside styles/ (e.g. install or one-offs).
			if (!$matched && str_ends_with(strtolower($normalized), '.tpl')) {
				$hits[] = $normalized;
			}
		}
		return array_values(array_unique($hits));
	}

	/**
	 * @param list<string> $labelNames
	 */
	public static function isSkipped(string $body, array $labelNames): bool
	{
		foreach ($labelNames as $name) {
			if (strcasecmp((string) $name, self::SKIP_LABEL) === 0) {
				return true;
			}
		}
		return stripos($body, self::SKIP_BODY_MARKER) !== false;
	}

	/**
	 * Collect image URLs from markdown images, <img src>, and bare user-attachments links.
	 *
	 * @return list<string>
	 */
	public static function imageUrls(string $body): array
	{
		if ($body === '') {
			return [];
		}
		$urls = [];
		if (preg_match_all('/!\[[^\]]*]\(([^)]+)\)/', $body, $m) > 0) {
			foreach ($m[1] as $url) {
				$urls[] = trim($url);
			}
		}
		if (preg_match_all('/<img\s[^>]*src=["\']([^"\']+)["\']/i', $body, $m) > 0) {
			foreach ($m[1] as $url) {
				$urls[] = trim($url);
			}
		}
		if (preg_match_all('#https?://[^)\s]*user-attachments/assets/[A-Za-z0-9-]+#', $body, $m) > 0) {
			foreach ($m[0] as $url) {
				$urls[] = trim($url);
			}
		}
		return array_values(array_unique(array_filter($urls, static fn($u) => $u !== '')));
	}

	public static function bodyHasScreenshot(string $body): bool
	{
		return self::imageUrls($body) !== [];
	}

	/**
	 * True when at least one image is bound to this PR: user-attachments, or a
	 * raw/blob GitHub URL whose ref equals $headRef (URL-encoded segments OK).
	 */
	public static function bodyHasPrBoundScreenshot(string $body, string $headRef): bool
	{
		$urls = self::imageUrls($body);
		if ($urls === []) {
			return false;
		}
		$headRef = trim($headRef);
		if ($headRef === '') {
			// No head ref available (local CLI without --head): keep legacy "any image" behavior.
			return true;
		}

		$quoted = preg_quote($headRef, '#');
		foreach ($urls as $url) {
			if (preg_match('#user-attachments/assets/[A-Za-z0-9-]+#', $url) === 1) {
				return true;
			}
			// raw.githubusercontent.com/org/repo/<headRef>/path
			if (preg_match('#raw\.githubusercontent\.com/[^/]+/[^/]+/' . $quoted . '/#', $url) === 1) {
				return true;
			}
			// github.com/org/repo/blob|raw/<headRef>/path
			if (preg_match('#github\.com/[^/]+/[^/]+/(?:blob|raw)/' . $quoted . '/#', $url) === 1) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<string> $changedPaths
	 * @param list<string> $labelNames
	 * @return array{required: bool, skipped: bool, ok: bool, rendering: list<string>, reason: string}
	 */
	public static function evaluate(array $changedPaths, string $body, array $labelNames, string $headRef = ''): array
	{
		$rendering = self::renderingPaths($changedPaths);
		if ($rendering === []) {
			return [
				'required' => false,
				'skipped' => false,
				'ok' => true,
				'rendering' => [],
				'reason' => 'No rendering paths changed; screenshot not required.',
			];
		}

		if (self::isSkipped($body, $labelNames)) {
			return [
				'required' => true,
				'skipped' => true,
				'ok' => true,
				'rendering' => $rendering,
				'reason' => 'Screenshot requirement skipped via label or [skip-screenshot] marker.',
			];
		}

		if (self::bodyHasPrBoundScreenshot($body, $headRef)) {
			return [
				'required' => true,
				'skipped' => false,
				'ok' => true,
				'rendering' => $rendering,
				'reason' => 'PR body includes a PR-bound screenshot (branch raw/blob URL or user-attachments).',
			];
		}

		if (self::bodyHasScreenshot($body) && $headRef !== '') {
			return [
				'required' => true,
				'skipped' => false,
				'ok' => false,
				'rendering' => $rendering,
				'reason' => 'Screenshot links must point at this PR branch (or user-attachments), not master/develop/another branch.',
			];
		}

		return [
			'required' => true,
			'skipped' => false,
			'ok' => false,
			'rendering' => $rendering,
			'reason' => 'Rendering paths changed but PR body has no screenshot image.',
		];
	}
}
