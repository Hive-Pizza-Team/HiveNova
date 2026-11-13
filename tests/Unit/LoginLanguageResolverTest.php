<?php

declare(strict_types=1);

use HiveNova\Core\LoginLanguageResolver;
use PHPUnit\Framework\TestCase;

class LoginLanguageResolverTest extends TestCase
{
	/** @var list<string> */
	private array $allowed = ['de', 'en', 'es', 'fr', 'pl', 'pt', 'ru', 'tr'];

	public function testExplicitRequestLangWinsAndPersists(): void
	{
		$decision = LoginLanguageResolver::resolve(
			'de',
			[LoginLanguageResolver::EXPLICIT_COOKIE => 'en', LoginLanguageResolver::LEGACY_COOKIE => 'fr'],
			'en-US,en;q=0.9',
			$this->allowed,
			'de'
		);

		$this->assertSame('de', $decision['language']);
		$this->assertSame('de', $decision['persistExplicit']);
		$this->assertTrue($decision['clearLegacy']);
	}

	public function testExplicitCookieBeatsAcceptLanguage(): void
	{
		$decision = LoginLanguageResolver::resolve(
			null,
			[LoginLanguageResolver::EXPLICIT_COOKIE => 'fr'],
			'en-US,en;q=0.9',
			$this->allowed,
			'de'
		);

		$this->assertSame('fr', $decision['language']);
		$this->assertNull($decision['persistExplicit']);
		$this->assertFalse($decision['clearLegacy']);
	}

	public function testLegacyLangCookieIsIgnored(): void
	{
		$decision = LoginLanguageResolver::resolve(
			null,
			[LoginLanguageResolver::LEGACY_COOKIE => 'de'],
			'en-US,en;q=0.9',
			$this->allowed,
			'de'
		);

		$this->assertSame('en', $decision['language']);
		$this->assertNull($decision['persistExplicit']);
		$this->assertFalse($decision['clearLegacy']);
	}

	public function testAcceptLanguageWhenNoExplicitChoice(): void
	{
		$decision = LoginLanguageResolver::resolve(
			null,
			[],
			'fr-FR,fr;q=0.9',
			$this->allowed,
			'de'
		);

		$this->assertSame('fr', $decision['language']);
		$this->assertNull($decision['persistExplicit']);
	}

	public function testFallsBackToEnglishWhenAcceptLanguageMisses(): void
	{
		$decision = LoginLanguageResolver::resolve(
			null,
			[],
			'ja-JP,ja;q=0.9',
			$this->allowed,
			'de'
		);

		$this->assertSame('en', $decision['language']);
		$this->assertNull($decision['persistExplicit']);
	}

	public function testFallsBackToConfigWhenEnglishUnavailable(): void
	{
		$decision = LoginLanguageResolver::resolve(
			null,
			[],
			'ja',
			['de', 'fr'],
			'de'
		);

		$this->assertSame('de', $decision['language']);
	}

	public function testDisablingExplicitCookieSkipsLangChoice(): void
	{
		$decision = LoginLanguageResolver::resolve(
			null,
			[LoginLanguageResolver::EXPLICIT_COOKIE => 'fr'],
			'en-US,en;q=0.9',
			$this->allowed,
			'de',
			false
		);

		$this->assertSame('en', $decision['language']);
	}

	public function testIgnoresDisallowedRequestAndCookie(): void
	{
		$decision = LoginLanguageResolver::resolve(
			'ja',
			[LoginLanguageResolver::EXPLICIT_COOKIE => 'ja'],
			'pt-BR,pt;q=0.9',
			$this->allowed,
			'de'
		);

		$this->assertSame('pt', $decision['language']);
		$this->assertNull($decision['persistExplicit']);
	}

	public function testSwitchUrlsPreserveOtherQueryParams(): void
	{
		$urls = LoginLanguageResolver::switchUrls(
			['en' => 'English', 'de' => 'Deutsch'],
			['page' => 'register', 'referralID' => '1', 'lang' => 'en']
		);

		$this->assertSame('index.php?page=register&referralID=1&lang=en', $urls['en']);
		$this->assertSame('index.php?page=register&referralID=1&lang=de', $urls['de']);
	}
}
