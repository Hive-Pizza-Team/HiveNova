<?php

use HiveNova\Core\PublicSeo;

use PHPUnit\Framework\TestCase;

class PublicSeoTest extends TestCase
{

	public function testNormalizeCanonicalOriginDefaultsAndCleans(): void
	{
		$this->assertSame('https://moonsiege.com/', PublicSeo::normalizeCanonicalOrigin(''));
		$this->assertSame('https://moonsiege.com/', PublicSeo::normalizeCanonicalOrigin('not a url'));
		$this->assertSame('https://moonsiege.com/', PublicSeo::normalizeCanonicalOrigin('https://moonsiege.com'));
		$this->assertSame('https://moonsiege.com/', PublicSeo::normalizeCanonicalOrigin('https://moonsiege.com/path?x=1'));
		$this->assertSame('https://example.test/', PublicSeo::normalizeCanonicalOrigin('example.test'));
		$this->assertSame('http://localhost:8080/', PublicSeo::normalizeCanonicalOrigin('http://localhost:8080/foo'));
	}

	public function testCanonicalBasePathUsesDefaultWithoutConfig(): void
	{
		$this->assertSame(PublicSeo::DEFAULT_CANONICAL_ORIGIN, PublicSeo::canonicalBasePath());
		$this->assertSame('https://moonsiege.com/', PublicSeo::DEFAULT_CANONICAL_ORIGIN);
	}

	public function testCanonicalBasePathHonorsConfiguredOriginOverride(): void
	{
		$this->assertSame(
			'https://canonical.example/',
			PublicSeo::canonicalBasePath('https://Canonical.Example/')
		);
	}

	public function testHreflangAndFooterLinksHonorCallerBasePath(): void
	{
		$requestBase = 'https://dev.example/uni1/';
		$langs = ['en' => 'English', 'de' => 'Deutsch'];
		$hreflang = PublicSeo::hreflangUrls($requestBase, 'rules', $langs);
		$this->assertSame('https://dev.example/uni1/index.php?page=rules', $hreflang['en']);
		$this->assertSame('https://dev.example/uni1/index.php?page=rules&lang=de', $hreflang['de']);

		$footer = PublicSeo::footerLinks($requestBase, 'de', ['siteTitleRules' => 'Rules']);
		$this->assertSame('https://dev.example/uni1/index.php?page=rules&lang=de', $footer[0]['url']);
	}

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
		$this->assertSame('Moon — Free browser space strategy — Uni 1 frontier', $title);
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
		$this->assertSame('index, follow', PublicSeo::robotsContent('disclamer', true));
	}

	public function testSitemapPagesOmitNewsAndKeepContact(): void
	{
		$this->assertNotContains('news', PublicSeo::SITEMAP_PAGES);
		$this->assertContains('disclamer', PublicSeo::SITEMAP_PAGES);
		$this->assertContains('index', PublicSeo::SITEMAP_PAGES);
		$this->assertContains('register', PublicSeo::SITEMAP_PAGES);
		$this->assertContains('rules', PublicSeo::SITEMAP_PAGES);
		$this->assertContains('screens', PublicSeo::SITEMAP_PAGES);
		$this->assertContains('battleHall', PublicSeo::SITEMAP_PAGES);
		$this->assertNotContains('banList', PublicSeo::SITEMAP_PAGES);
		$this->assertSame('noindex, follow', PublicSeo::robotsContent('banList'));
	}

	public function testFooterLinksUseLocalizedTitlesAndLanguageUrls(): void
	{
		$lng = [
			'siteTitleRegister'   => 'Register',
			'siteTitleRules'      => 'Rules',
			'siteTitleScreens'    => 'Screenshots',
			'siteTitleBattleHall' => 'Hall of Fame',
		];

		$links = PublicSeo::footerLinks('https://moon.hive.pizza/', 'de', $lng);

		$this->assertSame(
			['Register', 'Rules', 'Screenshots', 'Hall of Fame'],
			array_column($links, 'label')
		);
		$this->assertSame(
			'https://moon.hive.pizza/index.php?page=rules&lang=de',
			$links[1]['url']
		);
		$this->assertSame([], PublicSeo::footerLinks('https://moon.hive.pizza/', 'en', []));
	}

	public function testFaqJsonLdMirrorsVisibleQuestions(): void
	{
		$this->assertSame('', PublicSeo::faqJsonLd([]));

		$json = PublicSeo::faqJsonLd([
			['q' => 'Is MOON free?', 'a' => 'Yes. <b>No</b> download.'],
			['q' => 'Mobile?', 'a' => 'Yes.'],
		]);
		$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame('FAQPage', $data['@type']);
		$this->assertCount(2, $data['mainEntity']);
		$this->assertSame('Question', $data['mainEntity'][0]['@type']);
		$this->assertSame('Is MOON free?', $data['mainEntity'][0]['name']);
		$this->assertSame('Answer', $data['mainEntity'][0]['acceptedAnswer']['@type']);
		$this->assertStringNotContainsString('<b>', $json);
	}

	public function testTwitterSiteHandle(): void
	{
		$this->assertSame('@PizzaOnHive', PublicSeo::TWITTER_SITE);
	}

	public function testDisclaimerAliasRedirectsToLegacySpelling(): void
	{
		$this->assertSame('disclamer', PublicSeo::loginPageAlias('disclaimer'));
		$this->assertNull(PublicSeo::loginPageAlias('disclamer'));
		$this->assertNull(PublicSeo::loginPageAlias('rules'));
		$this->assertSame('index.php?page=disclamer', PublicSeo::aliasRedirectLocation('disclamer', 'en'));
		$this->assertSame('index.php?page=disclamer&lang=de', PublicSeo::aliasRedirectLocation('disclamer', 'de'));
		$this->assertSame('index.php?page=disclamer', PublicSeo::loginAliasRedirectTarget('disclaimer', 'en'));
		$this->assertSame('index.php?page=disclamer&lang=de', PublicSeo::loginAliasRedirectTarget('disclaimer', 'de'));
		$this->assertNull(PublicSeo::loginAliasRedirectTarget('disclamer'));
		$this->assertNull(PublicSeo::loginAliasRedirectTarget('rules'));
	}

	public function testEntitySameAsListsGenuinePublicUrls(): void
	{
		$sameAs = PublicSeo::entitySameAs();
		$this->assertSame([
			'https://discord.gg/bP6ksCeEUk',
			'https://github.com/Hive-Pizza-Team/HiveNova',
			'https://peakd.com/@hive.pizza',
			'https://hive.pizza/',
		], $sameAs);
		$this->assertNotContains('https://twitter.com/PizzaOnHive', $sameAs);
		$this->assertNotContains('https://x.com/PizzaOnHive', $sameAs);
	}

	public function testIndexJsonLdVideoGameIncludesSameAsAndPublisher(): void
	{
		$json = PublicSeo::indexJsonLd(
			'Moon',
			'https://moon.hive.pizza/',
			'A free browser space strategy game.',
			'https://moon.hive.pizza/styles/resource/images/login/HiveNova.png'
		);
		$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame('https://schema.org', $data['@context']);
		$this->assertSame('VideoGame', $data['@type']);
		$this->assertSame('Moon', $data['name']);
		$this->assertSame('https://moon.hive.pizza/', $data['url']);
		$this->assertSame(PublicSeo::entitySameAs(), $data['sameAs']);
		$this->assertSame('Organization', $data['publisher']['@type']);
		$this->assertSame('Hive Pizza Team', $data['publisher']['name']);
		$this->assertSame('https://hive.pizza/', $data['publisher']['url']);
		$this->assertSame(PublicSeo::entitySameAs(), $data['publisher']['sameAs']);
		$this->assertSame('Organization', $data['contributor']['@type']);
		$this->assertSame('Team Mithril', $data['contributor']['name']);
		$this->assertSame('https://github.com/srbde', $data['contributor']['url']);
		$this->assertArrayNotHasKey('aggregateRating', $data);
		$this->assertArrayNotHasKey('reviewCount', $data);
		$this->assertStringNotContainsString('\\/', $json);
	}
}
