<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\PushNotificationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/PushSubscriptionDatabaseStub.php';

if (!defined('TIMESTAMP')) {
	define('TIMESTAMP', 1_700_000_000);
}

class PushNotificationServiceTest extends TestCase
{
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

	public function testSaveSubscriptionInsertsNewEndpoint(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$this->assertTrue(PushNotificationService::saveSubscription(42, $this->validSubscription()));
			$this->assertCount(1, $stub->inserts);
			$this->assertSame([], $stub->updates);
			$this->assertSame(42, $stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/example']['user_id']);
		});
	}

	public function testSaveSubscriptionReassignsEndpointFromAnotherUser(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/shared-device';
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub) use ($endpoint): void {
			$stub->subscriptionsByEndpoint[$endpoint] = [
				'user_id'  => 7,
				'endpoint' => $endpoint,
				'p256dh'   => 'old-key',
				'auth'     => 'old-auth',
			];
			$stub->settingsPushByUser[7] = 1;
			$stub->settingsPushByUser[99] = 1;

			$this->assertTrue(PushNotificationService::saveSubscription(99, $this->validSubscription($endpoint)));
			$this->assertCount(1, array_filter($stub->updates, static fn (array $row): bool => str_contains($row['qry'], '%%PUSH_SUBSCRIPTIONS%%')));
			$this->assertSame([], $stub->inserts);
			$this->assertSame(99, $stub->subscriptionsByEndpoint[$endpoint]['user_id']);
			$this->assertSame(0, $stub->settingsPushByUser[7]);
		});
	}

	public function testSaveSubscriptionUpdatesExistingEndpointForSameUser(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/same-user';
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub) use ($endpoint): void {
			$stub->subscriptionsByEndpoint[$endpoint] = [
				'user_id'  => 5,
				'endpoint' => $endpoint,
				'p256dh'   => 'old-key',
				'auth'     => 'old-auth',
			];

			$this->assertTrue(PushNotificationService::saveSubscription(5, $this->validSubscription($endpoint)));
			$this->assertCount(1, $stub->updates);
			$this->assertSame(5, $stub->subscriptionsByEndpoint[$endpoint]['user_id']);
		});
	}

	public function testIsValidSubscriptionAcceptsWellFormedPayload(): void
	{
		$this->assertTrue(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		]));
	}

	public function testIsValidSubscriptionRejectsHttpEndpoint(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'http://fcm.googleapis.com/fcm/send/example',
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		]));
	}

	public function testIsValidSubscriptionRejectsJavascriptUrl(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'javascript:alert(1)',
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		]));
	}

	public function testIsValidSubscriptionAcceptsLongFcmEndpoint(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/abc:APA91b' . str_repeat('x', 500);
		$this->assertGreaterThan(512, strlen($endpoint));
		$this->assertTrue(PushNotificationService::isValidSubscription([
			'endpoint' => $endpoint,
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		]));
	}

	public function testIsValidSubscriptionRejectsOversizedEndpoint(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://example.com/' . str_repeat('a', 2048),
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
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
}
