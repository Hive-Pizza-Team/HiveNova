<?php

declare(strict_types=1);

use HiveNova\Core\ProductVersion;
use HiveNova\Core\ReleaseNotesService;
use PHPUnit\Framework\TestCase;

class ProductVersionTest extends TestCase
{
	public function testInstallVersionMatchesNewestChangelog(): void
	{
		$version = ProductVersion::current(ROOT_PATH);
		$latest = (new ReleaseNotesService(ROOT_PATH . 'changelog/'))->latestVersion();

		$this->assertNotNull($latest);
		$this->assertSame($latest, $version);
		$this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', $version);
		$this->assertLessThanOrEqual(32, strlen($version));
	}

	public function testDisplayedPrefersTheFileOverAStaleConfigValue(): void
	{
		$this->assertSame(ProductVersion::current(ROOT_PATH), ProductVersion::displayed('2.0', ROOT_PATH));
		$this->assertNotSame('2.0', ProductVersion::displayed('2.0', ROOT_PATH));
	}

	public function testDisplayedFallsBackWhenTheFileIsMissing(): void
	{
		$dir = sys_get_temp_dir() . '/hn-product-version-' . uniqid('', true);
		mkdir($dir, 0777, true);

		try {
			$this->assertSame('2.0', ProductVersion::displayed('2.0', $dir));
			$this->assertSame('', ProductVersion::current($dir));
		} finally {
			rmdir($dir);
		}
	}
}
