<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Web Push for social inbox events: player PMs, alliance mail, and
 * player-originated system notices (buddy requests / support replies).
 *
 * Combat, spy, transport, and expedition mail stay on their own channels
 * so AFK construction/fleet digests are not drowned out.
 */
class SocialMessagePushService
{
	public const NOTIFY_URL = 'game.php?page=messages';

	/** Player private messages */
	public const TYPE_PLAYER = 1;

	/** Alliance circulars / diplomacy */
	public const TYPE_ALLIANCE = 2;

	/** Buddy requests and similar player-originated system notices */
	public const TYPE_SYSTEM_SOCIAL = 4;

	/**
	 * @param callable(int, string, string, array<string, mixed>): (bool|void)|null $notifier
	 */
	public function __construct(
		private readonly mixed $notifier = null,
		private readonly ?bool $configured = null,
	) {
	}

	public function isConfigured(): bool
	{
		return $this->configured ?? PushNotificationService::isConfigured();
	}

	public static function shouldNotify(int $receiverId, int $senderId, int $messageType): bool
	{
		if ($receiverId <= 0 || $receiverId === $senderId) {
			return false;
		}

		return match ($messageType) {
			self::TYPE_PLAYER, self::TYPE_ALLIANCE => true,
			self::TYPE_SYSTEM_SOCIAL => $senderId > 0,
			default => false,
		};
	}

	/**
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function socialMessage(
		int $messageType,
		string $senderName,
		string $subject,
		?array $lng = null
	): array {
		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$senderName = trim($senderName) !== '' ? trim($senderName) : 'HiveNova';
		$subject = trim($subject) !== '' ? trim($subject) : (
			is_string($lng['push_social_body_fallback'] ?? null)
				? $lng['push_social_body_fallback']
				: 'You have a new message'
		);

		$title = match ($messageType) {
			self::TYPE_ALLIANCE => is_string($lng['push_social_alliance_title'] ?? null)
				? $lng['push_social_alliance_title']
				: 'Alliance message',
			self::TYPE_SYSTEM_SOCIAL => is_string($lng['push_social_buddy_title'] ?? null)
				? $lng['push_social_buddy_title']
				: 'Social notice',
			default => is_string($lng['push_social_pm_title'] ?? null)
				? $lng['push_social_pm_title']
				: 'New message',
		};

		$template = is_string($lng['push_social_body'] ?? null)
			? $lng['push_social_body']
			: '%s: %s';
		$body = sprintf($template, $senderName, $subject);

		return [
			'title' => $title,
			'body'  => $body,
			'data'  => [
				'url'  => self::NOTIFY_URL,
				'type' => 'social_message',
				'message_type' => $messageType,
			],
		];
	}

	public static function notifySocialMessage(
		int $receiverId,
		int $senderId,
		int $messageType,
		string $senderName,
		string $subject
	): bool {
		return (new self())->notify($receiverId, $senderId, $messageType, $senderName, $subject);
	}

	public function notify(
		int $receiverId,
		int $senderId,
		int $messageType,
		string $senderName,
		string $subject
	): bool {
		if (!$this->isConfigured() || !self::shouldNotify($receiverId, $senderId, $messageType)) {
			return false;
		}

		try {
			$message = self::socialMessage($messageType, $senderName, $subject);

			return $this->deliver($receiverId, $message);
		} catch (Throwable $e) {
			PushNotificationService::logFailure('SocialMessagePushService::notify: ' . $e->getMessage());

			return false;
		}
	}

	/**
	 * @param array{title: string, body: string, data: array<string, mixed>} $message
	 */
	private function deliver(int $userId, array $message): bool
	{
		if (is_callable($this->notifier)) {
			return ($this->notifier)($userId, $message['title'], $message['body'], $message['data']) !== false;
		}

		$result = PushNotificationService::notifyUser($userId, $message['title'], $message['body'], $message['data']);

		return PushNotificationService::shouldStopRetrying($result);
	}
}
