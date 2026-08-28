<?php

use HiveNova\Core\GameAssetPrefetchService;

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

	public function testMissingThemeReturnsEmptyList(): void
	{
		$service = new GameAssetPrefetchService($this->fixtureRoot, 'missing-skin');
		$this->assertSame([], $service->listUrls());
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
			if (is_dir($path)) {
				$this->removeTree($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}
