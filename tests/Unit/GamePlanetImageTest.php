<?php

use HiveNova\Core\GamePlanetImage;
use PHPUnit\Framework\TestCase;

class GamePlanetImageTest extends TestCase
{
	private string $fixtureRoot;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtureRoot = sys_get_temp_dir() . '/hivenova-planet-img-' . uniqid('', true);

		$hiveHd = $this->fixtureRoot . '/styles/theme/hive/planeten-hd';
		$hiveClassic = $this->fixtureRoot . '/styles/theme/hive/planeten';
		mkdir($hiveHd, 0777, true);
		mkdir($hiveClassic, 0777, true);
		mkdir($this->fixtureRoot . '/styles/theme/nova/planeten', 0777, true);

		file_put_contents($hiveHd . '/dschjungelplanet01.jpg', 'lite');
		file_put_contents($hiveHd . '/dschjungelplanet01_hq.jpg', 'hq');
		file_put_contents($hiveHd . '/gasplanet01.jpg', 'gas');
		file_put_contents($hiveClassic . '/dschjungelplanet01.jpg', 'classic-lite');
		file_put_contents($hiveClassic . '/dschjungelplanet01_hq.jpg', 'classic-hq');
		file_put_contents($hiveClassic . '/wuestenplanet01.jpg', 'classic-only');
	}

	protected function tearDown(): void
	{
		$this->removeTree($this->fixtureRoot);
		parent::tearDown();
	}

	public function testLitePrefersStandardHdJpg(): void
	{
		$resolver = new GamePlanetImage($this->fixtureRoot);
		$this->assertSame(
			'./styles/theme/hive/planeten-hd/dschjungelplanet01.jpg',
			$resolver->url('./styles/theme/hive/', 'dschjungelplanet01', false)
		);
		$this->assertTrue($resolver->hasHd('./styles/theme/hive/', 'dschjungelplanet01', false));
	}

	public function testHqPrefersHqStillThenLiteHd(): void
	{
		$resolver = new GamePlanetImage($this->fixtureRoot);
		$this->assertSame(
			'./styles/theme/hive/planeten-hd/dschjungelplanet01_hq.jpg',
			$resolver->url('./styles/theme/hive/', 'dschjungelplanet01', true)
		);
		$this->assertSame(
			'./styles/theme/hive/planeten-hd/gasplanet01.jpg',
			$resolver->url('./styles/theme/hive/', 'gasplanet01', true)
		);
	}

	public function testFallsBackToClassicWhenNoHd(): void
	{
		$resolver = new GamePlanetImage($this->fixtureRoot);
		$this->assertSame(
			'./styles/theme/hive/planeten/wuestenplanet01.jpg',
			$resolver->url('./styles/theme/hive/', 'wuestenplanet01', false)
		);
		$this->assertSame(
			'./styles/theme/hive/planeten/wuestenplanet01_hq.jpg',
			$resolver->url('./styles/theme/hive/', 'wuestenplanet01', true)
		);
		$this->assertFalse($resolver->hasHd('./styles/theme/hive/', 'wuestenplanet01'));
	}

	public function testRejectsUnsafeTextureNames(): void
	{
		$resolver = new GamePlanetImage($this->fixtureRoot);
		$this->assertSame(
			'./styles/theme/hive/planeten/unknown.jpg',
			$resolver->url('./styles/theme/hive/', '../secret', false)
		);
	}

	public function testRepoGlobeAndDebrisAssetsResolve(): void
	{
		$resolver = new GamePlanetImage();
		$dpath = './styles/theme/hive/';
		$this->assertTrue($resolver->hasHd($dpath, 'dschjungelplanet01', false));
		$this->assertTrue($resolver->hasHd($dpath, 'dschjungelplanet01', true));
		$this->assertSame($dpath . 'planeten-hd/dschjungelplanet01.jpg', $resolver->url($dpath, 'dschjungelplanet01', false));
		$this->assertSame($dpath . 'planeten-hd/dschjungelplanet01_hq.jpg', $resolver->url($dpath, 'dschjungelplanet01', true));
		$this->assertSame($dpath . 'planeten-hd/gasplanet01.jpg', $resolver->url($dpath, 'gasplanet01', true));
		$this->assertSame($dpath . 'planeten-hd/debris.jpg', $resolver->url($dpath, 'debris', false));
		$this->assertFalse(is_file((defined('ROOT_PATH') ? ROOT_PATH : '') . 'styles/theme/hive/planeten-hd/debris_hq.jpg'));
		$this->assertSame($dpath . 'planeten-hd/mond_hq.jpg', $resolver->url($dpath, 'mond', true));
		$this->assertSame($dpath . 'planeten-hd/mond.jpg', $resolver->url($dpath, 'mond', false));
	}

	private function removeTree(string $dir): void
	{
		if (is_link($dir)) {
			unlink($dir);
			return;
		}
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
			if (is_link($path) || is_file($path)) {
				unlink($path);
			} else {
				$this->removeTree($path);
			}
		}
		rmdir($dir);
	}
}
