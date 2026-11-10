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
			foreach (self::RENDERING_PREFIXES as $prefix) {
				if (str_starts_with($normalized, $prefix)) {
					$hits[] = $normalized;
					break;
				}
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

	public static function bodyHasScreenshot(string $body): bool
	{
		if ($body === '') {
			return false;
		}
		// Markdown image: ![alt](url)
		if (preg_match('/!\[[^\]]*]\([^)]+\)/', $body) === 1) {
			return true;
		}
		// HTML <img ...>
		if (preg_match('/<img\s/i', $body) === 1) {
			return true;
		}
		// GitHub / browser upload URLs without markdown wrapper
		if (preg_match('#https?://[^)\s]*user-attachments/assets/[A-Za-z0-9-]+#', $body) === 1) {
			return true;
		}
		return false;
	}

	/**
	 * @param list<string> $changedPaths
	 * @param list<string> $labelNames
	 * @return array{required: bool, skipped: bool, ok: bool, rendering: list<string>, reason: string}
	 */
	public static function evaluate(array $changedPaths, string $body, array $labelNames): array
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

		if (self::bodyHasScreenshot($body)) {
			return [
				'required' => true,
				'skipped' => false,
				'ok' => true,
				'rendering' => $rendering,
				'reason' => 'PR body includes a screenshot.',
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
