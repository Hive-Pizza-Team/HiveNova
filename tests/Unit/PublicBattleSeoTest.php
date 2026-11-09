<?php

use HiveNova\Core\PublicBattleSeo;
use HiveNova\Core\PublicSeo;
use PHPUnit\Framework\TestCase;

class PublicBattleSeoTest extends TestCase
{
	public function testSignupCtaUrlUsesRefAndSrc(): void
	{
		$this->assertSame(
			'index.php?ref=1&src=battlehall',
			PublicBattleSeo::signupCtaUrl(PublicBattleSeo::SRC_HALL)
		);
		$this->assertSame(
			'index.php?ref=1&src=battlereport',
			PublicBattleSeo::signupCtaUrl(PublicBattleSeo::SRC_REPORT)
		);
		$this->assertSame(
			'index.php?ref=1&src=battlehall',
			PublicBattleSeo::signupCtaUrl('bad src!!')
		);
	}

	public function testReportCanonicalKeepsRidAndStripsRefSrcIfPassed(): void
	{
		$url = PublicSeo::canonicalUrl(
			'https://moonsiege.com/',
			'battle',
			'en',
			'en',
			['rid' => 'abc123abc123abc123abc123abc123ab', 'ref' => 1, 'src' => 'x']
		);
		$this->assertSame(
			'https://moonsiege.com/index.php?page=battle&rid=abc123abc123abc123abc123abc123ab',
			$url
		);
		$this->assertStringNotContainsString('ref=', $url);
		$this->assertStringNotContainsString('src=', $url);
	}

	public function testReportHreflangIncludesRid(): void
	{
		$urls = PublicBattleSeo::reportHreflangUrls(
			'https://moon.hive.pizza/',
			'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			['en' => 'English', 'de' => 'Deutsch']
		);
		$this->assertSame(
			'https://moon.hive.pizza/index.php?page=battle&rid=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			$urls['en']
		);
		$this->assertSame(
			'https://moon.hive.pizza/index.php?page=battle&rid=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa&lang=de',
			$urls['de']
		);
		$this->assertSame($urls['en'], $urls['x-default']);
	}

	public function testHallTitleAndDescriptionAreUniverseSpecific(): void
	{
		$lng = [
			'metaTitleBattleHallUni' => '%s Battle Hall — %s',
			'metaDescriptionBattleHallUni' => 'Biggest fleet battles in %2$s on %1$s.',
		];
		$this->assertSame(
			'Moon Battle Hall — Universe 1',
			PublicBattleSeo::hallDocumentTitle('Moon', 'Universe 1', $lng)
		);
		$this->assertSame(
			'Biggest fleet battles in Universe 1 on Moon.',
			PublicBattleSeo::hallMetaDescription('Moon', 'Universe 1', $lng)
		);
	}

	public function testReportTitleMatchesShareFriendlyPattern(): void
	{
		$title = PublicBattleSeo::reportDocumentTitle(
			'vanen3',
			'crazyphantombr',
			488700000,
			'Moon',
			['metaTitleBattleReport' => '%s vs %s: %s units destroyed | %s Battle Hall']
		);
		$this->assertSame(
			'vanen3 vs crazyphantombr: 488.7M units destroyed | Moon Battle Hall',
			$title
		);
	}

	public function testFormatCompactUnits(): void
	{
		$this->assertSame('488.7M', PublicBattleSeo::formatCompactUnits(488700000));
		$this->assertSame('1.2B', PublicBattleSeo::formatCompactUnits(1200000000));
		$this->assertSame('1.5K', PublicBattleSeo::formatCompactUnits(1500));
		$this->assertSame('42', PublicBattleSeo::formatCompactUnits(42));
	}

	public function testDebrisSummaryUsesResourceConstants(): void
	{
		$summary = PublicBattleSeo::formatDebrisSummary([
			RESOURCE_METAL => 1200000,
			RESOURCE_CRYSTAL => 800000,
			RESOURCE_DEUTERIUM => 0,
		], []);
		$this->assertSame('1.2M metal, 800K crystal', $summary);
	}

	public function testBattlePageIsIndexable(): void
	{
		$this->assertContains('battle', PublicSeo::INDEXABLE_PAGES);
		$this->assertSame('index, follow', PublicSeo::robotsContent('battle', true));
	}

	public function testOgImageConstants(): void
	{
		$this->assertSame(1200, PublicBattleSeo::OG_IMAGE_WIDTH);
		$this->assertSame(630, PublicBattleSeo::OG_IMAGE_HEIGHT);
		$this->assertSame(
			'https://moonsiege.com/styles/resource/images/login/battle-hall-og.png',
			PublicBattleSeo::ogImageUrl('https://moonsiege.com/')
		);
	}
}
