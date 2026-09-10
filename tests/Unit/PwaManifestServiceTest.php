<?php

use HiveNova\Core\PwaManifestService;
use PHPUnit\Framework\TestCase;

class PwaManifestServiceTest extends TestCase
{
	private PwaManifestService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->service = new PwaManifestService();
	}

	public function testEmptyNameFallsBackToHiveNova(): void
	{
		$manifest = $this->service->build('  ');

		$this->assertSame('HiveNova', $manifest['name']);
		$this->assertSame('HiveNova', $manifest['short_name']);
	}

	public function testLongUnicodeNameIsTruncatedForShortName(): void
	{
		$manifest = $this->service->build('Моя очень длинная вселенная');

		$this->assertSame('Моя очень длинная вселенная', $manifest['name']);
		$this->assertSame(12, mb_strlen($manifest['short_name'], 'UTF-8'));
		$this->assertSame('Моя очень дл', $manifest['short_name']);
	}

	public function testStartUrlAndScopeHonorUniPath(): void
	{
		$manifest = $this->service->build('Moon', '/uni1');

		$this->assertSame('/uni1/', $manifest['id']);
		$this->assertSame('/uni1/', $manifest['scope']);
		$this->assertSame('/uni1/game.php?page=overview', $manifest['start_url']);
		$this->assertSame('/uni1/styles/resource/images/pwa/icon-192.png', $manifest['icons'][0]['src']);
	}

	public function testIconsMeetChromeInstallSizes(): void
	{
		$icons = $this->service->icons('/');
		$byKey = [];
		foreach ($icons as $icon) {
			$byKey[$icon['sizes'] . ':' . $icon['purpose']] = $icon;
		}

		$this->assertArrayHasKey('192x192:any', $byKey);
		$this->assertArrayHasKey('512x512:any', $byKey);
		$this->assertArrayHasKey('192x192:maskable', $byKey);
		$this->assertArrayHasKey('512x512:maskable', $byKey);
		$this->assertSame('image/png', $byKey['192x192:any']['type']);
		$this->assertSame('image/png', $byKey['512x512:any']['type']);
		$this->assertStringEndsWith('icon-192.png', $byKey['192x192:any']['src']);
		$this->assertStringEndsWith('icon-512.png', $byKey['512x512:any']['src']);
	}

	public function testScreenshotsIncludeWideAndNarrowFormFactors(): void
	{
		$shots = $this->service->screenshots('/', 'LocalMoon');
		$factors = array_column($shots, 'form_factor');
		$this->assertSame('LocalMoon', $shots[0]['label']);

		$this->assertContains('wide', $factors);
		$this->assertContains('narrow', $factors);
		foreach ($shots as $shot) {
			$this->assertSame('image/png', $shot['type']);
			$this->assertNotEmpty($shot['sizes']);
			$this->assertStringContainsString('/styles/resource/images/pwa/screenshot-', $shot['src']);
		}
	}

	public function testStandaloneDisplayAndThemeColor(): void
	{
		$manifest = $this->service->build('Moon');

		$this->assertSame('standalone', $manifest['display']);
		$this->assertSame('#1a1a2e', $manifest['theme_color']);
		$this->assertSame('#1a1a2e', $manifest['background_color']);
	}
}
