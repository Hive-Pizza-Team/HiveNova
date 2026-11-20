<?php

namespace HiveNova\Core;

/**
 * Builds a list of theme image URLs to warm the browser cache before login.
 *
 * Emits one URL per displayable item via the same resolvers used in-game
 * (GameElementImage / GamePlanetImage / GameResourceIcon), so prefetch cannot
 * drift from what the UI actually loads.
 *
 * Resource icons: only the fixed HD set (metal, crystal, deuterium, energy,
 * darkmatter) when an HD file exists — same “warm HD icons only” behaviour
 * as before; classic GIFs for those stems are not listed.
 */
class GameAssetPrefetchService
{
	/** Resource-bar stems that have dedicated HD icons to warm. */
	private const RESOURCE_ICON_STEMS = ['metal', 'crystal', 'deuterium', 'energy', 'darkmatter'];

	private string $rootPath;
	private string $theme;

	public function __construct(?string $rootPath = null, ?string $theme = null)
	{
		$this->rootPath = rtrim(str_replace('\\', '/', $rootPath ?? ROOT_PATH), '/');
		$this->theme = $theme ?? (defined('DEFAULT_THEME') ? DEFAULT_THEME : 'hive');
	}

	/**
	 * Web-relative image paths for the theme (no leading "./"; excludes *_hq).
	 *
	 * @return list<string>
	 */
	public function listUrls(): array
	{
		$themeRoot = $this->rootPath . '/styles/theme/' . $this->theme;
		if (!is_dir($themeRoot)) {
			return [];
		}

		$dpath = 'styles/theme/' . $this->theme . '/';
		$elementImage = new GameElementImage($this->rootPath);
		$planetImage = new GamePlanetImage($this->rootPath);
		$resourceIcon = new GameResourceIcon($this->rootPath);

		$urls = [];

		foreach ($this->elementIds() as $id) {
			$urls[] = $this->normalizeWebUrl($elementImage->url($dpath, $id));
		}

		foreach ($this->planetStems() as $stem) {
			$urls[] = $this->normalizeWebUrl($planetImage->url($dpath, $stem, false));
		}

		foreach (self::RESOURCE_ICON_STEMS as $stem) {
			if ($resourceIcon->hasHd($dpath, $stem)) {
				$urls[] = $this->normalizeWebUrl($resourceIcon->url($dpath, $stem));
			}
		}

		$urls = array_values(array_unique($urls));
		sort($urls, SORT_STRING);

		return $urls;
	}

	/**
	 * Classic gebaeude IDs plus HD-only IDs (webp present, no classic file).
	 *
	 * @return list<int>
	 */
	private function elementIds(): array
	{
		$classic = $this->scanClassicElementIds();
		$hd = $this->scanHdElementIds();
		$ids = array_unique(array_merge($classic, $hd));
		sort($ids, SORT_NUMERIC);

		return $ids;
	}

	/**
	 * @return list<int>
	 */
	private function scanClassicElementIds(): array
	{
		$dir = $this->rootPath . '/styles/theme/' . $this->theme . '/gebaeude';
		if (!is_dir($dir)) {
			return [];
		}

		$entries = scandir($dir);
		if ($entries === false) {
			return [];
		}

		$ids = [];
		foreach ($entries as $file) {
			if (preg_match('/^(\d+)\.(gif|jpe?g)$/i', $file, $match) !== 1) {
				continue;
			}
			$path = $dir . '/' . $file;
			if (!is_file($path) && !is_link($path)) {
				continue;
			}
			$ids[] = (int) $match[1];
		}

		return $ids;
	}

	/**
	 * HD element IDs from the theme gebaeude-hd dir and the shared nova pack.
	 *
	 * @return list<int>
	 */
	private function scanHdElementIds(): array
	{
		$skins = [$this->theme];
		if ($this->theme !== GameElementImage::SHARED_THEME) {
			$skins[] = GameElementImage::SHARED_THEME;
		}

		$ids = [];
		foreach (array_unique($skins) as $skin) {
			$dir = $this->rootPath . '/styles/theme/' . $skin . '/' . GameElementImage::HD_DIR;
			if (!is_dir($dir)) {
				continue;
			}
			$entries = scandir($dir);
			if ($entries === false) {
				continue;
			}
			foreach ($entries as $file) {
				if (preg_match('/^(\d+)\.webp$/i', $file, $match) !== 1) {
					continue;
				}
				$path = $dir . '/' . $file;
				if (!is_file($path) && !is_link($path)) {
					continue;
				}
				$ids[] = (int) $match[1];
			}
		}

		return $ids;
	}

	/**
	 * Non-_hq classic planet texture stems under planeten/.
	 *
	 * @return list<string>
	 */
	private function planetStems(): array
	{
		$dir = $this->rootPath . '/styles/theme/' . $this->theme . '/planeten';
		if (!is_dir($dir)) {
			return [];
		}

		$entries = scandir($dir);
		if ($entries === false) {
			return [];
		}

		$stems = [];
		foreach ($entries as $file) {
			if (str_contains($file, '_hq.')) {
				continue;
			}
			if (preg_match('/^([A-Za-z0-9_-]+)\.jpe?g$/i', $file, $match) !== 1) {
				continue;
			}
			$path = $dir . '/' . $file;
			if (!is_file($path) && !is_link($path)) {
				continue;
			}
			$stems[] = $match[1];
		}

		sort($stems, SORT_STRING);

		return $stems;
	}

	private function normalizeWebUrl(string $url): string
	{
		$url = str_replace('\\', '/', $url);
		if (str_starts_with($url, './')) {
			$url = substr($url, 2);
		}

		return $url;
	}
}
