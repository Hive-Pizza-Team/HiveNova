<?php

namespace HiveNova\Core;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Web Push notifications for mobile players (attacks, fleet events).
 * VAPID keys live in includes/push.config.php (generated on install or copied from push.config.sample.php).
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

	public static function isValidSubscription(array $subscription): bool
	{
		$endpoint = $subscription['endpoint'] ?? '';
		if (!is_string($endpoint) || $endpoint === '' || strlen($endpoint) > 512) {
			return false;
		}
		if (!filter_var($endpoint, FILTER_VALIDATE_URL) || stripos($endpoint, 'https://') !== 0) {
			return false;
		}

		$p256dh = $subscription['keys']['p256dh'] ?? '';
		$auth   = $subscription['keys']['auth'] ?? '';
		if (!is_string($p256dh) || !is_string($auth) || $p256dh === '' || $auth === '') {
			return false;
		}
		if (strlen($p256dh) > 255 || strlen($auth) > 255) {
			return false;
		}
		if (!preg_match('#^[A-Za-z0-9_-]+=*$#', $p256dh) || !preg_match('#^[A-Za-z0-9_-]+=*$#', $auth)) {
			return false;
		}

		return true;
	}

	public static function saveSubscription(int $userId, array $subscription, ?string $userAgent = null): bool
	{
		if (!self::isValidSubscription($subscription)) {
			return false;
		}

		$db = Database::get();
		$existingUserId = $db->selectSingle(
			'SELECT user_id FROM %%PUSH_SUBSCRIPTIONS%% WHERE endpoint = :endpoint',
			[':endpoint' => $subscription['endpoint']],
			'user_id'
		);

		if ($existingUserId !== false && $existingUserId !== null && $existingUserId !== '' && (int) $existingUserId !== $userId) {
			return false;
		}

		if ($userAgent !== null && strlen($userAgent) > 255) {
			$userAgent = substr($userAgent, 0, 255);
		}

		if ($existingUserId !== false && $existingUserId !== null && $existingUserId !== '') {
			$db->update(
				'UPDATE %%PUSH_SUBSCRIPTIONS%% SET p256dh = :p256dh, auth = :auth, user_agent = :userAgent, created_at = :createdAt WHERE endpoint = :endpoint AND user_id = :userId',
				[
					':userId'    => $userId,
					':p256dh'    => $subscription['keys']['p256dh'],
					':auth'      => $subscription['keys']['auth'],
					':userAgent' => $userAgent,
					':createdAt' => TIMESTAMP,
					':endpoint'  => $subscription['endpoint'],
				]
			);

			return true;
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

		return true;
	}

	public static function removeSubscriptionForUser(int $userId, string $endpoint): void
	{
		if ($endpoint === '') {
			return;
		}

		$db = Database::get();
		$db->delete(
			'DELETE FROM %%PUSH_SUBSCRIPTIONS%% WHERE endpoint = :endpoint AND user_id = :userId',
			[
				':endpoint' => $endpoint,
				':userId'   => $userId,
			]
		);
	}

	/** Remove by endpoint only (expired subscription cleanup from push gateway). */
	public static function removeSubscription(string $endpoint): void
	{
		$db = Database::get();
		$db->delete('DELETE FROM %%PUSH_SUBSCRIPTIONS%% WHERE endpoint = :endpoint', [
			':endpoint' => $endpoint,
		]);
	}

	public static function removeSubscriptionsForUser(int $userId): void
	{
		$db = Database::get();
		$db->delete('DELETE FROM %%PUSH_SUBSCRIPTIONS%% WHERE user_id = :userId', [
			':userId' => $userId,
		]);
	}

	public static function isEnabledForUser(int $userId): bool
	{
		$db = Database::get();
		$value = $db->selectSingle(
			'SELECT settings_push FROM %%USERS%% WHERE id = :userId',
			[':userId' => $userId],
			'settings_push'
		);

		return $value === null || (int) $value === 1;
	}

	public static function setUserPreference(int $userId, bool $enabled): void
	{
		$db = Database::get();
		$db->update(
			'UPDATE %%USERS%% SET settings_push = :enabled WHERE id = :userId',
			[
				':enabled' => $enabled ? 1 : 0,
				':userId'  => $userId,
			]
		);

		if (!$enabled) {
			self::removeSubscriptionsForUser($userId);
		}
	}

	public static function notifyUser(int $userId, string $title, string $body, array $data = []): void
	{
		if (!self::isConfigured() || !class_exists(WebPush::class) || !self::isEnabledForUser($userId)) {
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

	public static function configFilePath(): string
	{
		return ROOT_PATH . 'includes/push.config.php';
	}

	public static function generateAndWriteConfigFile(?string $subject = null): bool
	{
		if (!class_exists(VAPID::class)) {
			return false;
		}

		$keys = VAPID::createVapidKeys();

		return self::writeConfigFile($keys['publicKey'], $keys['privateKey'], $subject ?? self::defaultInstallSubject());
	}

	public static function updateConfigSubject(string $subject): bool
	{
		if (!self::isConfigured()) {
			return false;
		}

		return self::writeConfigFile(PUSH_VAPID_PUBLIC, PUSH_VAPID_PRIVATE, $subject);
	}

	public static function writeConfigFile(string $publicKey, string $privateKey, string $subject): bool
	{
		$path = self::configFilePath();
		$dir  = dirname($path);
		if (!is_dir($dir) || (!is_file($path) && !is_writable($dir)) || (is_file($path) && !is_writable($path))) {
			return false;
		}

		$content = sprintf(
			"<?php\n\n/**\n * Web Push VAPID keys — do not commit.\n */\n\ndefine('PUSH_VAPID_PUBLIC', '%s');\ndefine('PUSH_VAPID_PRIVATE', '%s');\ndefine('PUSH_VAPID_SUBJECT', '%s');\n",
			self::escapePhpSingleQuoted($publicKey),
			self::escapePhpSingleQuoted($privateKey),
			self::escapePhpSingleQuoted($subject)
		);

		if (file_put_contents($path, $content, LOCK_EX) === false) {
			return false;
		}

		@chmod($path, 0600);

		return true;
	}

	private static function defaultInstallSubject(): string
	{
		if (defined('HTTP_HOST') && HTTP_HOST !== '') {
			$scheme = (defined('HTTPS') && HTTPS) ? 'https' : 'http';

			return $scheme . '://' . HTTP_HOST;
		}

		return 'mailto:support@hive.pizza';
	}

	private static function escapePhpSingleQuoted(string $value): string
	{
		return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
	}
}
