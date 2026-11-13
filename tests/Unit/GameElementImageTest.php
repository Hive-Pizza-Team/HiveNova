<?php

use HiveNova\Core\GameElementImage;
use PHPUnit\Framework\TestCase;

class GameElementImageTest extends TestCase
{
	private string $fixtureRoot;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtureRoot = sys_get_temp_dir() . '/hivenova-element-img-' . uniqid('', true);

		$novaHd = $this->fixtureRoot . '/styles/theme/nova/gebaeude-hd';
		$novaGif = $this->fixtureRoot . '/styles/theme/nova/gebaeude';
		$hiveGif = $this->fixtureRoot . '/styles/theme/hive/gebaeude';
		$gowGif = $this->fixtureRoot . '/styles/theme/gow/gebaeude';
		mkdir($novaHd, 0777, true);
		mkdir($novaGif, 0777, true);
		mkdir($hiveGif, 0777, true);
		mkdir($gowGif, 0777, true);

		file_put_contents($novaHd . '/1.png', 'png');
		file_put_contents($novaHd . '/2.png', 'png');
		file_put_contents($novaHd . '/readme.txt', 'skip');
		file_put_contents($novaGif . '/1.gif', 'gif');
		file_put_contents($novaGif . '/21.gif', 'gif');
		file_put_contents($novaGif . '/601.jpg', 'jpg');

		// hive gebaeude-hd is a symlink to nova, matching the repo layout
		symlink('../nova/gebaeude-hd', $this->fixtureRoot . '/styles/theme/hive/gebaeude-hd');

		// gow has no HD folder and no symlink
	}

	protected function tearDown(): void
	{
		$this->removeTree($this->fixtureRoot);
		parent::tearDown();
	}

	public function testPrefersHdPngOnHiveViaSymlink(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame(
			'./styles/theme/hive/gebaeude-hd/1.png',
			$resolver->url('./styles/theme/hive/', 1)
		);
		$this->assertTrue($resolver->hasHd('./styles/theme/hive/', 1));
	}

	public function testFallsBackToGifWhenNoHdFile(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame(
			'./styles/theme/hive/gebaeude/21.gif',
			$resolver->url('./styles/theme/hive/', 21)
		);
		$this->assertFalse($resolver->hasHd('./styles/theme/hive/', 21));
	}

	public function testOfficerFallbackIsJpg(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame(
			'./styles/theme/hive/gebaeude/601.jpg',
			$resolver->url('./styles/theme/hive/', 601)
		);
		$this->assertSame('jpg', GameElementImage::classicExtension(600));
		$this->assertSame('jpg', GameElementImage::classicExtension(699));
		$this->assertSame('gif', GameElementImage::classicExtension(599));
		$this->assertSame('gif', GameElementImage::classicExtension(1));
	}

	public function testGowWithoutLocalHdUsesSharedNovaFile(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame(
			'styles/theme/nova/gebaeude-hd/2.png',
			$resolver->url('styles/theme/gow/', 2)
		);
		$this->assertTrue($resolver->hasHd('styles/theme/gow/', 2));
	}

	public function testDpathWithoutLeadingDotIsPreservedOnHdAndClassic(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame(
			'styles/theme/nova/gebaeude-hd/1.png',
			$resolver->url('styles/theme/nova/', 1)
		);
		$this->assertSame(
			'styles/theme/nova/gebaeude/21.gif',
			$resolver->url('styles/theme/nova/', 21)
		);
	}

	public function testEmptyDpathUsesDefaultThemePrefix(): void
	{
		if (!defined('DEFAULT_THEME')) {
			define('DEFAULT_THEME', 'hive');
		}

		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame(
			'./styles/theme/hive/gebaeude-hd/1.png',
			$resolver->url('', 1)
		);
		$this->assertSame(
			'./styles/theme/hive/gebaeude/21.gif',
			$resolver->url('', 21)
		);
	}

	public function testHdExtensionsListsNumericPngsOnly(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);
		$map = $resolver->hdExtensions('./styles/theme/hive/');

		$this->assertSame(['1' => 'png', '2' => 'png'], $map);
	}

	public function testNonPositiveIdsNeverResolveHd(): void
	{
		$resolver = new GameElementImage($this->fixtureRoot);

		$this->assertSame('./styles/theme/hive/gebaeude/0.gif', $resolver->url('./styles/theme/hive/', 0));
		$this->assertFalse($resolver->hasHd('./styles/theme/hive/', -3));
	}

	public function testRepoBatch1AssetsResolveAndShipyardIsOmitted(): void
	{
		$resolver = new GameElementImage();
		$dpath = './styles/theme/hive/';

		foreach ([1, 2, 3, 4, 14, 22, 31] as $id) {
			$this->assertTrue($resolver->hasHd($dpath, $id), 'expected HD still for building ' . $id);
			$this->assertSame(
				'./styles/theme/hive/gebaeude-hd/' . $id . '.png',
				$resolver->url($dpath, $id)
			);
		}

		$this->assertFalse($resolver->hasHd($dpath, 21), 'shipyard v1 was left out (no v2 still)');
		$this->assertSame('./styles/theme/hive/gebaeude/21.gif', $resolver->url($dpath, 21));
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
