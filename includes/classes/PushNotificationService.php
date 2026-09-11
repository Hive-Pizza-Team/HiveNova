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
	public const CONTENT_ENCODING = 'aes128gcm';
	public const TEST_COOLDOWN = 60;
	public const TEST_NOTIFY_URL = 'game.php?page=overview';
	public const SKIP_NOT_CONFIGURED = 'not_configured';
	public const SKIP_WEBPUSH_MISSING = 'webpush_missing';
	public const SKIP_DISABLED = 'disabled';
	public const SKIP_NO_SUBSCRIPTION = 'no_subscription';
	public const SKIP_EXCEPTION = 'exception';

	/** @var callable|null fn(list<array{endpoint: string, p256dh: string, auth: string}>, string): iterable */
	private static $webPushFactory = null;

	/** @var callable|null fn(string $message): void */
	private static $errorLogger = null;

	private static ?bool $configuredOverride = null;

	public static function setWebPushFactory(?callable $factory): void
	{
		self::$webPushFactory = $factory;
	}

	public static function setErrorLogger(?callable $logger): void
	{
		self::$errorLogger = $logger;
	}

	public static function setConfiguredOverride(?bool $configured): void
	{
		self::$configuredOverride = $configured;
	}

	public static function isConfigured(): bool
	{
		if (self::$configuredOverride !== null) {
			return self::$configuredOverride;
		}

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
		if (!is_string($endpoint) || $endpoint === '' || strlen($endpoint) > 2048) {
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

		if ($userAgent !== null && strlen($userAgent) > 255) {
			$userAgent = substr($userAgent, 0, 255);
		}

		if ($existingUserId !== false && $existingUserId !== null && $existingUserId !== '') {
			// One browser endpoint per origin — reassign to whoever is logged in now.
			// Same device switching accounts/universes must work; a stolen subscription
			// object could re-point alerts (tradeoff vs hard block in #163).
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

	public static function hasSubscription(int $userId): bool
	{
		if ($userId <= 0) {
			return false;
		}

		$row = Database::get()->selectSingle(
			'SELECT user_id FROM %%PUSH_SUBSCRIPTIONS%% WHERE user_id = :userId LIMIT 1',
			[':userId' => $userId],
			'user_id'
		);

		return $row !== false && $row !== null && $row !== '';
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

	/**
	 * @return array{ok: bool, attempted: int, delivered: int, failed: int, skipped: string|null}
	 */
	public static function notifyUser(int $userId, string $title, string $body, array $data = []): array
	{
		if (!self::isConfigured()) {
			return self::skipResult(self::SKIP_NOT_CONFIGURED);
		}
		if (!is_callable(self::$webPushFactory) && !class_exists(WebPush::class)) {
			self::logFailure('PushNotificationService: minishlink/web-push is not installed');

			return self::skipResult(self::SKIP_WEBPUSH_MISSING);
		}
		if (!self::isEnabledForUser($userId)) {
			return self::skipResult(self::SKIP_DISABLED);
		}

		$db = Database::get();
		$rows = $db->select(
			'SELECT endpoint, p256dh, auth FROM %%PUSH_SUBSCRIPTIONS%% WHERE user_id = :userId',
			[':userId' => $userId]
		);

		if (!is_array($rows) || $rows === []) {
			return self::skipResult(self::SKIP_NO_SUBSCRIPTION);
		}

		$payload = json_encode([
			'title' => $title,
			'body'  => $body,
			'data'  => $data,
			'url'   => $data['url'] ?? 'game.php?page=overview',
			'tag'   => is_string($data['type'] ?? null) ? $data['type'] : 'hivenova',
		]);

		$delivered = 0;
		$failed = 0;

		try {
			foreach (self::flushNotifications($rows, is_string($payload) ? $payload : '{}') as $report) {
				$success = is_object($report) && method_exists($report, 'isSuccess') && $report->isSuccess();
				$expired = is_object($report) && method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired();
				$endpoint = is_object($report) && method_exists($report, 'getEndpoint')
					? (string) $report->getEndpoint()
					: '';
				$reason = is_object($report) && method_exists($report, 'getReason')
					? (string) $report->getReason()
					: '';
				$status = self::reportStatus($report);

				if ($success) {
					$delivered++;
					continue;
				}

				$failed++;
				self::logFailure(self::formatDeliveryFailure($endpoint, $expired, $reason, $status));
				if ($expired && $endpoint !== '') {
					self::removeSubscription($endpoint);
				}
			}
		} catch (\Throwable $e) {
			self::logFailure('PushNotificationService::notifyUser: ' . $e->getMessage());

			return [
				'ok'        => false,
				'attempted' => count($rows),
				'delivered' => $delivered,
				'failed'    => max($failed, 1),
				'skipped'   => self::SKIP_EXCEPTION,
			];
		}

		return [
			'ok'        => $delivered > 0,
			'attempted' => count($rows),
			'delivered' => $delivered,
			'failed'    => $failed,
			'skipped'   => null,
		];
	}

	public static function endpointHost(string $endpoint): string
	{
		$host = parse_url($endpoint, PHP_URL_HOST);

		return is_string($host) && $host !== '' ? $host : 'unknown';
	}

	public static function formatDeliveryFailure(string $endpoint, bool $expired, string $reason, ?int $status = null): string
	{
		$reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');
		if (strlen($reason) > 180) {
			$reason = substr($reason, 0, 177) . '...';
		}
		$reason = str_replace($endpoint, self::endpointHost($endpoint), $reason);

		return sprintf(
			'PushNotificationService: flush failed host=%s status=%s expired=%s reason=%s',
			self::endpointHost($endpoint),
			$status === null ? 'n/a' : (string) $status,
			$expired ? '1' : '0',
			$reason === '' ? 'unknown' : $reason
		);
	}

	public static function testCooldownRemaining(int $lastSentAt, int $now): int
	{
		if ($lastSentAt <= 0) {
			return 0;
		}

		return max(0, self::TEST_COOLDOWN - ($now - $lastSentAt));
	}

	/**
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function testMessage(?array $lng = null): array
	{
		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$title = is_string($lng['push_test_title'] ?? null) ? $lng['push_test_title'] : 'HiveNova push test';
		$body = is_string($lng['push_test_body'] ?? null)
			? $lng['push_test_body']
			: 'If you see this, Web Push delivery works.';

		return [
			'title' => $title,
			'body'  => $body,
			'data'  => [
				'url'  => self::TEST_NOTIFY_URL,
				'type' => 'push_test',
			],
		];
	}

	/**
	 * @return array{ok: bool, attempted: int, delivered: int, failed: int, skipped: string|null}
	 */
	public static function sendTestNotification(int $userId): array
	{
		if ($userId <= 0) {
			return self::skipResult(self::SKIP_DISABLED);
		}

		$message = self::testMessage();

		return self::notifyUser($userId, $message['title'], $message['body'], $message['data']);
	}

	public static function logFailure(string $message): void
	{
		if (self::$errorLogger !== null) {
			(self::$errorLogger)($message);

			return;
		}

		error_log($message);
	}

	/**
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function expeditionResultMessage(bool $hasChoice): array
	{
		global $LNG;
		$title = $LNG['push_expedition_title'] ?? 'Expedition complete';
		$body = $hasChoice
			? ($LNG['push_expedition_choice_body'] ?? 'Your expedition returned with a choice to review.')
			: ($LNG['push_expedition_body'] ?? 'Your expedition has returned.');

		return [
			'title' => $title,
			'body' => $body,
			'data' => [
				'url' => 'game.php?page=overview#commander',
				'type' => 'expedition_result',
				'choice' => $hasChoice,
			],
		];
	}

	public static function notifyExpeditionResult(int $userId, bool $hasChoice): void
	{
		if ($userId <= 0) {
			return;
		}

		$message = self::expeditionResultMessage($hasChoice);
		self::notifyUser($userId, $message['title'], $message['body'], $message['data']);
	}

	public static function notifyDirectiveMilestone(int $userId, string $type, int $periodEnd = 0): void
	{
		if ($userId <= 0) {
			return;
		}

		global $LNG;
		if ($type === 'directive_period_ending') {
			$title = $LNG['push_directive_ending_title'] ?? 'Directive period ending';
			$body = $LNG['push_directive_ending_body'] ?? 'Your empire directive period ends soon.';
		} else {
			$title = $LNG['push_directive_complete_title'] ?? 'Directive complete';
			$body = $LNG['push_directive_complete_body'] ?? 'Your empire directive is ready to claim.';
			$type = 'directive_completable';
		}

		self::notifyUser($userId, $title, $body, [
			'url' => 'game.php?page=overview#commander',
			'type' => $type,
			'period_end' => $periodEnd,
		]);
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

	/**
	 * @return array{ok: bool, attempted: int, delivered: int, failed: int, skipped: string|null}
	 */
	private static function skipResult(string $reason): array
	{
		return [
			'ok'        => false,
			'attempted' => 0,
			'delivered' => 0,
			'failed'    => 0,
			'skipped'   => $reason,
		];
	}

	/**
	 * @param list<array{endpoint?: string, p256dh?: string, auth?: string}> $rows
	 * @return iterable<object>
	 */
	private static function flushNotifications(array $rows, string $payload): iterable
	{
		if (is_callable(self::$webPushFactory)) {
			return (self::$webPushFactory)($rows, $payload);
		}

		$auth = [
			'VAPID' => [
				'subject'    => defined('PUSH_VAPID_SUBJECT') ? PUSH_VAPID_SUBJECT : 'mailto:support@hive.pizza',
				'publicKey'  => PUSH_VAPID_PUBLIC,
				'privateKey' => PUSH_VAPID_PRIVATE,
			],
		];

		$webPush = new WebPush($auth);
		foreach ($rows as $row) {
			$sub = Subscription::create([
				'endpoint'        => (string) ($row['endpoint'] ?? ''),
				'contentEncoding' => self::CONTENT_ENCODING,
				'keys'            => [
					'p256dh' => (string) ($row['p256dh'] ?? ''),
					'auth'   => (string) ($row['auth'] ?? ''),
				],
			]);
			$webPush->queueNotification($sub, $payload);
		}

		return $webPush->flush();
	}

	private static function reportStatus(mixed $report): ?int
	{
		if (!is_object($report) || !method_exists($report, 'getResponse')) {
			return null;
		}

		$response = $report->getResponse();
		if (!is_object($response) || !method_exists($response, 'getStatusCode')) {
			return null;
		}

		$code = $response->getStatusCode();

		return is_int($code) ? $code : null;
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
