<?php

use HiveNova\Core\PublicScreensService;

use PHPUnit\Framework\TestCase;

class PublicScreensServiceTest extends TestCase
{
	public function testAltKeyFromFilename(): void
	{
		$this->assertSame('screenAltGalaxy', PublicScreensService::altKey('galaxy.jpg'));
		$this->assertSame('screenAltFleet', PublicScreensService::altKey('Fleet.PNG'));
		$this->assertSame('screenAltScreenshot', PublicScreensService::altKey('...'));
	}

	public function testAltTextUsesLanguageKey(): void
	{
		$lng = ['screenAltGalaxy' => 'Galaxy map'];
		$this->assertSame('Galaxy map', PublicScreensService::altText('galaxy.jpg', $lng));
	}

	public function testAltTextFallsBackToCleanFilename(): void
	{
		$this->assertSame('Overview', PublicScreensService::altText('overview.jpg', []));
		$this->assertSame('Empire view', PublicScreensService::altText('empire-view.png', []));
		$this->assertSame('Screenshot', PublicScreensService::altText('', []));
	}

	public function testAltTextReadsObjectLanguageBag(): void
	{
		$lng = new ArrayObject(['screenAltBuild' => 'Buildings and construction']);
		$this->assertSame('Buildings and construction', PublicScreensService::altText('build.jpg', $lng));
	}
}
