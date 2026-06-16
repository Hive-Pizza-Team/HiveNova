<?php

use HiveNova\Core\PlanetImageUtil;
use PHPUnit\Framework\TestCase;

class PlanetImageUtilTest extends TestCase
{
	public function testVariantOneIsHottestInFamilyRange(): void
	{
		$this->assertSame(1, PlanetImageUtil::variantFromTemperature(250, 120, 260));
		$this->assertSame(5, PlanetImageUtil::variantFromTemperature(125, 120, 260));
	}

	public function testBuildImageNamePadsVariant(): void
	{
		$this->assertSame('trockenplanet03', PlanetImageUtil::buildImageName('trocken', 3));
		$this->assertSame('eisplanet01', PlanetImageUtil::buildImageName('eis', 1));
	}

	public function testRemapLegacyImageUsesTemperature(): void
	{
		$this->assertSame(
			'trockenplanet01',
			PlanetImageUtil::remapLegacyImage('trockenplanet09', 240, 250)
		);
		$this->assertSame(
			'dschjungelplanet05',
			PlanetImageUtil::remapLegacyImage('dschjungelplanet08', 52, 58)
		);
		$this->assertSame('mond', PlanetImageUtil::remapLegacyImage('mond', -10, 10));
	}

	public function testCatalogTempRangeMatchesFiveBuckets(): void
	{
		$first = PlanetImageUtil::catalogTempRangeForVariant(1, 120, 260);
		$last = PlanetImageUtil::catalogTempRangeForVariant(5, 120, 260);

		$this->assertSame(260, $first['tempMax']);
		$this->assertSame(120, $last['tempMin']);
		$this->assertLessThan($first['tempMin'], $last['tempMax']);
	}
}
