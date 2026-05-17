<?php

namespace HiveNova\Core;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push notifications for mobile players (attacks, fleet events).
 * Configure VAPID keys in includes/constants.php or site config.
 */
class PushNotificationService
{
	public static function isConfigured(): bool
	{
		return defined('PUSH_VAPID_PUBLIC') && PUSH_VAPID_PUBLIC !== ''
			&& defined('PUSH_VAPID_PRIVATE') && PUSH_VAPID_PRIVATE !== '';
	}

	public static function getPublicKey(): string
	{
		return defined('PUSH_VAPID_PUBLIC') ? PUSH_VAPID_PUBLIC : '';
	}

	public static function saveSubscription(int $userId, array $subscription, ?string $userAgent = null): void
	{
		$db = Database::get();
		$existing = $db->selectSingle(
			'SELECT id FROM %%PUSH_SUBSCRIPTIONS%% WHERE endpoint = :endpoint',
			[':endpoint' => $subscription['endpoint']],
			'id'
		);

		if ($existing) {
			$db->update(
				'UPDATE %%PUSH_SUBSCRIPTIONS%% SET user_id = :userId, p256dh = :p256dh, auth = :auth, user_agent = :userAgent, created_at = :createdAt WHERE endpoint = :endpoint',
				[
					':userId'    => $userId,
					':p256dh'    => $subscription['keys']['p256dh'],
					':auth'      => $subscription['keys']['auth'],
					':userAgent' => $userAgent,
					':createdAt' => TIMESTAMP,
					':endpoint'  => $subscription['endpoint'],
				]
			);
			return;
		}

		$db->insert(
			'INSERT INTO %%PUSH_SUBSCRIPTIONS%% (user_id, endpoint, p256dh, auth, user_agent, created_at)
			VALUES (:userId, :endpoint, :p256dh, :auth, :userAgent, :createdAt)',
			[
				':userId'    => $userId,
				':endpoint'  => $subscription['endpoint'],
				':p256dh'    => $subscription['keys']['p256dh'],
				':auth'      => $subscription['keys']['auth'],
				':userAgent' => $userAgent,
				':createdAt' => TIMESTAMP,
			]
		);
	}

	public static function removeSubscription(string $endpoint): void
	{
		$db = Database::get();
		$db->delete('DELETE FROM %%PUSH_SUBSCRIPTIONS%% WHERE endpoint = :endpoint', [
			':endpoint' => $endpoint,
		]);
	}

	public static function notifyUser(int $userId, string $title, string $body, array $data = []): void
	{
		if (!self::isConfigured() || !class_exists(WebPush::class)) {
			return;
		}

		$db = Database::get();
		$rows = $db->select(
			'SELECT endpoint, p256dh, auth FROM %%PUSH_SUBSCRIPTIONS%% WHERE user_id = :userId',
			[':userId' => $userId]
		);

		if (empty($rows)) {
			return;
		}

		$auth = [
			'VAPID' => [
				'subject'    => defined('PUSH_VAPID_SUBJECT') ? PUSH_VAPID_SUBJECT : 'mailto:support@hive.pizza',
				'publicKey'  => PUSH_VAPID_PUBLIC,
				'privateKey' => PUSH_VAPID_PRIVATE,
			],
		];

		$webPush = new WebPush($auth);
		$payload = json_encode([
			'title' => $title,
			'body'  => $body,
			'data'  => $data,
			'url'   => $data['url'] ?? 'game.php?page=overview',
		]);

		foreach ($rows as $row) {
			$sub = Subscription::create([
				'endpoint' => $row['endpoint'],
				'keys'     => [
					'p256dh' => $row['p256dh'],
					'auth'   => $row['auth'],
				],
			]);
			$webPush->queueNotification($sub, $payload);
		}

		foreach ($webPush->flush() as $report) {
			if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
				self::removeSubscription($report->getEndpoint());
			}
		}
	}

	public static function notifyIncomingHostileFleet(int $targetUserId, int $mission, int $galaxy, int $system, int $planet): void
	{
		if ($targetUserId <= 0) {
			return;
		}

		global $LNG;
		$missionName = $LNG['type_mission_' . $mission] ?? ('Mission ' . $mission);
		$title = $LNG['push_hostile_title'] ?? 'Incoming fleet';
		$body  = sprintf(
			$LNG['push_hostile_body'] ?? '%s heading to [%d:%d:%d]',
			$missionName,
			$galaxy,
			$system,
			$planet
		);

		self::notifyUser($targetUserId, $title, $body, [
			'url' => 'game.php?page=fleetTable',
			'type' => 'hostile_fleet',
		]);
	}
}
