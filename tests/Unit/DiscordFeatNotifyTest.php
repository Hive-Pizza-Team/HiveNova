<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\DiscordWebhookService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class DiscordFeatNotifyTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    /** @var list<array{url: string, json: string}> */
    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        $this->posts = [];
        DiscordWebhookService::setPoster(function (string $url, string $json): int {
            $this->posts[] = ['url' => $url, 'json' => $json];
            return 204;
        });
        $this->fake->achievement->users[4] = [
            'id' => 4,
            'username' => 'Nova',
            'lang' => 'en',
            'universe' => 1,
        ];
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        DiscordWebhookService::setPoster(null);
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testNotifyFeatClaimedPostsEmbed(): void
    {
        $token = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
        Config::setInstance(new Config([
            'uni' => 1,
            'discord_feat_webhook' => 'https://discord.com/api/webhooks/123456789012345678/' . $token,
        ]), 1);

        DiscordWebhookService::notifyFeatClaimed(1, 'feat_first_ship', 4);
        $this->assertCount(1, $this->posts);
        $json = $this->posts[0]['json'];
        $this->assertStringContainsString('Feat of Strength', $json);
        $this->assertStringContainsString('First completed shipyard unit.', $json);
        $this->assertStringContainsString('First ship', $json);
        $this->assertStringContainsString('Nova', $json);
        $this->assertStringNotContainsString('feat_first_ship', $json);
    }

    public function testNotifyFeatClaimedSkipsInvalidWebhook(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'discord_feat_webhook' => 'not-a-webhook',
        ]), 1);
        DiscordWebhookService::notifyFeatClaimed(1, 'feat_first_ship', 4);
        $this->assertSame([], $this->posts);
    }

    public function testNotifyFeatClaimedFallsBackWhenUsernameMissing(): void
    {
        $token = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
        Config::setInstance(new Config([
            'uni' => 1,
            'discord_feat_webhook' => 'https://discord.com/api/webhooks/123456789012345678/' . $token,
        ]), 1);
        DiscordWebhookService::notifyFeatClaimed(1, 'feat_first_moon', 99);
        $this->assertCount(1, $this->posts);
        $json = $this->posts[0]['json'];
        $this->assertStringContainsString('#99', $json);
        $this->assertStringContainsString('First moon', $json);
        $this->assertStringContainsString('First player to own a moon.', $json);
        $this->assertStringNotContainsString('feat_first_moon', $json);
    }

    public function testNotifySeasonReminderPostsContent(): void
    {
        $token = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
        Config::setInstance(new Config([
            'uni' => 1,
            'game_name' => 'Moon',
            'discord_feat_webhook' => 'https://discord.com/api/webhooks/123456789012345678/' . $token,
        ]), 1);

        DiscordWebhookService::notifySeasonReminder(1, 'Season 3 countdown: 2 day(s) until close.');
        $this->assertCount(1, $this->posts);
        $payload = json_decode($this->posts[0]['json'], true);
        $this->assertSame('Season 3 countdown: 2 day(s) until close.', $payload['content']);
        $this->assertSame('Moon', $payload['username']);
    }

    public function testNotifySeasonReminderSkipsWhenWebhookMissing(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'discord_feat_webhook' => '',
        ]), 1);
        DiscordWebhookService::notifySeasonReminder(1, 'Season 3 is closing.');
        $this->assertSame([], $this->posts);
    }

    public function testNotifySeasonReminderSkipsBlankText(): void
    {
        $token = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
        Config::setInstance(new Config([
            'uni' => 1,
            'discord_feat_webhook' => 'https://discord.com/api/webhooks/123456789012345678/' . $token,
        ]), 1);
        DiscordWebhookService::notifySeasonReminder(1, '   ');
        $this->assertSame([], $this->posts);
    }
}
