<?php

namespace HiveNova\Core;

/**
 * Builds a list of theme image URLs to warm the browser cache before login.
 */
class GameAssetPrefetchService
{
	private const IMAGE_EXTENSIONS = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'svg'];

	/** Subdirs under styles/theme/{skin}/ that power common in-game lazy images. */
	private const SUBDIRS = ['gebaeude', 'planeten'];

	private string $rootPath;
	private string $theme;

	public function __construct(?string $rootPath = null, ?string $theme = null)
	{
		$this->rootPath = rtrim($rootPath ?? ROOT_PATH, '/\\');
		$this->theme = $theme ?? (defined('DEFAULT_THEME') ? DEFAULT_THEME : 'hive');
	}

	/**
	 * Web-relative image paths for the default skin (excludes *_hq.* variants).
	 *
	 * @return list<string>
	 */
	public function listUrls(): array
	{
		$urls = [];

		foreach (self::SUBDIRS as $subdir) {
			$dir = $this->rootPath . '/styles/theme/' . $this->theme . '/' . $subdir;
			if (!is_dir($dir)) {
				continue;
			}

			$entries = scandir($dir);
			if ($entries === false) {
				continue;
			}

			foreach ($entries as $file) {
				if ($file === '.' || $file === '..') {
					continue;
				}
				if (str_contains($file, '_hq.')) {
					continue;
				}

				$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
					continue;
				}

				$path = $dir . '/' . $file;
				if (!is_file($path)) {
					continue;
				}

				$urls[] = 'styles/theme/' . $this->theme . '/' . $subdir . '/' . $file;
			}
		}

		sort($urls, SORT_STRING);

		return $urls;
	}
}
