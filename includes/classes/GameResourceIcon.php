<?php

namespace HiveNova\Core;

/**
 * Resolves the 42×22 resource-bar icon URL (metal, crystal, deuterium, energy, darkmatter).
 *
 * Classic art: styles/theme/{skin}/images/{name}.gif.
 * Optional HD icons live in styles/theme/hive/images-hd/{name}.png at native 42×22.
 * Do not upscale them; the GIF stays as the fallback.
 */
class GameResourceIcon
{
	public const HD_DIR = 'images-hd';
	public const SHARED_THEME = 'hive';

	private string $rootPath;

	public function __construct(?string $rootPath = null)
	{
		$root = $rootPath ?? (defined('ROOT_PATH') ? ROOT_PATH : '');
		$this->rootPath = rtrim(str_replace('\\', '/', $root), '/');
	}

	public function url(string $dpath, string $name): string
	{
		$stem = $this->sanitize($name);
		if ($stem === '') {
			return $this->classicUrl($dpath, 'metal');
		}

		$hd = $this->hdUrl($dpath, $stem);
		if ($hd !== null) {
			return $hd;
		}

		return $this->classicUrl($dpath, $stem);
	}

	public function hasHd(string $dpath, string $name): bool
	{
		$stem = $this->sanitize($name);

		return $stem !== '' && $this->hdUrl($dpath, $stem) !== null;
	}

	private function sanitize(string $name): string
	{
		$name = strtolower(str_replace('\\', '/', trim($name)));
		$name = preg_replace('/\.(png|gif|jpe?g)$/i', '', $name) ?? $name;
		if (preg_match('/^[a-z]+$/', $name) !== 1) {
			return '';
		}

		return $name;
	}

	private function hdUrl(string $dpath, string $stem): ?string
	{
		$theme = $this->themeFromDpath($dpath);
		$skins = [];
		if ($theme !== '') {
			$skins[] = $theme;
		}
		if ($theme !== self::SHARED_THEME) {
			$skins[] = self::SHARED_THEME;
		}

		foreach (array_unique($skins) as $skin) {
			$rel = 'styles/theme/' . $skin . '/' . self::HD_DIR . '/' . $stem . '.png';
			if (is_file($this->rootPath . '/' . $rel)) {
				return $this->webPrefix($dpath) . $rel;
			}
		}

		return null;
	}

	private function classicUrl(string $dpath, string $stem): string
	{
		return $this->normalizeDpath($dpath) . 'images/' . $stem . '.gif';
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
