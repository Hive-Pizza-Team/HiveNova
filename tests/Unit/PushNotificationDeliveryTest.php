<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\PushNotificationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/PushSubscriptionDatabaseStub.php';

if (!defined('TIMESTAMP')) {
	define('TIMESTAMP', 1_700_000_000);
}

class PushNotificationDeliveryTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		PushNotificationService::setConfiguredOverride(true);
	}

	protected function tearDown(): void
	{
		PushNotificationService::setWebPushFactory(null);
		PushNotificationService::setErrorLogger(null);
		PushNotificationService::setConfiguredOverride(null);
		PushNotificationService::setVapidOkOverride(null);
		parent::tearDown();
	}

	/**
	 * @param callable(PushSubscriptionDatabaseStub): void $callback
	 */
	private function withDatabaseStub(callable $callback): void
	{
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$previous = $prop->getValue();

		$stub = new PushSubscriptionDatabaseStub();
		Database::setInstance($stub);

		try {
			$callback($stub);
		} finally {
			if ($previous instanceof DatabaseInterface) {
				Database::setInstance($previous);
			} else {
				$prop->setValue(null);
			}
		}
	}

	public function testNotifyUserLogsFlushFailureAndDoesNotCountItDelivered(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/secret'] = [
				'user_id'  => 9,
				'endpoint' => 'https://fcm.googleapis.com/fcm/send/secret',
				'p256dh'   => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'     => 'tBHItJI5svbpez7KI4CCXg',
			];
			$logs = [];
			PushNotificationService::setErrorLogger(static function (string $line) use (&$logs): void {
				$logs[] = $line;
			});
			PushNotificationService::setWebPushFactory(static function () {
				return [new class {
					public function isSuccess(): bool
					{
						return false;
					}

					public function isSubscriptionExpired(): bool
					{
						return false;
					}

					public function getEndpoint(): string
					{
						return 'https://fcm.googleapis.com/fcm/send/secret';
					}

					public function getReason(): string
					{
						return '403 Forbidden';
					}
				}];
			});

			$result = PushNotificationService::notifyUser(9, 'Title', 'Body', ['type' => 'push_test']);
			$this->assertFalse($result['ok']);
			$this->assertSame(1, $result['attempted']);
			$this->assertSame(0, $result['delivered']);
			$this->assertSame(1, $result['failed']);
			$this->assertSame('fcm.googleapis.com', $result['failures'][0]['host'] ?? null);
			$this->assertFalse($result['failures'][0]['expired'] ?? true);
			$this->assertStringContainsString('host=fcm.googleapis.com', (string) $result['lastError']);
			$this->assertNotEmpty($logs);
			$this->assertStringContainsString('host=fcm.googleapis.com', $logs[0]);
			$this->assertStringNotContainsString('/fcm/send/secret', $logs[0]);
		});
	}

	public function testNotifyUserRemovesExpiredSubscription(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$endpoint = 'https://fcm.googleapis.com/fcm/send/expired';
			$stub->subscriptionsByEndpoint[$endpoint] = [
				'user_id'  => 3,
				'endpoint' => $endpoint,
				'p256dh'   => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'     => 'tBHItJI5svbpez7KI4CCXg',
			];
			PushNotificationService::setErrorLogger(static function (): void {});
			PushNotificationService::setWebPushFactory(static function () use ($endpoint) {
				return [new class($endpoint) {
					public function __construct(private string $endpoint)
					{
					}

					public function isSuccess(): bool
					{
						return false;
					}

					public function isSubscriptionExpired(): bool
					{
						return true;
					}

					public function getEndpoint(): string
					{
						return $this->endpoint;
					}

					public function getReason(): string
					{
						return '410 Gone';
					}
				}];
			});

			$result = PushNotificationService::notifyUser(3, 'Title', 'Body');
			$this->assertFalse($result['ok']);
			$this->assertArrayNotHasKey($endpoint, $stub->subscriptionsByEndpoint);
		});
	}

	public function testNotifyUserCountsSuccessfulFlush(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/ok'] = [
				'user_id'  => 4,
				'endpoint' => 'https://fcm.googleapis.com/fcm/send/ok',
				'p256dh'   => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'     => 'tBHItJI5svbpez7KI4CCXg',
			];
			PushNotificationService::setWebPushFactory(static function () {
				return [new class {
					public function isSuccess(): bool
					{
						return true;
					}

					public function isSubscriptionExpired(): bool
					{
						return false;
					}

					public function getEndpoint(): string
					{
						return 'https://fcm.googleapis.com/fcm/send/ok';
					}
				}];
			});

			$result = PushNotificationService::sendTestNotification(4);
			$this->assertTrue($result['ok']);
			$this->assertSame(1, $result['delivered']);
			$this->assertNull($result['skipped']);
		});
	}

	public function testNotifyUserSkipsWhenUserHasNoSubscription(): void
	{
		$this->withDatabaseStub(function (): void {
			$result = PushNotificationService::notifyUser(8, 'Title', 'Body');
			$this->assertFalse($result['ok']);
			$this->assertSame(PushNotificationService::SKIP_NO_SUBSCRIPTION, $result['skipped']);
		});
	}

	public function testNotifyUserSkipsWhenPreferenceDisabled(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$stub->settingsPushByUser[5] = 0;
			$result = PushNotificationService::notifyUser(5, 'Title', 'Body');
			$this->assertFalse($result['ok']);
			$this->assertSame(PushNotificationService::SKIP_DISABLED, $result['skipped']);
		});
	}

	public function testNotifyUserLogsFactoryExceptions(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/x'] = [
				'user_id'  => 2,
				'endpoint' => 'https://fcm.googleapis.com/fcm/send/x',
				'p256dh'   => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'     => 'tBHItJI5svbpez7KI4CCXg',
			];
			$logs = [];
			PushNotificationService::setErrorLogger(static function (string $line) use (&$logs): void {
				$logs[] = $line;
			});
			PushNotificationService::setWebPushFactory(static function (): void {
				throw new RuntimeException('vapid invalid');
			});

			$result = PushNotificationService::notifyUser(2, 'Title', 'Body');
			$this->assertFalse($result['ok']);
			$this->assertSame(PushNotificationService::SKIP_EXCEPTION, $result['skipped']);
			$this->assertStringContainsString('vapid invalid', $logs[0] ?? '');
		});
	}
}
