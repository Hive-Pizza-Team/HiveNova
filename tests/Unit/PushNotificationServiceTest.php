<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\PushNotificationService;
use PHPUnit\Framework\TestCase;

if (!defined('TIMESTAMP')) {
	define('TIMESTAMP', 1_700_000_000);
}

/**
 * Tracks push subscription writes for saveSubscription tests.
 */
class PushSubscriptionDatabaseStub implements DatabaseInterface
{
	/** @var array<string, array{user_id: int, endpoint: string, p256dh: string, auth: string}> */
	public array $subscriptionsByEndpoint = [];

	public array $updates = [];
	public array $inserts = [];

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && str_contains($qry, 'endpoint')) {
			$endpoint = $params[':endpoint'] ?? '';
			$row = $this->subscriptionsByEndpoint[$endpoint] ?? null;
			if ($row === null) {
				return false;
			}

			return $field === false ? $row : ($row[$field] ?? false);
		}

		return false;
	}

	public function select($qry, array $params = []) { return []; }
	public function delete($qry, array $params = []) { return 0; }
	public function replace($qry, array $params = []) { return 0; }
	public function query($qry) { return 0; }
	public function nativeQuery($qry) { return false; }
	public function lastInsertId() { return false; }
	public function rowCount() { return false; }
	public function getQueryCounter() { return 0; }
	public function quote($str) { return "'" . addslashes((string) $str) . "'"; }
	public function disconnect() {}
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	public function insert($qry, array $params = [])
	{
		$this->inserts[] = $params;
		$this->subscriptionsByEndpoint[$params[':endpoint']] = [
			'user_id'  => (int) $params[':userId'],
			'endpoint' => $params[':endpoint'],
			'p256dh'   => $params[':p256dh'],
			'auth'     => $params[':auth'],
		];

		return 1;
	}

	public function update($qry, array $params = [])
	{
		$this->updates[] = $params;
		$this->subscriptionsByEndpoint[$params[':endpoint']] = [
			'user_id'  => (int) $params[':userId'],
			'endpoint' => $params[':endpoint'],
			'p256dh'   => $params[':p256dh'],
			'auth'     => $params[':auth'],
		];

		return 1;
	}
}

class PushNotificationServiceTest extends TestCase
{
	private ?DatabaseInterface $previousDb = null;

	protected function setUp(): void
	{
		$this->previousDb = Database::get();
	}

	protected function tearDown(): void
	{
		if ($this->previousDb !== null) {
			Database::setInstance($this->previousDb);
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
		$stub = new PushSubscriptionDatabaseStub();
		Database::setInstance($stub);

		$this->assertTrue(PushNotificationService::saveSubscription(42, $this->validSubscription()));
		$this->assertCount(1, $stub->inserts);
		$this->assertSame([], $stub->updates);
		$this->assertSame(42, $stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/example']['user_id']);
	}

	public function testSaveSubscriptionReassignsEndpointFromAnotherUser(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/shared-device';
		$stub = new PushSubscriptionDatabaseStub();
		$stub->subscriptionsByEndpoint[$endpoint] = [
			'user_id'  => 7,
			'endpoint' => $endpoint,
			'p256dh'   => 'old-key',
			'auth'     => 'old-auth',
		];
		Database::setInstance($stub);

		$this->assertTrue(PushNotificationService::saveSubscription(99, $this->validSubscription($endpoint)));
		$this->assertCount(1, $stub->updates);
		$this->assertSame([], $stub->inserts);
		$this->assertSame(99, $stub->subscriptionsByEndpoint[$endpoint]['user_id']);
		$this->assertSame(99, $stub->updates[0][':userId']);
	}

	public function testSaveSubscriptionUpdatesExistingEndpointForSameUser(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/same-user';
		$stub = new PushSubscriptionDatabaseStub();
		$stub->subscriptionsByEndpoint[$endpoint] = [
			'user_id'  => 5,
			'endpoint' => $endpoint,
			'p256dh'   => 'old-key',
			'auth'     => 'old-auth',
		];
		Database::setInstance($stub);

		$this->assertTrue(PushNotificationService::saveSubscription(5, $this->validSubscription($endpoint)));
		$this->assertCount(1, $stub->updates);
		$this->assertSame(5, $stub->subscriptionsByEndpoint[$endpoint]['user_id']);
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

	public function testIsValidSubscriptionRejectsOversizedEndpoint(): void
	{
		$this->assertFalse(PushNotificationService::isValidSubscription([
			'endpoint' => 'https://example.com/' . str_repeat('a', 512),
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
