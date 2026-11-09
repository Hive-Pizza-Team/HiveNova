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
				$expected[] = PublicSeo::canonicalUrl(PublicSeo::CANONICAL_BASE, $page, $lang);
			}
		}

		sort($locs);
		sort($expected);

		$this->assertSame($expected, $locs);
		$this->assertNotContains(PublicSeo::CANONICAL_BASE.'index.php?page=news', $locs);
		$this->assertNotContains(PublicSeo::CANONICAL_BASE.'index.php?page=banList', $locs);
		$this->assertContains(PublicSeo::CANONICAL_BASE, $locs);
		$this->assertContains(PublicSeo::CANONICAL_BASE.'index.php?page=disclamer', $locs);
		$this->assertContains(PublicSeo::CANONICAL_BASE.'index.php?page=rules&lang=de', $locs);
	}

	public function testSitemapXmlIsInSyncWithGenerator(): void
	{
		$raw = file_get_contents(ROOT_PATH.'sitemap.xml');
		$this->assertIsString($raw);
		$raw = str_replace("\r\n", "\n", $raw);
		$this->assertSame(1, preg_match('#<lastmod>(\d{4}-\d{2}-\d{2})</lastmod>#', $raw, $m));

		$this->assertSame(
			PublicSeo::sitemapXml(PublicSeo::CANONICAL_BASE, self::languageKeys(), $m[1]),
			$raw,
			'sitemap.xml is stale; run: php scripts/generate-sitemap.php --lastmod='.$m[1]
		);
	}

	public function testSitemapXmlCarriesHreflangAlternatesAndLastmod(): void
	{
		$xml = PublicSeo::sitemapXml(PublicSeo::CANONICAL_BASE, ['en', 'de'], '2026-01-02');
		$doc = simplexml_load_string($xml);
		$this->assertNotFalse($doc);

		$this->assertCount(count(PublicSeo::SITEMAP_PAGES) * 2, $doc->url);
		$this->assertSame('2026-01-02', (string) $doc->url[0]->lastmod);
		$this->assertStringNotContainsString('<changefreq>', $xml);
		$this->assertStringNotContainsString('<priority>', $xml);

		// Attributes are in no namespace. Array access on an xhtml-namespaced
		// element looks them up in that namespace and returns empty strings.
		$hreflangs = [];
		foreach ($doc->url[0]->children('http://www.w3.org/1999/xhtml') as $link) {
			$attrs = $link->attributes();
			$hreflangs[(string) $attrs['hreflang']] = (string) $attrs['href'];
		}
		$this->assertSame(['en', 'de', 'x-default'], array_keys($hreflangs));
		$this->assertSame(PublicSeo::CANONICAL_BASE, $hreflangs['x-default']);
		$this->assertSame(PublicSeo::CANONICAL_BASE.'index.php?lang=de', $hreflangs['de']);
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
		$this->assertSame(PublicSeo::CANONICAL_BASE.'sitemap.xml', (string) $xml->sitemap->loc);
	}
}
