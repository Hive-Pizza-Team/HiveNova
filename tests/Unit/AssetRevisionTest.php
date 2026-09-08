<?php

declare(strict_types=1);

use HiveNova\Core\AssetRevision;
use PHPUnit\Framework\TestCase;

class AssetRevisionTest extends TestCase
{
	public function testVersionSuffixAloneWhenNoMtime(): void
	{
		$this->assertSame('2.0', AssetRevision::forVersion('2.0'));
		$this->assertSame('.0.0', AssetRevision::forVersion('1.0.0.0'));
	}

	public function testAppendsStylesheetMtimeSoCssDeploysBustCache(): void
	{
		$this->assertSame('2.0.1710000000', AssetRevision::forVersion('2.0', 1710000000));
	}

	public function testFilesystemRevIncludesMainCssMtime(): void
	{
		$rev = AssetRevision::fromFilesystem('2.0');
		$this->assertMatchesRegularExpression('/^2\.0\.\d+$/', $rev);
	}
}
