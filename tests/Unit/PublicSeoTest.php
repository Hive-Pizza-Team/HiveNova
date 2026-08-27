<?php

use HiveNova\Core\PublicSeo;

use PHPUnit\Framework\TestCase;

class PublicSeoTest extends TestCase
{
	public function testNormalizePageDefaultsEmptyToIndex(): void
	{
		$this->assertSame('index', PublicSeo::normalizePage(''));
		$this->assertSame('index', PublicSeo::normalizePage('overview'));
		$this->assertSame('rules', PublicSeo::normalizePage('rules'));
	}

	public function testCanonicalHomepageDefaultLangIsBareBase(): void
	{
		$url = PublicSeo::canonicalUrl('https://moon.hive.pizza/', 'index', 'en');
		$this->assertSame('https://moon.hive.pizza/', $url);
	}

	public function testCanonicalHomepageNonDefaultLang(): void
	{
		$url = PublicSeo::canonicalUrl('https://moon.hive.pizza/', 'index', 'de');
		$this->assertSame('https://moon.hive.pizza/index.php?lang=de', $url);
	}

	public function testCanonicalInnerPageIncludesQuery(): void
	{
		$url = PublicSeo::canonicalUrl('https://moon.hive.pizza/', 'rules', 'en');
		$this->assertSame('https://moon.hive.pizza/index.php?page=rules', $url);
	}

	public function testCanonicalNonDefaultLangPreservesPage(): void
	{
		$url = PublicSeo::canonicalUrl('https://moon.hive.pizza/', 'rules', 'de');
		$this->assertSame('https://moon.hive.pizza/index.php?page=rules&lang=de', $url);
	}

	public function testHreflangIncludesXDefaultAndAbsoluteUrls(): void
	{
		$langs = ['en' => 'English', 'de' => 'Deutsch'];
		$urls = PublicSeo::hreflangUrls('https://moon.hive.pizza/', 'screens', $langs);

		$this->assertSame('https://moon.hive.pizza/index.php?page=screens', $urls['en']);
		$this->assertSame('https://moon.hive.pizza/index.php?page=screens&lang=de', $urls['de']);
		$this->assertSame('https://moon.hive.pizza/index.php?page=screens', $urls['x-default']);
	}

	public function testDocumentTitleHomeUsesTemplate(): void
	{
		$lng = ['metaTitleHome' => '%s — Free Hive browser space strategy game'];
		$title = PublicSeo::documentTitle('index', 'Moon', $lng);
		$this->assertSame('Moon — Free Hive browser space strategy game', $title);
	}

	public function testDocumentTitleHomeDefaultTemplateWhenKeyMissing(): void
	{
		$title = PublicSeo::documentTitle('index', 'Moon', []);
		$this->assertSame('Moon — Free browser space strategy game', $title);
	}

	public function testDocumentTitleInnerPageUsesSiteTitle(): void
	{
		$lng = ['siteTitleRules' => 'Rules'];
		$title = PublicSeo::documentTitle('rules', 'Moon', $lng);
		$this->assertSame('Rules - Moon', $title);
	}

	public function testDocumentTitleUnknownPageFallsBackToGameName(): void
	{
		$this->assertSame('Moon', PublicSeo::documentTitle('unknownPage', 'Moon', []));
		$this->assertSame('Moon', PublicSeo::documentTitle('rules', 'Moon', []));
	}

	public function testPageHeadingIndexAndInner(): void
	{
		$this->assertSame(
			'Welcome to Moon',
			PublicSeo::pageHeading('index', 'Moon', ['loginWelcome' => 'Welcome to %s'])
		);
		$this->assertSame(
			'Welcome to Moon',
			PublicSeo::pageHeading('index', 'Moon', [])
		);
		$this->assertSame(
			'Rules',
			PublicSeo::pageHeading('rules', 'Moon', ['siteTitleRules' => 'Rules'])
		);
		$this->assertSame('Moon', PublicSeo::pageHeading('unknownPage', 'Moon', []));
	}

	public function testMetaDescriptionFormatsGameName(): void
	{
		$lng = ['metaDescriptionRules' => 'Official %s game rules.'];
		$desc = PublicSeo::metaDescription('rules', 'Moon', $lng);
		$this->assertSame('Official Moon game rules.', $desc);
	}

	public function testMetaDescriptionWithoutPlaceholder(): void
	{
		$lng = ['metaDescriptionScreens' => 'Screenshots gallery.'];
		$this->assertSame('Screenshots gallery.', PublicSeo::metaDescription('screens', 'Moon', $lng));
	}

	public function testMetaDescriptionFallsBackToIndexThenHardcoded(): void
	{
		$lng = ['metaDescriptionIndex' => 'Index desc for %s'];
		$this->assertSame('Index desc for Moon', PublicSeo::metaDescription('unknownPage', 'Moon', $lng));

		$hardcoded = PublicSeo::metaDescription('unknownPage', 'Moon', []);
		$this->assertStringContainsString('MOON', $hardcoded);
	}

	public function testMetaKeySuffix(): void
	{
		$this->assertSame('Index', PublicSeo::metaKeySuffix(''));
		$this->assertSame('BattleHall', PublicSeo::metaKeySuffix('battleHall'));
	}

	public function testRobotsNoindexForLostPasswordAndEmptyAllow(): void
	{
		$this->assertSame('noindex, follow', PublicSeo::robotsContent('lostPassword'));
		$this->assertSame('noindex, follow', PublicSeo::robotsContent('news', false));
		$this->assertSame('index, follow', PublicSeo::robotsContent('rules', true));
	}
}
