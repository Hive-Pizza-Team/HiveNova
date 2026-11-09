<?php

use HiveNova\Core\SocialMessagePushService;
use PHPUnit\Framework\TestCase;

class SocialMessagePushServiceTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		\HiveNova\Core\PushNotificationService::setErrorLogger(static function (): void {});
	}

	protected function tearDown(): void
	{
		\HiveNova\Core\PushNotificationService::setErrorLogger(null);
		\HiveNova\Core\PushNotificationService::setConfiguredOverride(null);
		parent::tearDown();
	}

	public function testShouldNotifyAcceptsPlayerAndAllianceMail(): void
	{
		$this->assertTrue(SocialMessagePushService::shouldNotify(2, 1, SocialMessagePushService::TYPE_PLAYER));
		$this->assertTrue(SocialMessagePushService::shouldNotify(2, 1, SocialMessagePushService::TYPE_ALLIANCE));
	}

	public function testShouldNotifyRequiresPlayerSenderForSystemSocial(): void
	{
		$this->assertTrue(SocialMessagePushService::shouldNotify(2, 5, SocialMessagePushService::TYPE_SYSTEM_SOCIAL));
		$this->assertFalse(SocialMessagePushService::shouldNotify(2, 0, SocialMessagePushService::TYPE_SYSTEM_SOCIAL));
	}

	public function testShouldNotifyRejectsSelfAndUnknownTypes(): void
	{
		$this->assertFalse(SocialMessagePushService::shouldNotify(0, 1, SocialMessagePushService::TYPE_PLAYER));
		$this->assertFalse(SocialMessagePushService::shouldNotify(1, 1, SocialMessagePushService::TYPE_PLAYER));
		$this->assertFalse(SocialMessagePushService::shouldNotify(2, 1, 3));
	}

	public function testSocialMessageUsesLanguageAndDeepLink(): void
	{
		$message = SocialMessagePushService::socialMessage(
			SocialMessagePushService::TYPE_PLAYER,
			'Alice',
			'Hello',
			[
				'push_social_pm_title' => 'New message',
				'push_social_body'     => '%s: %s',
			]
		);

		$this->assertSame('New message', $message['title']);
		$this->assertSame('Alice: Hello', $message['body']);
		$this->assertSame('game.php?page=messages', $message['data']['url']);
		$this->assertSame('social_message', $message['data']['type']);
		$this->assertSame(SocialMessagePushService::TYPE_PLAYER, $message['data']['message_type']);
	}

	public function testSocialMessageTitlesByType(): void
	{
		$lng = [
			'push_social_alliance_title' => 'Alliance message',
			'push_social_buddy_title'    => 'Social notice',
			'push_social_pm_title'       => 'New message',
			'push_social_body'           => '%s: %s',
		];

		$this->assertSame(
			'Alliance message',
			SocialMessagePushService::socialMessage(SocialMessagePushService::TYPE_ALLIANCE, 'A', 'S', $lng)['title']
		);
		$this->assertSame(
			'Social notice',
			SocialMessagePushService::socialMessage(SocialMessagePushService::TYPE_SYSTEM_SOCIAL, 'A', 'S', $lng)['title']
		);
	}

	public function testNotifySendsWhenConfigured(): void
	{
		$sent = [];
		$service = new SocialMessagePushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'title', 'body', 'data');
			},
			configured: true,
		);

		$this->assertTrue($service->notify(9, 3, SocialMessagePushService::TYPE_PLAYER, 'Bob', 'Hi'));
		$this->assertCount(1, $sent);
		$this->assertSame(9, $sent[0]['userId']);
		$this->assertSame('social_message', $sent[0]['data']['type']);
	}

	public function testNotifySkippedWhenNotConfiguredOrNotEligible(): void
	{
		$sent = [];
		$service = new SocialMessagePushService(
			notifier: static function () use (&$sent): void {
				$sent[] = 1;
			},
			configured: false,
		);
		$this->assertFalse($service->notify(9, 3, SocialMessagePushService::TYPE_PLAYER, 'Bob', 'Hi'));

		$service = new SocialMessagePushService(
			notifier: static function () use (&$sent): void {
				$sent[] = 1;
			},
			configured: true,
		);
		$this->assertFalse($service->notify(9, 0, SocialMessagePushService::TYPE_SYSTEM_SOCIAL, 'Sys', 'Fleet'));
		$this->assertSame([], $sent);
	}

	public function testStaticNotifyNoopsWhenNotConfigured(): void
	{
		$this->assertFalse(SocialMessagePushService::notifySocialMessage(
			1,
			2,
			SocialMessagePushService::TYPE_PLAYER,
			'A',
			'B'
		));
	}

	public function testIsConfiguredDefersToPushServiceWhenUnset(): void
	{
		$service = new SocialMessagePushService();
		$this->assertFalse($service->isConfigured());
	}
}
