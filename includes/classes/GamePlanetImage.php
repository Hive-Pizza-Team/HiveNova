<?php

namespace HiveNova\Core;

/**
 * Resolves a planet/moon/debris thumbnail URL.
 *
 * Classic art: styles/theme/{skin}/planeten/{texture}.jpg plus optional {texture}_hq.jpg.
 * Optional HD stills live in styles/theme/hive/planeten-hd/ (Hive globes are the
 * source of truth). Drop {stem}.jpg and, for 512 globes, {stem}_hq.jpg; the
 * classic files stay as the fallback.
 */
class GamePlanetImage
{
	public const HD_DIR = 'planeten-hd';
	public const SHARED_THEME = 'hive';

	private string $rootPath;

	public function __construct(?string $rootPath = null)
	{
		$root = $rootPath ?? (defined('ROOT_PATH') ? ROOT_PATH : '');
		$this->rootPath = rtrim(str_replace('\\', '/', $root), '/');
	}

	/**
	 * @param string $texture Planet image stem, e.g. "wuestenplanet04" or "mond"
	 * @param bool   $hq      When true, prefer the _hq still if one exists
	 */
	public function url(string $dpath, string $texture, bool $hq = false): string
	{
		$stem = $this->sanitize($texture);
		if ($stem === '') {
			return $this->classicUrl($dpath, 'unknown', $hq);
		}

		$hd = $this->hdUrl($dpath, $stem, $hq);
		if ($hd !== null) {
			return $hd;
		}

		return $this->classicUrl($dpath, $stem, $hq);
	}

	public function hasHd(string $dpath, string $texture, bool $hq = false): bool
	{
		$stem = $this->sanitize($texture);

		return $stem !== '' && $this->hdUrl($dpath, $stem, $hq) !== null;
	}

	private function sanitize(string $texture): string
	{
		$texture = str_replace('\\', '/', trim($texture));
		$texture = preg_replace('/\.(jpe?g|png|gif)$/i', '', $texture) ?? $texture;
		if (preg_match('/^[A-Za-z0-9_-]+$/', $texture) !== 1) {
			return '';
		}

		return $texture;
	}

	private function hdUrl(string $dpath, string $stem, bool $hq): ?string
	{
		$theme = $this->themeFromDpath($dpath);
		$skins = [];
		if ($theme !== '') {
			$skins[] = $theme;
		}
		if ($theme !== self::SHARED_THEME) {
			$skins[] = self::SHARED_THEME;
		}

		$names = $hq ? [$stem . '_hq.jpg', $stem . '.jpg'] : [$stem . '.jpg'];
		foreach (array_unique($skins) as $skin) {
			foreach ($names as $file) {
				$rel = 'styles/theme/' . $skin . '/' . self::HD_DIR . '/' . $file;
				if (is_file($this->rootPath . '/' . $rel)) {
					return $this->webPrefix($dpath) . $rel;
				}
			}
		}

		return null;
	}

	private function classicUrl(string $dpath, string $stem, bool $hq): string
	{
		$prefix = $this->normalizeDpath($dpath);
		$file = $hq ? ($stem . '_hq.jpg') : ($stem . '.jpg');

		return $prefix . 'planeten/' . $file;
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
