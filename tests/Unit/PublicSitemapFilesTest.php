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
			$expected[] = PublicSeo::canonicalUrl('https://moon.hive.pizza/', $page, 'en');
		}

		sort($locs);
		sort($expected);

		$this->assertSame($expected, $locs);
		$this->assertNotContains('https://moon.hive.pizza/index.php?page=news', $locs);
		$this->assertContains('https://moon.hive.pizza/', $locs);
		$this->assertContains('https://moon.hive.pizza/index.php?page=disclamer', $locs);
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
