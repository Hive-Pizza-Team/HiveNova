<?php

/**
 * Regenerate sitemap.xml from PublicSeo::SITEMAP_PAGES and the language/ directories.
 *
 * Usage:
 *   php scripts/generate-sitemap.php [--lastmod=YYYY-MM-DD] [--base=https://moonsiege.com/]
 *
 * Run after changing SITEMAP_PAGES, adding a language, or editing public page
 * content, then commit the result. PublicSitemapFilesTest fails if the file drifts.
 * Default --base is PublicSeo::CANONICAL_BASE. Also rewrites sitemap_index.xml.
 */

use HiveNova\Core\PublicSeo;

require dirname(__DIR__).'/vendor/autoload.php';

$opts = getopt('', ['lastmod::', 'base::']);
$lastmod = $opts['lastmod'] ?? gmdate('Y-m-d');
$base = $opts['base'] ?? PublicSeo::CANONICAL_BASE;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmod)) {
	fwrite(STDERR, "--lastmod must be YYYY-MM-DD\n");
	exit(1);
}

$langKeys = array_map('basename', glob(dirname(__DIR__).'/language/*', GLOB_ONLYDIR));
sort($langKeys);

$root = dirname(__DIR__);
$path = $root.'/sitemap.xml';
file_put_contents($path, PublicSeo::sitemapXml($base, $langKeys, $lastmod));
$indexPath = $root.'/sitemap_index.xml';
file_put_contents($indexPath, PublicSeo::sitemapIndexXml($base));
echo 'Wrote '.$path.' and '.$indexPath.' ('.count($langKeys).' languages, lastmod '.$lastmod.")\n";
