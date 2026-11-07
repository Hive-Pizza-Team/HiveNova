<?php

use HiveNova\Core\PublicSeo;

use PHPUnit\Framework\TestCase;

class PublicSitemapFilesTest extends TestCase
{
	public function testSitemapXmlMatchesPublicSeoPagesAndOmitsNews(): void
	{
		$path = ROOT_PATH.'sitemap.xml';
		$this->assertFileExists($path);

		$xml = simplexml_load_file($path);
		$this->assertNotFalse($xml);
		$this->assertSame('urlset', $xml->getName());

		$locs = [];
		foreach ($xml->url as $url) {
			$locs[] = (string) $url->loc;
		}

		$expected = [];
		foreach (PublicSeo::SITEMAP_PAGES as $page) {
			foreach (self::languageKeys() as $lang) {
				$expected[] = PublicSeo::canonicalUrl('https://moon.hive.pizza/', $page, $lang);
			}
		}

		sort($locs);
		sort($expected);

		$this->assertSame($expected, $locs);
		$this->assertNotContains('https://moon.hive.pizza/index.php?page=news', $locs);
		$this->assertNotContains('https://moon.hive.pizza/index.php?page=banList', $locs);
		$this->assertContains('https://moon.hive.pizza/', $locs);
		$this->assertContains('https://moon.hive.pizza/index.php?page=disclamer', $locs);
		$this->assertContains('https://moon.hive.pizza/index.php?page=rules&lang=de', $locs);
	}

	public function testSitemapXmlIsInSyncWithGenerator(): void
	{
		$raw = file_get_contents(ROOT_PATH.'sitemap.xml');
		$this->assertIsString($raw);
		$raw = str_replace("\r\n", "\n", $raw);
		$this->assertSame(1, preg_match('#<lastmod>(\d{4}-\d{2}-\d{2})</lastmod>#', $raw, $m));

		$this->assertSame(
			PublicSeo::sitemapXml('https://moon.hive.pizza/', self::languageKeys(), $m[1]),
			$raw,
			'sitemap.xml is stale; run: php scripts/generate-sitemap.php --lastmod='.$m[1]
		);
	}

	public function testSitemapXmlCarriesHreflangAlternatesAndLastmod(): void
	{
		$xml = PublicSeo::sitemapXml('https://moon.hive.pizza/', ['en', 'de'], '2026-01-02');
		$doc = simplexml_load_string($xml);
		$this->assertNotFalse($doc);

		$this->assertCount(count(PublicSeo::SITEMAP_PAGES) * 2, $doc->url);
		$this->assertSame('2026-01-02', (string) $doc->url[0]->lastmod);
		$this->assertStringNotContainsString('<changefreq>', $xml);
		$this->assertStringNotContainsString('<priority>', $xml);

		$links = $doc->url[0]->children('xhtml', true);
		$hreflangs = [];
		foreach ($links->link as $link) {
			$hreflangs[(string) $link['hreflang']] = (string) $link['href'];
		}
		$this->assertSame(['en', 'de', 'x-default'], array_keys($hreflangs));
		$this->assertSame('https://moon.hive.pizza/', $hreflangs['x-default']);
		$this->assertSame('https://moon.hive.pizza/index.php?lang=de', $hreflangs['de']);
	}

	/** @return list<string> */
	private static function languageKeys(): array
	{
		$keys = array_map('basename', glob(ROOT_PATH.'language/*', GLOB_ONLYDIR));
		sort($keys);

		return $keys;
	}

	public function testSitemapIndexIsXmlNotHtml(): void
	{
		$path = ROOT_PATH.'sitemap_index.xml';
		$this->assertFileExists($path);

		$raw = file_get_contents($path);
		$this->assertIsString($raw);
		$this->assertStringStartsWith('<?xml', trim($raw));
		$this->assertStringNotContainsString('<html', strtolower($raw));

		$xml = simplexml_load_string($raw);
		$this->assertNotFalse($xml);
		$this->assertSame('sitemapindex', $xml->getName());
		$this->assertSame('https://moon.hive.pizza/sitemap.xml', (string) $xml->sitemap->loc);
	}
}
