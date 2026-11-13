<?php

namespace HiveNova\Core;

/**
 * Resolves the in-game image URL for a tech/building/ship/defense ID.
 *
 * Classic art lives in styles/theme/{skin}/gebaeude/{id}.gif (officers: .jpg).
 * Optional HD stills live in styles/theme/nova/gebaeude-hd/{id}.png (transparent
 * cutouts). Drop a PNG named {id}.png in that folder to enable it; the GIF is
 * kept as the fallback when no HD file is present.
 */
class GameElementImage
{
	public const HD_DIR = 'gebaeude-hd';
	public const HD_EXT = 'png';
	public const SHARED_THEME = 'nova';

	private string $rootPath;

	public function __construct(?string $rootPath = null)
	{
		$root = $rootPath ?? (defined('ROOT_PATH') ? ROOT_PATH : '');
		$this->rootPath = rtrim(str_replace('\\', '/', $root), '/');
	}

	/**
	 * Web path for the element's image, matching the $dpath prefix style
	 * already used in templates (including a leading "./" when present).
	 */
	public function url(string $dpath, int $elementId): string
	{
		$hd = $this->hdUrl($dpath, $elementId);
		if ($hd !== null) {
			return $hd;
		}

		return $this->classicUrl($dpath, $elementId);
	}

	public function hasHd(string $dpath, int $elementId): bool
	{
		return $this->hdUrl($dpath, $elementId) !== null;
	}

	/**
	 * Map of element-id strings to HD file extensions, for JS (techtree).
	 * Batch 2+ is drop-in: add the PNG and it appears here automatically.
	 *
	 * @return array<string, string>
	 */
	public function hdExtensions(string $dpath): array
	{
		$theme = $this->themeFromDpath($dpath);
		$skins = [];
		if ($theme !== '') {
			$skins[] = $theme;
		}
		if ($theme !== self::SHARED_THEME) {
			$skins[] = self::SHARED_THEME;
		}

		$out = [];
		foreach (array_unique($skins) as $skin) {
			$dir = $this->rootPath . '/styles/theme/' . $skin . '/' . self::HD_DIR;
			if (!is_dir($dir)) {
				continue;
			}

			$entries = scandir($dir);
			if ($entries === false) {
				continue;
			}

			foreach ($entries as $file) {
				if (preg_match('/^(\d+)\.' . preg_quote(self::HD_EXT, '/') . '$/i', $file, $match) !== 1) {
					continue;
				}
				$path = $dir . '/' . $file;
				if (!is_file($path) && !is_link($path)) {
					continue;
				}
				$out[$match[1]] = self::HD_EXT;
			}
		}

		ksort($out, SORT_NUMERIC);

		return $out;
	}

	public static function classicExtension(int $elementId): string
	{
		return ($elementId >= 600 && $elementId <= 699) ? 'jpg' : 'gif';
	}

	private function hdUrl(string $dpath, int $elementId): ?string
	{
		if ($elementId <= 0) {
			return null;
		}

		$theme = $this->themeFromDpath($dpath);
		$candidates = [];
		if ($theme !== '') {
			$candidates[] = $theme;
		}
		if ($theme !== self::SHARED_THEME) {
			$candidates[] = self::SHARED_THEME;
		}

		$file = $elementId . '.' . self::HD_EXT;
		foreach (array_unique($candidates) as $skin) {
			$rel = 'styles/theme/' . $skin . '/' . self::HD_DIR . '/' . $file;
			if (is_file($this->rootPath . '/' . $rel)) {
				return $this->webPrefix($dpath) . $rel;
			}
		}

		return null;
	}

	private function classicUrl(string $dpath, int $elementId): string
	{
		$prefix = $this->normalizeDpath($dpath);

		return $prefix . 'gebaeude/' . $elementId . '.' . self::classicExtension($elementId);
	}

	private function themeFromDpath(string $dpath): string
	{
		$normalized = str_replace('\\', '/', $dpath);
		if (preg_match('#(?:\./)?styles/theme/([^/]+)/?#', $normalized, $match) === 1) {
			return $match[1];
		}

		$trimmed = trim($normalized, '/.');
		if ($trimmed !== '' && !str_contains($trimmed, '/')) {
			return $trimmed;
		}

		if (defined('DEFAULT_THEME') && is_string(DEFAULT_THEME) && DEFAULT_THEME !== '') {
			return DEFAULT_THEME;
		}

		return self::SHARED_THEME;
	}

	private function webPrefix(string $dpath): string
	{
		$normalized = str_replace('\\', '/', $dpath);
		if ($normalized === '' || str_starts_with($normalized, './')) {
			return './';
		}

		return '';
	}

	private function normalizeDpath(string $dpath): string
	{
		$normalized = str_replace('\\', '/', $dpath);
		if ($normalized === '') {
			$theme = $this->themeFromDpath($dpath);
			return './styles/theme/' . $theme . '/';
		}
		if (!str_ends_with($normalized, '/')) {
			$normalized .= '/';
		}

		return $normalized;
	}
}
