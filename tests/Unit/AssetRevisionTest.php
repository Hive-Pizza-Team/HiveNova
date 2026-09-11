<?php

declare(strict_types=1);

use HiveNova\Core\AssetRevision;
use PHPUnit\Framework\TestCase;

class AssetRevisionTest extends TestCase
{
	/** @var list<string> */
	private array $tempRoots = [];

	protected function tearDown(): void
	{
		foreach ($this->tempRoots as $root) {
			$this->removeTree($root);
		}
		$this->tempRoots = [];
		parent::tearDown();
	}

	public function testVersionSuffixAloneWhenNoMtime(): void
	{
		$this->assertSame('2.0', AssetRevision::forVersion('2.0'));
		$this->assertSame('.0.0', AssetRevision::forVersion('1.0.0.0'));
	}

	public function testAppendsAssetMtimeSoDeploysBustCache(): void
	{
		$this->assertSame('2.0.1710000000', AssetRevision::forVersion('2.0', 1710000000));
	}

	public function testFilesystemRevIncludesMainCssMtime(): void
	{
		$rev = AssetRevision::fromFilesystem('2.0');
		$this->assertMatchesRegularExpression('/^2\.0\.\d+$/', $rev);
	}

	public function testFilesystemRevUsesMaxOfCssAndPushSubscribeJs(): void
	{
		$cssMtime = 1_700_000_000;
		$jsMtime = 1_700_000_500;
		$root = $this->makeAssetTree($cssMtime, $jsMtime);

		$this->assertSame('2.0.' . $jsMtime, AssetRevision::fromFilesystem('2.0', $root));
		$this->assertSame($jsMtime, AssetRevision::maxAssetMtime($root));
	}

	public function testFilesystemRevChangesWhenPushSubscribeJsChangesAndCssDoesNot(): void
	{
		$cssMtime = 1_700_000_000;
		$jsMtime = 1_700_000_100;
		$root = $this->makeAssetTree($cssMtime, $jsMtime);

		$before = AssetRevision::fromFilesystem('2.0', $root);
		$this->assertSame('2.0.' . $jsMtime, $before);

		$newerJs = 1_700_000_200;
		$this->assertTrue(touch($root . 'scripts/game/push-subscribe.js', $newerJs));
		clearstatcache(true, $root . 'scripts/game/push-subscribe.js');
		clearstatcache(true, $root . 'styles/resource/css/ingame/main.css');

		$after = AssetRevision::fromFilesystem('2.0', $root);
		$this->assertSame('2.0.' . $newerJs, $after);
		$this->assertNotSame($before, $after);
		$this->assertSame($cssMtime, (int) filemtime($root . 'styles/resource/css/ingame/main.css'));
	}

	public function testFilesystemRevStillBumpsWhenOnlyMainCssChanges(): void
	{
		$cssMtime = 1_700_000_300;
		$jsMtime = 1_700_000_100;
		$root = $this->makeAssetTree($cssMtime, $jsMtime);

		$this->assertSame('2.0.' . $cssMtime, AssetRevision::fromFilesystem('2.0', $root));
	}

	public function testFilesystemRevIgnoresMissingFingerprintFiles(): void
	{
		$root = $this->makeEmptyRoot();
		$this->assertSame('2.0', AssetRevision::fromFilesystem('2.0', $root));
		$this->assertNull(AssetRevision::maxAssetMtime($root));
	}

	private function makeAssetTree(int $cssMtime, int $jsMtime): string
	{
		$root = $this->makeEmptyRoot();
		$cssDir = $root . 'styles/resource/css/ingame';
		$jsDir = $root . 'scripts/game';
		mkdir($cssDir, 0777, true);
		mkdir($jsDir, 0777, true);

		$css = $cssDir . '/main.css';
		$js = $jsDir . '/push-subscribe.js';
		file_put_contents($css, 'body{}');
		file_put_contents($js, 'void 0;');
		touch($css, $cssMtime);
		touch($js, $jsMtime);
		clearstatcache(true);

		return $root;
	}

	private function makeEmptyRoot(): string
	{
		$root = sys_get_temp_dir() . '/hivenova-asset-rev-' . uniqid('', true) . '/';
		mkdir($root, 0777, true);
		$this->tempRoots[] = $root;

		return $root;
	}

	private function removeTree(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($items as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
		rmdir($path);
	}
}
