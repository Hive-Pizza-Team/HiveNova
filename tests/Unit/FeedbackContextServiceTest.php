<?php

declare(strict_types=1);

use HiveNova\Core\FeedbackContextService;
use PHPUnit\Framework\TestCase;

class FeedbackContextServiceTest extends TestCase
{
	public function testSanitizePageUrlStripsSensitiveQueryKeys(): void
	{
		$url = 'game.php?page=settings&token=abc&Password=secret&mode=view&uni=1';
		$clean = FeedbackContextService::sanitizePageUrl($url);

		$this->assertStringContainsString('page=settings', $clean);
		$this->assertStringContainsString('mode=view', $clean);
		$this->assertStringContainsString('uni=1', $clean);
		$this->assertStringNotContainsString('token=', $clean);
		$this->assertStringNotContainsString('Password=', $clean);
		$this->assertStringNotContainsString('secret', $clean);
	}

	public function testSanitizePageUrlKeepsHostAndDropsFragmentNoise(): void
	{
		$clean = FeedbackContextService::sanitizePageUrl(
			'https://moon.hive.pizza/game.php?page=overview&sid=drop-me#section'
		);

		$this->assertSame('https://moon.hive.pizza/game.php?page=overview', $clean);
	}

	public function testFormatContextBlockIncludesCoreFields(): void
	{
		$block = FeedbackContextService::formatContextBlock([
			'pageUrl'   => 'game.php?page=fleetTable&token=x',
			'universe'  => 1,
			'userId'    => 42,
			'username'  => 'Commander',
			'planet'    => 'Home [1:2:3]',
			'userAgent' => 'TestBrowser/1.0',
			'language'  => 'en',
			'version'   => '2.0',
			'commit'    => 'abcdef1',
			'timestamp' => 1700000000,
		]);

		$this->assertStringContainsString('Page: game.php?page=fleetTable', $block);
		$this->assertStringNotContainsString('token=', $block);
		$this->assertStringContainsString('Universe: 1', $block);
		$this->assertStringContainsString('User ID: 42', $block);
		$this->assertStringContainsString('Username: Commander', $block);
		$this->assertStringContainsString('Planet: Home [1:2:3]', $block);
		$this->assertStringContainsString('Browser: TestBrowser/1.0', $block);
		$this->assertStringContainsString('Language: en', $block);
		$this->assertStringContainsString('Version: 2.0', $block);
		$this->assertStringContainsString('Commit: abcdef1', $block);
		$this->assertStringContainsString('Time (UTC): 2023-11-14T22:13:20+00:00', $block);
	}

	public function testComposeMessagePutsNotesAboveContext(): void
	{
		$message = FeedbackContextService::composeMessage([
			'universe' => 2,
			'userId'   => 9,
		], 'Something broke on fleet send.');

		$this->assertStringStartsWith("Something broke on fleet send.\n\n", $message);
		$this->assertStringContainsString(FeedbackContextService::CONTEXT_HEADING, $message);
		$this->assertStringContainsString('Universe: 2', $message);
		$this->assertStringContainsString('User ID: 9', $message);
	}

	public function testSupportComposeHrefPrefillsSubjectAndMessage(): void
	{
		$href = FeedbackContextService::supportComposeHref([
			'pageUrl'  => 'game.php?page=overview',
			'universe' => 1,
			'userId'   => 7,
			'userAgent'=> 'UA',
		], 'Bug report');

		$this->assertStringStartsWith('game.php?', $href);
		parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $query);
		$this->assertSame('ticket', $query['page']);
		$this->assertSame('create', $query['mode']);
		$this->assertSame('Bug report', $query['subject']);
		$this->assertStringContainsString('Universe: 1', $query['message']);
		$this->assertStringContainsString('User ID: 7', $query['message']);
		$this->assertStringContainsString('Browser: UA', $query['message']);
	}

	public function testGithubIssueHrefUsesNewIssueEndpoint(): void
	{
		$href = FeedbackContextService::githubIssueHref([
			'pageUrl'  => 'index.php?page=register',
			'universe' => 6,
			'userAgent'=> 'LobbyUA',
		]);

		$this->assertStringStartsWith(FeedbackContextService::GITHUB_NEW_ISSUE_URL.'?', $href);
		parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $query);
		$this->assertSame(FeedbackContextService::DEFAULT_SUBJECT, $query['title']);
		$this->assertStringContainsString('Universe: 6', $query['body']);
		$this->assertStringContainsString('Browser: LobbyUA', $query['body']);
	}

	public function testResolveEntryPointPrefersSupportWhenAvailable(): void
	{
		$support = FeedbackContextService::resolveEntryPoint(['userId' => 1], true);
		$this->assertSame('support', $support['channel']);
		$this->assertFalse($support['external']);
		$this->assertStringContainsString('page=ticket', $support['href']);

		$github = FeedbackContextService::resolveEntryPoint(['userId' => 1], false);
		$this->assertSame('github', $github['channel']);
		$this->assertTrue($github['external']);
		$this->assertStringStartsWith(FeedbackContextService::GITHUB_NEW_ISSUE_URL, $github['href']);
	}

	public function testFormatPlanetLabel(): void
	{
		$this->assertSame(
			'Homeworld [4:5:6]',
			FeedbackContextService::formatPlanetLabel([
				'name'   => 'Homeworld',
				'galaxy' => 4,
				'system' => 5,
				'planet' => 6,
			])
		);
		$this->assertSame('', FeedbackContextService::formatPlanetLabel([]));
	}
}
