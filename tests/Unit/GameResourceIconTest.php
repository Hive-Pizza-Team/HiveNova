<?php

use HiveNova\Core\GameResourceIcon;
use PHPUnit\Framework\TestCase;

class GameResourceIconTest extends TestCase
{
	private string $fixtureRoot;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtureRoot = sys_get_temp_dir() . '/hivenova-res-icon-' . uniqid('', true);
		$hiveHd = $this->fixtureRoot . '/styles/theme/hive/images-hd';
		$hiveClassic = $this->fixtureRoot . '/styles/theme/hive/images';
		mkdir($hiveHd, 0777, true);
		mkdir($hiveClassic, 0777, true);
		file_put_contents($hiveHd . '/metal.png', 'png');
		file_put_contents($hiveClassic . '/metal.gif', 'gif');
		file_put_contents($hiveClassic . '/noradium.gif', 'gif');
		mkdir($this->fixtureRoot . '/styles/theme/nova', 0777, true);
		symlink('../hive/images-hd', $this->fixtureRoot . '/styles/theme/nova/images-hd');
	}

	protected function tearDown(): void
	{
		$this->removeTree($this->fixtureRoot);
		parent::tearDown();
	}

	public function testPrefersNativeHdPng(): void
	{
		$resolver = new GameResourceIcon($this->fixtureRoot);
		$this->assertSame('./styles/theme/hive/images-hd/metal.png', $resolver->url('./styles/theme/hive/', 'metal'));
		$this->assertTrue($resolver->hasHd('./styles/theme/hive/', 'metal'));
	}

	public function testFallsBackToClassicGif(): void
	{
		$resolver = new GameResourceIcon($this->fixtureRoot);
		$this->assertSame('./styles/theme/hive/images/noradium.gif', $resolver->url('./styles/theme/hive/', 'noradium'));
		$this->assertFalse($resolver->hasHd('./styles/theme/hive/', 'noradium'));
	}

	public function testNovaThemeFollowsHiveSymlink(): void
	{
		$resolver = new GameResourceIcon($this->fixtureRoot);
		$this->assertSame('./styles/theme/nova/images-hd/metal.png', $resolver->url('./styles/theme/nova/', 'metal'));
	}

	public function testRejectsUnsafeNames(): void
	{
		$resolver = new GameResourceIcon($this->fixtureRoot);
		$this->assertSame('./styles/theme/hive/images/metal.gif', $resolver->url('./styles/theme/hive/', '../x'));
	}

	public function testRepoIconsResolveAtNativeSize(): void
	{
		$resolver = new GameResourceIcon();
		$dpath = './styles/theme/hive/';
		foreach (['metal', 'crystal', 'deuterium', 'energy', 'darkmatter'] as $name) {
			$this->assertTrue($resolver->hasHd($dpath, $name), $name);
			$path = (defined('ROOT_PATH') ? ROOT_PATH : '') . 'styles/theme/hive/images-hd/' . $name . '.png';
			$this->assertFileExists($path);
			$info = getimagesize($path);
			$this->assertSame([42, 22], [$info[0], $info[1]], $name . ' must stay 42x22');
			$this->assertSame($dpath . 'images-hd/' . $name . '.png', $resolver->url($dpath, $name));
		}
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
