<?php

use HiveNova\Core\AllianceService;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\DiscordWebhookService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DiscordWebhookDatabaseStub.php';

class DiscordWebhookServiceTest extends TestCase
{
	private const VALID_TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
	private const SNOWFLAKE = '123456789012345678';

	private DiscordWebhookDatabaseStub $db;

	/** @var list<array{url: string, json: string}> */
	private array $posts = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new DiscordWebhookDatabaseStub();
		$this->swapDatabase($this->db);
		$this->posts = [];
		DiscordWebhookService::setPoster(function (string $url, string $json): int {
			$this->posts[] = ['url' => $url, 'json' => $json];
			return 204;
		});
	}

	protected function tearDown(): void
	{
		DiscordWebhookService::setPoster(null);
		$this->restoreDatabase();
		parent::tearDown();
	}

	private function swapDatabase(DatabaseInterface $fake): void
	{
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, $fake);
	}

	private function restoreDatabase(): void
	{
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	private function validUrl(string $host = 'discord.com', string $pathPrefix = '/api/webhooks/'): string
	{
		return 'https://' . $host . $pathPrefix . self::SNOWFLAKE . '/' . self::VALID_TOKEN;
	}

	private function seedDefender(int $userId = 2, int $allyId = 10, ?string $webhook = null): void
	{
		$this->db->users[$userId] = [
			'id'       => $userId,
			'username' => 'Alice',
			'ally_id'  => $allyId,
		];
		$this->db->alliances[$allyId] = [
			'id'                    => $allyId,
			'ally_discord_webhook'  => $webhook ?? $this->validUrl(),
		];
	}

	public function testNormalizeRebuildsCanonicalDiscordUrl(): void
	{
		$normalized = DiscordWebhookService::normalizeUrl($this->validUrl());
		$this->assertSame($this->validUrl(), $normalized);
	}

	public function testNormalizeRewritesLegacyAndRegionalHosts(): void
	{
		$canonical = $this->validUrl();
		foreach (['ptb.discord.com', 'canary.discord.com', 'discordapp.com', 'ptb.discordapp.com'] as $host) {
			$this->assertSame($canonical, DiscordWebhookService::normalizeUrl($this->validUrl($host)));
		}
	}

	public function testNormalizeAcceptsApiVersionInPath(): void
	{
		$raw = $this->validUrl('discord.com', '/api/v10/webhooks/');
		$this->assertSame($this->validUrl(), DiscordWebhookService::normalizeUrl($raw));
	}

	/** @dataProvider invalidUrlProvider */
	public function testNormalizeRejectsInvalidUrls(string $url): void
	{
		$this->assertNull(DiscordWebhookService::normalizeUrl($url));
	}

	public static function invalidUrlProvider(): array
	{
		$token = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
		$id = '123456789012345678';
		return [
			'http'          => ['http://discord.com/api/webhooks/' . $id . '/' . $token],
			'localhost'     => ['https://127.0.0.1/api/webhooks/' . $id . '/' . $token],
			'example'       => ['https://example.com/api/webhooks/' . $id . '/' . $token],
			'github suffix' => ['https://discord.com/api/webhooks/' . $id . '/' . $token . '/github'],
			'slack suffix'  => ['https://discord.com/api/webhooks/' . $id . '/' . $token . '/slack'],
			'empty'         => [''],
			'with port'     => ['https://discord.com:443/api/webhooks/' . $id . '/' . $token],
			'with user'     => ['https://u:p@discord.com/api/webhooks/' . $id . '/' . $token],
			'too long'      => ['https://discord.com/api/webhooks/' . $id . '/' . $token . str_repeat('x', 500)],
		];
	}

	public function testIncomingNotifyPostsDefenderMissionAndCoordsWithoutAttacker(): void
	{
		$this->seedDefender();
		DiscordWebhookService::notifyIncomingHostile(2, 1, 1, 2, 3, 1);

		$this->assertCount(1, $this->posts);
		$this->assertSame($this->validUrl(), $this->posts[0]['url']);
		$payload = json_decode($this->posts[0]['json'], true);
		$this->assertSame([], $payload['allowed_mentions']['parse']);
		$this->assertSame('HiveNova', $payload['username']);
		$this->assertSame(
			'https://moon.hive.pizza/styles/resource/images/login/HiveNova.png',
			$payload['avatar_url']
		);
		$this->assertArrayNotHasKey('content', $payload);
		$embed = $payload['embeds'][0];
		$this->assertSame('Incoming Attack', $embed['title']);
		$this->assertStringContainsString('Incoming Attack to Alice at [1:2:3] (planet)', $embed['description']);
		$this->assertStringNotContainsString('Bob', $embed['description']);
		$this->assertSame(0xE74C3C, $embed['color']);
		$this->assertSame($payload['avatar_url'], $embed['thumbnail']['url']);
		$this->assertSame('Alice', $embed['fields'][0]['value']);
		$this->assertSame('[1:2:3] (planet)', $embed['fields'][1]['value']);
		$this->assertCount(2, $embed['fields']);
	}

	public function testIncomingNpcRaidNamesPiratesInEmbed(): void
	{
		$this->seedDefender();
		DiscordWebhookService::notifyIncomingHostile(2, 1, 1, 50, 13, 1, 0, '204,8;202,2');

		$this->assertCount(1, $this->posts);
		$embed = json_decode($this->posts[0]['json'], true)['embeds'][0];
		$this->assertSame('Incoming Attack — Pirates', $embed['title']);
		$this->assertStringContainsString('Incoming Attack (Pirates) to Alice at [1:50:13] (planet)', $embed['description']);
		$this->assertSame('Pirates', $embed['fields'][2]['value']);
	}

	public function testCombatNpcRaidNamesAliensInEmbed(): void
	{
		$this->seedDefender();
		DiscordWebhookService::notifyCombatResolved(2, 1, 1, 50, 13, 1, 0, '205,6;203,1');

		$embed = json_decode($this->posts[0]['json'], true)['embeds'][0];
		$this->assertSame('Combat resolved — Attack — Aliens', $embed['title']);
		$this->assertStringContainsString('Aliens vs Alice', $embed['description']);
		$this->assertSame('Aliens', $embed['fields'][2]['value']);
	}

	public function testBuildPayloadUsesLogoAvatarAndEmbedFields(): void
	{
		$payload = DiscordWebhookService::buildPayload(
			'Incoming Attack to Alice at [1:2:3] (planet)',
			true,
			'Alice',
			1,
			'[1:2:3] (planet)'
		);
		$this->assertSame('HiveNova', $payload['username']);
		$this->assertSame(
			'https://moon.hive.pizza/styles/resource/images/login/HiveNova.png',
			$payload['avatar_url']
		);
		$this->assertSame($payload['avatar_url'], $payload['embeds'][0]['thumbnail']['url']);
		$this->assertSame('Incoming Attack', $payload['embeds'][0]['title']);
		$this->assertSame('Alice', $payload['embeds'][0]['fields'][0]['value']);
	}

	public function testCombatFormatterDiffersFromIncoming(): void
	{
		$incoming = DiscordWebhookService::formatIncoming('Alice', 1, 1, 2, 3, 1);
		$combat   = DiscordWebhookService::formatCombat('Alice', 1, 1, 2, 3, 1);
		$this->assertNotSame($incoming, $combat);
		$this->assertStringContainsString('Incoming', $incoming);
		$this->assertStringContainsString('Combat resolved', $combat);
		$this->assertStringNotContainsString('Pirates', $incoming);
		$this->assertStringContainsString(
			'Pirates',
			DiscordWebhookService::formatIncoming('Alice', 1, 1, 2, 3, 1, 'Pirates')
		);
	}

	public function testEmptyWebhookDoesNotPost(): void
	{
		$this->seedDefender(2, 10, '');
		DiscordWebhookService::notifyIncomingHostile(2, 1, 1, 2, 3, 1);
		$this->assertSame([], $this->posts);
	}

	public function testNoAllianceDoesNotPost(): void
	{
		$this->db->users[2] = ['id' => 2, 'username' => 'Alice', 'ally_id' => 0];
		DiscordWebhookService::notifyIncomingHostile(2, 1, 1, 2, 3, 1);
		$this->assertSame([], $this->posts);
	}

	public function testPosterExceptionIsSwallowed(): void
	{
		$this->seedDefender();
		DiscordWebhookService::setPoster(static function (): int {
			throw new RuntimeException('discord down');
		});
		DiscordWebhookService::notifyIncomingHostile(2, 1, 1, 2, 3, 1);
		$this->assertTrue(true);
	}

	public function testPosterHttpErrorsAreSwallowed(): void
	{
		$this->seedDefender();
		DiscordWebhookService::setPoster(function (string $url, string $json): int {
			$this->posts[] = ['url' => $url, 'json' => $json];
			return 429;
		});
		DiscordWebhookService::notifyCombatResolved(2, 1, 1, 2, 3, 3);
		$this->assertCount(1, $this->posts);
		$payload = json_decode($this->posts[0]['json'], true);
		$this->assertStringContainsString('(moon)', $payload['embeds'][0]['description']);
		$this->assertSame('Combat resolved — Attack', $payload['embeds'][0]['title']);
		$this->assertSame(0x3498DB, $payload['embeds'][0]['color']);
	}

	public function testResolveAdminInputKeepsCurrentWhenBlank(): void
	{
		$current = $this->validUrl();
		$this->assertSame($current, DiscordWebhookService::resolveAdminInput('', false, $current));
	}

	public function testResolveAdminInputClearsWhenRequested(): void
	{
		$this->assertSame('', DiscordWebhookService::resolveAdminInput('', true, $this->validUrl()));
	}

	public function testResolveAdminInputRejectsInvalidPaste(): void
	{
		$this->assertFalse(DiscordWebhookService::resolveAdminInput('https://example.com/x', false, $this->validUrl()));
	}

	public function testSetDiscordWebhookStoresNormalizedUrl(): void
	{
		$this->db->alliances[10] = ['id' => 10, 'ally_discord_webhook' => ''];
		AllianceService::setDiscordWebhook(10, $this->validUrl('discordapp.com'));
		$this->assertSame($this->validUrl(), $this->db->alliances[10]['ally_discord_webhook']);
	}

	public function testSetDiscordWebhookRejectsInvalidWithoutChanging(): void
	{
		$this->db->alliances[10] = ['id' => 10, 'ally_discord_webhook' => $this->validUrl()];
		try {
			AllianceService::setDiscordWebhook(10, 'https://example.com/hook');
			$this->fail('expected exception');
		} catch (RuntimeException $e) {
			$this->assertSame('invalid_discord_webhook', $e->getMessage());
		}
		$this->assertSame($this->validUrl(), $this->db->alliances[10]['ally_discord_webhook']);
	}

	public function testSetDiscordWebhookClears(): void
	{
		$this->db->alliances[10] = ['id' => 10, 'ally_discord_webhook' => $this->validUrl()];
		AllianceService::setDiscordWebhook(10, '');
		$this->assertSame('', $this->db->alliances[10]['ally_discord_webhook']);
	}

	public function testNotifyIgnoresNonPositiveUserId(): void
	{
		DiscordWebhookService::notifyIncomingHostile(0, 1, 1, 2, 3, 1);
		$this->assertSame([], $this->posts);
	}

	public function testUnknownMissionAndPlanetTypeUseFallbacks(): void
	{
		$text = DiscordWebhookService::formatIncoming('Alice', 99, 1, 2, 3, 2);
		$this->assertStringContainsString('Mission 99', $text);
		$this->assertStringContainsString('(planet)', $text);
	}

	public function testNormalizeAcceptsV9Path(): void
	{
		$raw = $this->validUrl('discord.com', '/api/v9/webhooks/');
		$this->assertSame($this->validUrl(), DiscordWebhookService::normalizeUrl($raw));
	}
}
