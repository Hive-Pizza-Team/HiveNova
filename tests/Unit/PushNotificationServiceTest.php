<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\PushNotificationService;
use PHPUnit\Framework\TestCase;

/**
 * In-memory DatabaseInterface stub for push subscription queries only.
 */
class PushNotificationFakeDatabase implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $subscriptions = [];

	/** @var array<int, int|null> */
	public array $userPushSettings = [];

	public int $insertCount = 0;

	public int $updateCount = 0;

	public int $deleteCount = 0;

	public function select($qry, array $params = [])
	{
		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && str_contains($qry, 'WHERE user_id = :userId')) {
			$userId = (int) $params[':userId'];

			return array_values(array_map(
				static fn (array $row): array => [
					'endpoint' => $row['endpoint'],
					'p256dh'   => $row['p256dh'],
					'auth'     => $row['auth'],
				],
				array_filter(
					$this->subscriptions,
					static fn (array $row): bool => (int) $row['user_id'] === $userId
				)
			));
		}

		return [];
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && str_contains($qry, 'endpoint = :endpoint')) {
			foreach ($this->subscriptions as $row) {
				if ($row['endpoint'] === $params[':endpoint']) {
					if ($field === false) {
						return $row;
					}

					return $row[$field] ?? false;
				}
			}

			return false;
		}

		if (str_contains($qry, 'settings_push') && str_contains($qry, '%%USERS%%')) {
			$userId = (int) $params[':userId'];

			return $this->userPushSettings[$userId] ?? null;
		}

		return false;
	}

	public function insert($qry, array $params = [])
	{
		$this->insertCount++;
		$this->subscriptions[] = [
			'user_id'    => (int) $params[':userId'],
			'endpoint'   => $params[':endpoint'],
			'p256dh'     => $params[':p256dh'],
			'auth'       => $params[':auth'],
			'user_agent' => $params[':userAgent'] ?? null,
			'created_at' => (int) $params[':createdAt'],
		];
	}

	public function update($qry, array $params = [])
	{
		$this->updateCount++;

		if (str_contains($qry, 'settings_push')) {
			$this->userPushSettings[(int) $params[':userId']] = (int) $params[':enabled'];

			return;
		}

		foreach ($this->subscriptions as &$row) {
			if ($row['endpoint'] === $params[':endpoint'] && (int) $row['user_id'] === (int) $params[':userId']) {
				$row['p256dh']     = $params[':p256dh'];
				$row['auth']       = $params[':auth'];
				$row['user_agent'] = $params[':userAgent'] ?? null;
				$row['created_at'] = (int) $params[':createdAt'];
			}
		}
		unset($row);
	}

	public function delete($qry, array $params = [])
	{
		$this->deleteCount++;

		$this->subscriptions = array_values(array_filter(
			$this->subscriptions,
			static function (array $row) use ($qry, $params): bool {
				if (isset($params[':userId'], $params[':endpoint'])
					&& str_contains($qry, 'endpoint = :endpoint')
					&& str_contains($qry, 'user_id = :userId')) {
					return !((int) $row['user_id'] === (int) $params[':userId']
						&& $row['endpoint'] === $params[':endpoint']);
				}

				if (isset($params[':endpoint']) && str_contains($qry, 'endpoint = :endpoint')) {
					return $row['endpoint'] !== $params[':endpoint'];
				}

				if (isset($params[':userId']) && str_contains($qry, 'user_id = :userId')) {
					return (int) $row['user_id'] !== (int) $params[':userId'];
				}

				return true;
			}
		));
	}

	public function replace($qry, array $params = []) {}

	public function query($qry) {}

	public function nativeQuery($qry) {}

	public function lastInsertId()
	{
		return 0;
	}

	public function rowCount()
	{
		return 0;
	}

	public function getQueryCounter()
	{
		return 0;
	}

	public function quote($str)
	{
		return "'" . addslashes((string) $str) . "'";
	}

	public function disconnect() {}

	public function beginTransaction(): void {}

	public function commit(): void {}

	public function rollback(): void {}
}

class PushNotificationServiceTest extends TestCase
{
	private ?DatabaseInterface $savedDatabaseInstance = null;

	private ?string $pushConfigBackup = null;

	private bool $pushConfigExisted = false;

	protected function setUp(): void
	{
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$this->savedDatabaseInstance = $prop->getValue();
		$prop->setValue(null, null);

		$this->swapDatabase(new PushNotificationFakeDatabase());
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, $this->savedDatabaseInstance);
		$this->savedDatabaseInstance = null;

		$this->restorePushConfigFile();

		parent::tearDown();
	}

	private function swapDatabase(PushNotificationFakeDatabase $fake): PushNotificationFakeDatabase
	{
		Database::setInstance($fake);

		return $fake;
	}

	/** @return array{endpoint: string, keys: array{p256dh: string, auth: string}} */
	private function validSubscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/example'): array
	{
		return [
			'endpoint' => $endpoint,
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		];
	}

	private function backupPushConfigFile(): void
	{
		$path = PushNotificationService::configFilePath();
		$this->pushConfigExisted = is_file($path);
		$this->pushConfigBackup  = $this->pushConfigExisted ? (string) file_get_contents($path) : null;
	}

	private function restorePushConfigFile(): void
	{
		$path = PushNotificationService::configFilePath();
		if ($this->pushConfigBackup !== null) {
			file_put_contents($path, $this->pushConfigBackup);
		} elseif ($this->pushConfigExisted === false && is_file($path)) {
			unlink($path);
		}
		$this->pushConfigBackup  = null;
		$this->pushConfigExisted = false;
	}

	public function testIsConfiguredReturnsFalseWhenKeysAreEmpty(): void
	{
		$this->assertFalse(PushNotificationService::isConfigured());
	}

	public function testGetPublicKeyReturnsEmptyWhenUnconfigured(): void
	{
		$this->assertSame('', PushNotificationService::getPublicKey());
	}

	public function testIsValidSubscriptionAcceptsWellFormedPayload(): void
	{
		$this->assertTrue(PushNotificationService::isValidSubscription($this->validSubscription()));
	}

	public function testIsValidSubscriptionRejectsHttpEndpoint(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'http://fcm.googleapis.com/fcm/send/example',
			'keys'     => $this->validSubscription()['keys'],
		]));
	}

	public function testIsValidSubscriptionRejectsJavascriptUrl(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'javascript:alert(1)',
			'keys'     => $this->validSubscription()['keys'],
		]));
	}

	public function testIsValidSubscriptionRejectsOversizedEndpoint(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://example.com/' . str_repeat('a', 512),
			'keys'     => $this->validSubscription()['keys'],
		]));
	}

	public function testIsValidSubscriptionRejectsInvalidKeyCharacters(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
			'keys'     => [
				'p256dh' => 'not valid base64url!',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		]));
	}

	public function testIsValidSubscriptionRejectsMissingEndpoint(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'keys' => $this->validSubscription()['keys'],
		]));
	}

	public function testIsValidSubscriptionRejectsEmptyKeys(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
			'keys'     => [],
		]));
	}

	public function testIsValidSubscriptionRejectsOversizedAuthKey(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => str_repeat('a', 256),
			],
		]));
	}

	public function testSaveSubscriptionRejectsInvalidPayload(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());

		$this->assertFalse(PushNotificationService::saveSubscription(1, ['endpoint' => 'not-a-url']));
		$this->assertSame(0, $fake->insertCount);
	}

	public function testSaveSubscriptionInsertsNewRow(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$sub  = $this->validSubscription('https://push.example.com/sub/1');

		$this->assertTrue(PushNotificationService::saveSubscription(42, $sub, 'TestAgent/1.0'));
		$this->assertSame(1, $fake->insertCount);
		$this->assertCount(1, $fake->subscriptions);
		$this->assertSame(42, $fake->subscriptions[0]['user_id']);
		$this->assertSame('TestAgent/1.0', $fake->subscriptions[0]['user_agent']);
	}

	public function testSaveSubscriptionUpdatesExistingRowForSameUser(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$sub  = $this->validSubscription('https://push.example.com/sub/shared');
		$fake->subscriptions[] = [
			'user_id'    => 7,
			'endpoint'   => $sub['endpoint'],
			'p256dh'     => 'old-key',
			'auth'       => 'old-auth',
			'user_agent' => null,
			'created_at' => 1,
		];

		$this->assertTrue(PushNotificationService::saveSubscription(7, $sub, 'UpdatedAgent'));
		$this->assertSame(0, $fake->insertCount);
		$this->assertSame(1, $fake->updateCount);
		$this->assertSame($sub['keys']['p256dh'], $fake->subscriptions[0]['p256dh']);
		$this->assertSame('UpdatedAgent', $fake->subscriptions[0]['user_agent']);
	}

	public function testSaveSubscriptionRejectsEndpointOwnedByAnotherUser(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$sub  = $this->validSubscription('https://push.example.com/sub/taken');
		$fake->subscriptions[] = [
			'user_id'    => 99,
			'endpoint'   => $sub['endpoint'],
			'p256dh'     => 'existing',
			'auth'       => 'existing',
			'user_agent' => null,
			'created_at' => 1,
		];

		$this->assertFalse(PushNotificationService::saveSubscription(5, $sub));
		$this->assertSame(0, $fake->insertCount);
		$this->assertSame(0, $fake->updateCount);
	}

	public function testSaveSubscriptionTruncatesLongUserAgent(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$sub  = $this->validSubscription('https://push.example.com/sub/ua');

		$this->assertTrue(PushNotificationService::saveSubscription(1, $sub, str_repeat('x', 300)));
		$this->assertSame(255, strlen((string) $fake->subscriptions[0]['user_agent']));
	}

	public function testRemoveSubscriptionForUserSkipsEmptyEndpoint(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->subscriptions[] = [
			'user_id'    => 1,
			'endpoint'   => 'https://push.example.com/sub/keep',
			'p256dh'     => 'k',
			'auth'       => 'a',
			'user_agent' => null,
			'created_at' => 1,
		];

		PushNotificationService::removeSubscriptionForUser(1, '');

		$this->assertCount(1, $fake->subscriptions);
		$this->assertSame(0, $fake->deleteCount);
	}

	public function testRemoveSubscriptionForUserDeletesMatchingRow(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$endpoint = 'https://push.example.com/sub/remove-me';
		$fake->subscriptions[] = [
			'user_id'    => 3,
			'endpoint'   => $endpoint,
			'p256dh'     => 'k',
			'auth'       => 'a',
			'user_agent' => null,
			'created_at' => 1,
		];

		PushNotificationService::removeSubscriptionForUser(3, $endpoint);

		$this->assertCount(0, $fake->subscriptions);
		$this->assertSame(1, $fake->deleteCount);
	}

	public function testRemoveSubscriptionDeletesByEndpoint(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$endpoint = 'https://push.example.com/sub/expired';
		$fake->subscriptions[] = [
			'user_id'    => 8,
			'endpoint'   => $endpoint,
			'p256dh'     => 'k',
			'auth'       => 'a',
			'user_agent' => null,
			'created_at' => 1,
		];

		PushNotificationService::removeSubscription($endpoint);

		$this->assertCount(0, $fake->subscriptions);
	}

	public function testRemoveSubscriptionsForUserDeletesAllRows(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->subscriptions = [
			['user_id' => 4, 'endpoint' => 'https://push.example.com/a', 'p256dh' => 'k', 'auth' => 'a', 'user_agent' => null, 'created_at' => 1],
			['user_id' => 4, 'endpoint' => 'https://push.example.com/b', 'p256dh' => 'k', 'auth' => 'a', 'user_agent' => null, 'created_at' => 1],
			['user_id' => 5, 'endpoint' => 'https://push.example.com/c', 'p256dh' => 'k', 'auth' => 'a', 'user_agent' => null, 'created_at' => 1],
		];

		PushNotificationService::removeSubscriptionsForUser(4);

		$this->assertCount(1, $fake->subscriptions);
		$this->assertSame(5, $fake->subscriptions[0]['user_id']);
	}

	public function testIsEnabledForUserDefaultsToTrueWhenUnset(): void
	{
		$this->assertTrue(PushNotificationService::isEnabledForUser(100));
	}

	public function testIsEnabledForUserReturnsFalseWhenDisabled(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->userPushSettings[100] = 0;

		$this->assertFalse(PushNotificationService::isEnabledForUser(100));
	}

	public function testSetUserPreferenceUpdatesSettingAndClearsSubscriptionsWhenDisabled(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->subscriptions[] = [
			'user_id'    => 12,
			'endpoint'   => 'https://push.example.com/sub/clear',
			'p256dh'     => 'k',
			'auth'       => 'a',
			'user_agent' => null,
			'created_at' => 1,
		];

		PushNotificationService::setUserPreference(12, false);

		$this->assertSame(0, $fake->userPushSettings[12]);
		$this->assertCount(0, $fake->subscriptions);
	}

	public function testSetUserPreferenceKeepsSubscriptionsWhenEnabled(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->userPushSettings[12] = 0;
		$fake->subscriptions[] = [
			'user_id'    => 12,
			'endpoint'   => 'https://push.example.com/sub/keep',
			'p256dh'     => 'k',
			'auth'       => 'a',
			'user_agent' => null,
			'created_at' => 1,
		];

		PushNotificationService::setUserPreference(12, true);

		$this->assertSame(1, $fake->userPushSettings[12]);
		$this->assertCount(1, $fake->subscriptions);
	}

	public function testNotifyUserReturnsEarlyWhenNotConfigured(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->subscriptions[] = [
			'user_id'    => 1,
			'endpoint'   => 'https://push.example.com/sub/notify',
			'p256dh'     => 'k',
			'auth'       => 'a',
			'user_agent' => null,
			'created_at' => 1,
		];

		PushNotificationService::notifyUser(1, 'Title', 'Body');

		$this->assertCount(1, $fake->subscriptions);
	}

	public function testNotifyUserReturnsEarlyWhenUserDisabled(): void
	{
		$fake = $this->swapDatabase(new PushNotificationFakeDatabase());
		$fake->userPushSettings[2] = 0;

		PushNotificationService::notifyUser(2, 'Title', 'Body');

		$this->assertSame(0, $fake->deleteCount);
	}

	public function testNotifyUserReturnsEarlyWhenNoSubscriptions(): void
	{
		PushNotificationService::notifyUser(3, 'Title', 'Body');

		$this->assertTrue(true);
	}

	public function testNotifyIncomingHostileFleetSkipsInvalidTarget(): void
	{
		PushNotificationService::notifyIncomingHostileFleet(0, 1, 1, 2, 3);

		$this->assertTrue(true);
	}

	public function testNotifyIncomingHostileFleetUsesLanguageStrings(): void
	{
		global $LNG;
		$LNG = [
			'type_mission_1'   => 'Attack',
			'push_hostile_title' => 'Fleet alert',
			'push_hostile_body'  => '%s at [%d:%d:%d]',
		];

		PushNotificationService::notifyIncomingHostileFleet(10, 1, 4, 5, 6);

		$this->assertTrue(true);
	}

	public function testConfigFilePathPointsToIncludesPushConfig(): void
	{
		$this->assertSame(ROOT_PATH . 'includes/push.config.php', PushNotificationService::configFilePath());
	}

	public function testWriteConfigFileCreatesEscapingSafePhpFile(): void
	{
		$this->backupPushConfigFile();
		if (is_file(PushNotificationService::configFilePath())) {
			unlink(PushNotificationService::configFilePath());
		}

		$public  = "pub'key\\test";
		$private = "priv'key\\test";
		$subject = "mailto:o'neill@example.com";

		$this->assertTrue(PushNotificationService::writeConfigFile($public, $private, $subject));

		$content = (string) file_get_contents(PushNotificationService::configFilePath());
		$this->assertStringContainsString("define('PUSH_VAPID_PUBLIC', 'pub\\'key\\\\test');", $content);
		$this->assertStringContainsString("define('PUSH_VAPID_PRIVATE', 'priv\\'key\\\\test');", $content);
		$this->assertStringContainsString("define('PUSH_VAPID_SUBJECT', 'mailto:o\\'neill@example.com');", $content);
	}

	public function testWriteConfigFileReturnsFalseWhenTargetNotWritable(): void
	{
		$this->backupPushConfigFile();
		$path = PushNotificationService::configFilePath();
		if (!is_file($path)) {
			touch($path);
		}
		chmod($path, 0444);

		try {
			$this->assertFalse(PushNotificationService::writeConfigFile('pub', 'priv', 'mailto:test@example.com'));
		} finally {
			chmod($path, 0644);
		}
	}

	public function testGenerateAndWriteConfigFileReturnsFalseWithoutVapidLibrary(): void
	{
		if (class_exists(\Minishlink\WebPush\VAPID::class)) {
			$this->markTestSkipped('VAPID library available; false-return path not exercised in this environment.');
		}

		$this->assertFalse(PushNotificationService::generateAndWriteConfigFile('mailto:admin@test.example'));
	}

	public function testGenerateAndWriteConfigFileWritesNewKeysWhenVapidAvailable(): void
	{
		if (!class_exists(\Minishlink\WebPush\VAPID::class)) {
			$this->markTestSkipped('minishlink/web-push is not installed.');
		}

		$this->backupPushConfigFile();
		if (is_file(PushNotificationService::configFilePath())) {
			unlink(PushNotificationService::configFilePath());
		}

		$this->assertTrue(PushNotificationService::generateAndWriteConfigFile('mailto:admin@test.example'));

		$content = (string) file_get_contents(PushNotificationService::configFilePath());
		$this->assertStringContainsString("define('PUSH_VAPID_PUBLIC', '", $content);
		$this->assertStringContainsString("define('PUSH_VAPID_PRIVATE', '", $content);
		$this->assertStringContainsString("define('PUSH_VAPID_SUBJECT', 'mailto:admin@test.example');", $content);
	}

	public function testUpdateConfigSubjectReturnsFalseWhenNotConfigured(): void
	{
		$this->assertFalse(PushNotificationService::updateConfigSubject('mailto:new@example.com'));
	}

	/** Runs last (zzz prefix): defines PUSH_VAPID_* constants for the remainder of the process. */
	public function testZzzIsConfiguredReturnsTrueWithNonEmptyKeys(): void
	{
		$this->backupPushConfigFile();
		$path = PushNotificationService::configFilePath();
		if (is_file($path)) {
			unlink($path);
		}

		$this->assertTrue(PushNotificationService::writeConfigFile('test-public-key', 'test-private-key', 'mailto:test@example.com'));

		require $path;

		$this->assertTrue(PushNotificationService::isConfigured());
		$this->assertSame('test-public-key', PushNotificationService::getPublicKey());
	}

	/** Runs last (zzz prefix): requires configured constants from prior test or fresh write. */
	public function testZzzUpdateConfigSubjectRewritesExistingKeys(): void
	{
		if (!PushNotificationService::isConfigured()) {
			$this->backupPushConfigFile();
			$path = PushNotificationService::configFilePath();
			if (is_file($path)) {
				unlink($path);
			}
			$this->assertTrue(PushNotificationService::writeConfigFile('keep-public', 'keep-private', 'mailto:old@example.com'));
			require $path;
		}

		$this->assertTrue(PushNotificationService::updateConfigSubject('mailto:new@example.com'));

		$content = (string) file_get_contents(PushNotificationService::configFilePath());
		$this->assertStringContainsString("define('PUSH_VAPID_PUBLIC', '", $content);
		$this->assertStringContainsString("define('PUSH_VAPID_PRIVATE', '", $content);
		$this->assertStringContainsString("define('PUSH_VAPID_SUBJECT', 'mailto:new@example.com');", $content);
	}
}
