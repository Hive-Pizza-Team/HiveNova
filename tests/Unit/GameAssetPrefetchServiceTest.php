<?php

use HiveNova\Core\GameAssetPrefetchService;
use HiveNova\Core\GameElementImage;
use HiveNova\Core\GamePlanetImage;
use HiveNova\Core\GameResourceIcon;

use PHPUnit\Framework\TestCase;

class GameAssetPrefetchServiceTest extends TestCase
{
	private string $fixtureRoot;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtureRoot = sys_get_temp_dir() . '/hivenova-prefetch-' . uniqid('', true);
		$gebaeude = $this->fixtureRoot . '/styles/theme/hive/gebaeude';
		$planeten = $this->fixtureRoot . '/styles/theme/hive/planeten';
		mkdir($gebaeude, 0777, true);
		mkdir($planeten, 0777, true);

		file_put_contents($gebaeude . '/1.gif', 'gif');
		file_put_contents($gebaeude . '/202.gif', 'gif');
		file_put_contents($gebaeude . '/readme.txt', 'skip');
		file_put_contents($planeten . '/mond.jpg', 'jpg');
		file_put_contents($planeten . '/mond_hq.jpg', 'hq');
		file_put_contents($planeten . '/debris.jpg', 'jpg');
		mkdir($planeten . '/subdir', 0777, true);
		file_put_contents($planeten . '/subdir/nested.jpg', 'skip-dir');
	}

	protected function tearDown(): void
	{
		$this->removeTree($this->fixtureRoot);
		parent::tearDown();
	}

	public function testListsGebaeudeAndLitePlanetenOnly(): void
	{
		$service = new GameAssetPrefetchService($this->fixtureRoot, 'hive');
		$urls = $service->listUrls();

		$this->assertSame([
			'styles/theme/hive/gebaeude/1.gif',
			'styles/theme/hive/gebaeude/202.gif',
			'styles/theme/hive/planeten/debris.jpg',
			'styles/theme/hive/planeten/mond.jpg',
		], $urls);
	}

	public function testPrefersHdOverClassicAndOmitsHq(): void
	{
		$hd = $this->fixtureRoot . '/styles/theme/hive/gebaeude-hd';
		$planetHd = $this->fixtureRoot . '/styles/theme/hive/planeten-hd';
		$iconsHd = $this->fixtureRoot . '/styles/theme/hive/images-hd';
		mkdir($hd, 0777, true);
		mkdir($planetHd, 0777, true);
		mkdir($iconsHd, 0777, true);

		file_put_contents($hd . '/1.webp', 'webp');
		file_put_contents($hd . '/99.webp', 'hd-only');
		file_put_contents($hd . '/notes.txt', 'skip');
		file_put_contents($planetHd . '/mond.webp', 'webp');
		file_put_contents($planetHd . '/mond_hq.webp', 'hq');
		file_put_contents($iconsHd . '/metal.webp', 'webp');

		$service = new GameAssetPrefetchService($this->fixtureRoot, 'hive');
		$urls = $service->listUrls();

		$this->assertContains('styles/theme/hive/gebaeude-hd/1.webp', $urls);
		$this->assertNotContains('styles/theme/hive/gebaeude/1.gif', $urls);
		$this->assertContains('styles/theme/hive/gebaeude/202.gif', $urls);
		$this->assertContains('styles/theme/hive/gebaeude-hd/99.webp', $urls);
		$this->assertContains('styles/theme/hive/planeten-hd/mond.webp', $urls);
		$this->assertNotContains('styles/theme/hive/planeten/mond.jpg', $urls);
		$this->assertContains('styles/theme/hive/planeten/debris.jpg', $urls);
		$this->assertNotContains('styles/theme/hive/planeten-hd/mond_hq.webp', $urls);
		$this->assertNotContains('styles/theme/hive/planeten/mond_hq.jpg', $urls);
		$this->assertContains('styles/theme/hive/images-hd/metal.webp', $urls);
		$this->assertNotContains('styles/theme/hive/gebaeude-hd/notes.txt', $urls);
	}

	public function testPrefetchListMatchesResolversExactly(): void
	{
		$hd = $this->fixtureRoot . '/styles/theme/hive/gebaeude-hd';
		$planetHd = $this->fixtureRoot . '/styles/theme/hive/planeten-hd';
		$iconsHd = $this->fixtureRoot . '/styles/theme/hive/images-hd';
		mkdir($hd, 0777, true);
		mkdir($planetHd, 0777, true);
		mkdir($iconsHd, 0777, true);
		file_put_contents($hd . '/1.webp', 'webp');
		file_put_contents($planetHd . '/debris.webp', 'webp');
		file_put_contents($iconsHd . '/crystal.webp', 'webp');

		$dpath = 'styles/theme/hive/';
		$elementImage = new GameElementImage($this->fixtureRoot);
		$planetImage = new GamePlanetImage($this->fixtureRoot);
		$resourceIcon = new GameResourceIcon($this->fixtureRoot);

		$expected = [
			$this->stripDotSlash($elementImage->url($dpath, 1)),
			$this->stripDotSlash($elementImage->url($dpath, 202)),
			$this->stripDotSlash($planetImage->url($dpath, 'debris', false)),
			$this->stripDotSlash($planetImage->url($dpath, 'mond', false)),
			$this->stripDotSlash($resourceIcon->url($dpath, 'crystal')),
		];
		sort($expected, SORT_STRING);

		$service = new GameAssetPrefetchService($this->fixtureRoot, 'hive');
		$this->assertSame($expected, $service->listUrls());
	}

	public function testItemKeysAreUniqueNoHdAndLegacyDuplicate(): void
	{
		$hd = $this->fixtureRoot . '/styles/theme/hive/gebaeude-hd';
		$planetHd = $this->fixtureRoot . '/styles/theme/hive/planeten-hd';
		mkdir($hd, 0777, true);
		mkdir($planetHd, 0777, true);
		file_put_contents($hd . '/1.webp', 'webp');
		file_put_contents($planetHd . '/mond.webp', 'webp');

		$urls = (new GameAssetPrefetchService($this->fixtureRoot, 'hive'))->listUrls();
		$keys = [];
		foreach ($urls as $url) {
			$key = $this->itemKeyFromUrl($url);
			$this->assertNotNull($key, 'unexpected prefetch url: ' . $url);
			$this->assertArrayNotHasKey($key, $keys, 'duplicate item key: ' . $key);
			$keys[$key] = $url;
		}
	}

	public function testMissingThemeReturnsEmptyList(): void
	{
		$service = new GameAssetPrefetchService($this->fixtureRoot, 'missing-skin');
		$this->assertSame([], $service->listUrls());
	}

	public function testRepoPrefetchUrlsExistUniqueAndPreferHdWebp(): void
	{
		$root = defined('ROOT_PATH') ? rtrim(ROOT_PATH, '/\\') : dirname(__DIR__, 2);
		$theme = 'hive';
		$dpath = 'styles/theme/' . $theme . '/';
		$service = new GameAssetPrefetchService($root, $theme);
		$urls = $service->listUrls();

		$this->assertNotEmpty($urls);

		$keys = [];
		foreach ($urls as $url) {
			$this->assertFileExists($root . '/' . $url, $url);
			$this->assertStringStartsNotWith('./', $url);
			$key = $this->itemKeyFromUrl($url);
			$this->assertNotNull($key, 'unexpected prefetch url: ' . $url);
			$this->assertArrayNotHasKey($key, $keys, 'duplicate item key: ' . $key);
			$keys[$key] = $url;
		}

		$elementImage = new GameElementImage($root);
		$hdDir = $root . '/styles/theme/nova/gebaeude-hd';
		$entries = scandir($hdDir);
		$this->assertNotFalse($entries);
		foreach ($entries as $file) {
			if (preg_match('/^(\d+)\.webp$/i', $file, $match) !== 1) {
				continue;
			}
			$id = (int) $match[1];
			$expected = $this->stripDotSlash($elementImage->url($dpath, $id));
			$this->assertContains($expected, $urls, 'missing HD prefetch for element ' . $id);
			$this->assertStringContainsString('gebaeude-hd/' . $id . '.webp', $expected);
		}

		foreach (['gebaeude-hd', 'planeten-hd', 'images-hd'] as $subdir) {
			foreach (['hive', 'nova'] as $skin) {
				$dir = $root . '/styles/theme/' . $skin . '/' . $subdir;
				if (!is_dir($dir)) {
					continue;
				}
				$files = scandir($dir);
				$this->assertNotFalse($files);
				foreach ($files as $file) {
					if ($file === '.' || $file === '..') {
						continue;
					}
					$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
					$this->assertNotContains(
						$ext,
						['png', 'jpg', 'jpeg'],
						$skin . '/' . $subdir . '/' . $file . ' must not remain as png/jpg'
					);
				}
			}
		}
	}

	private function stripDotSlash(string $url): string
	{
		return str_starts_with($url, './') ? substr($url, 2) : $url;
	}

	private function itemKeyFromUrl(string $url): ?string
	{
		if (preg_match('#/gebaeude(?:-hd)?/(\d+)\.[a-z0-9]+$#i', $url, $m) === 1) {
			return 'element:' . $m[1];
		}
		if (preg_match('#/planeten(?:-hd)?/([A-Za-z0-9_-]+?)(?:_hq)?\.[a-z0-9]+$#i', $url, $m) === 1) {
			return 'planet:' . $m[1];
		}
		if (preg_match('#/images(?:-hd)?/([a-z]+)\.[a-z0-9]+$#i', $url, $m) === 1) {
			return 'icon:' . $m[1];
		}

		return null;
	}

	private function removeTree(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$items = scandir($dir);
		if ($items === false) {
			return;
		}
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . '/' . $item;
			if (is_dir($path) && !is_link($path)) {
				$this->removeTree($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}
