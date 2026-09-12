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

			$this->assertTrue(PushNotificationService::saveSubscription(99, $this->validSubscription($endpoint)));
			$this->assertCount(1, array_filter($stub->updates, static fn (array $row): bool => str_contains($row['qry'], '%%PUSH_SUBSCRIPTIONS%%')));
			$this->assertSame([], $stub->inserts);
			$this->assertSame(99, $stub->subscriptionsByEndpoint[$endpoint]['user_id']);
			$this->assertSame(1, $stub->settingsPushByUser[7]);
		});
	}

	public function testSaveSubscriptionReassignSurvivesPreviousOwnerDisableSideEffects(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/shared-device';
		$otherEndpoint = 'https://fcm.googleapis.com/fcm/send/other-device';
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub) use ($endpoint, $otherEndpoint): void {
			$stub->subscriptionsByEndpoint[$endpoint] = [
				'user_id'  => 7,
				'endpoint' => $endpoint,
				'p256dh'   => 'old-key',
				'auth'     => 'old-auth',
			];
			$stub->subscriptionsByEndpoint[$otherEndpoint] = [
				'user_id'  => 7,
				'endpoint' => $otherEndpoint,
				'p256dh'   => 'other-key',
				'auth'     => 'other-auth',
			];

			$this->assertTrue(PushNotificationService::saveSubscription(99, $this->validSubscription($endpoint)));

			$this->assertSame(99, $stub->subscriptionsByEndpoint[$endpoint]['user_id']);
			$this->assertSame(7, $stub->subscriptionsByEndpoint[$otherEndpoint]['user_id']);
		});
	}

	public function testSaveSubscriptionDropsOtherEndpointsForSameUser(): void
	{
		$keep = 'https://fcm.googleapis.com/fcm/send/keep';
		$stale = 'https://fcm.googleapis.com/fcm/send/dual-sw-leftover';
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub) use ($keep, $stale): void {
			$stub->subscriptionsByEndpoint[$stale] = [
				'user_id'  => 42,
				'endpoint' => $stale,
				'p256dh'   => 'old-key',
				'auth'     => 'old-auth',
			];

			$this->assertTrue(PushNotificationService::saveSubscription(42, $this->validSubscription($keep)));
			$this->assertArrayHasKey($keep, $stub->subscriptionsByEndpoint);
			$this->assertArrayNotHasKey($stale, $stub->subscriptionsByEndpoint);
			$this->assertSame(42, $stub->subscriptionsByEndpoint[$keep]['user_id']);
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

	public function testExpeditionChoicePushMentionsReview(): void
	{
		$previousLng = $GLOBALS['LNG'] ?? null;
		$GLOBALS['LNG'] = [
			'push_expedition_title' => 'Expedition complete',
			'push_expedition_body' => 'Your expedition has returned.',
			'push_expedition_choice_body' => 'Your expedition returned with a choice to review.',
		];
		try {
			$plain = PushNotificationService::expeditionResultMessage(false);
			$choice = PushNotificationService::expeditionResultMessage(true);
			$this->assertStringNotContainsString('choice', strtolower($plain['body']));
			$this->assertStringContainsString('choice', strtolower($choice['body']));
			$this->assertTrue($choice['data']['choice']);
		} finally {
			if ($previousLng === null) {
				unset($GLOBALS['LNG']);
			} else {
				$GLOBALS['LNG'] = $previousLng;
			}
		}
	}

	public function testNotifySkippedWhenNotConfigured(): void
	{
		PushNotificationService::notifyExpeditionResult(0, true);
		PushNotificationService::notifyExpeditionResult(42, true);
		PushNotificationService::notifyDirectiveMilestone(0, 'directive_completable');
		PushNotificationService::notifyDirectiveMilestone(42, 'directive_completable');
		PushNotificationService::notifyDirectiveMilestone(42, 'directive_period_ending', TIMESTAMP);
		$this->assertFalse(PushNotificationService::isConfigured());
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

	public function testHasSubscriptionReportsPersistedRow(): void
	{
		$this->withDatabaseStub(function (PushSubscriptionDatabaseStub $stub): void {
			$this->assertFalse(PushNotificationService::hasSubscription(0));
			$this->assertFalse(PushNotificationService::hasSubscription(42));
			$stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/example'] = [
				'user_id'  => 42,
				'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
				'p256dh'   => 'key',
				'auth'     => 'auth',
			];
			$this->assertTrue(PushNotificationService::hasSubscription(42));
			$this->assertFalse(PushNotificationService::hasSubscription(99));
		});
	}

	public function testFormatDeliveryFailureUsesHostAndStripsEndpoint(): void
	{
		$endpoint = 'https://fcm.googleapis.com/fcm/send/secret-token';
		$line = PushNotificationService::formatDeliveryFailure(
			$endpoint,
			false,
			'Forbidden for ' . $endpoint,
			403
		);
		$this->assertStringContainsString('host=fcm.googleapis.com', $line);
		$this->assertStringContainsString('status=403', $line);
		$this->assertStringNotContainsString('secret-token', $line);
	}

	public function testTestCooldownAndMessage(): void
	{
		$this->assertSame(0, PushNotificationService::testCooldownRemaining(0, 1_000));
		$this->assertSame(40, PushNotificationService::testCooldownRemaining(980, 1_000));
		$this->assertSame(0, PushNotificationService::testCooldownRemaining(900, 1_000));

		$message = PushNotificationService::testMessage([
			'push_test_title' => 'HiveNova push test',
			'push_test_body'  => 'If you see this, Web Push delivery works.',
		]);
		$this->assertSame('HiveNova push test', $message['title']);
		$this->assertSame('push_test', $message['data']['type']);
		$this->assertSame('game.php?page=overview', $message['data']['url']);
		$this->assertSame('push_test-' . TIMESTAMP, $message['data']['tag']);
		$this->assertSame('push_test-' . TIMESTAMP, PushNotificationService::notificationTag($message['data']));
		$this->assertSame('push_test', PushNotificationService::notificationTag(['type' => 'push_test']));
		$this->assertSame('hivenova', PushNotificationService::notificationTag([]));
	}

	public function testSendTestNotificationSkippedWhenNotConfigured(): void
	{
		$result = PushNotificationService::sendTestNotification(42);
		$this->assertFalse($result['ok']);
		$this->assertSame(PushNotificationService::SKIP_NOT_CONFIGURED, $result['skipped']);
	}

	public function testNotifyUserSkippedWhenNotConfigured(): void
	{
		$result = PushNotificationService::notifyUser(1, 't', 'b');
		$this->assertFalse($result['ok']);
		$this->assertSame(PushNotificationService::SKIP_NOT_CONFIGURED, $result['skipped']);
	}

	public function testEndpointHostFallsBackWhenInvalid(): void
	{
		$this->assertSame('unknown', PushNotificationService::endpointHost('not-a-url'));
		$this->assertSame('updates.push.services.mozilla.com', PushNotificationService::endpointHost(
			'https://updates.push.services.mozilla.com/wpush/v2/token'
		));
	}

	public function testResolveContentEncodingAcceptsKnownValuesOnly(): void
	{
		$this->assertNull(PushNotificationService::resolveContentEncoding(null));
		$this->assertNull(PushNotificationService::resolveContentEncoding(''));
		$this->assertNull(PushNotificationService::resolveContentEncoding('identity'));
		$this->assertSame('aes128gcm', PushNotificationService::resolveContentEncoding('aes128gcm'));
		$this->assertSame('aesgcm', PushNotificationService::resolveContentEncoding('AESGCM'));
	}

	public function testSubscriptionCreatePayloadOmitsForcedEncoding(): void
	{
		$plain = PushNotificationService::subscriptionCreatePayload([
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/x',
			'p256dh'   => 'pk',
			'auth'     => 'ak',
		]);
		$this->assertArrayNotHasKey('contentEncoding', $plain);

		$with = PushNotificationService::subscriptionCreatePayload([
			'endpoint'          => 'https://fcm.googleapis.com/fcm/send/x',
			'p256dh'            => 'pk',
			'auth'              => 'ak',
			'content_encoding'  => 'aes128gcm',
		]);
		$this->assertSame('aes128gcm', $with['contentEncoding']);
	}

	public function testVapidKeysMatchForGeneratedPair(): void
	{
		$key = openssl_pkey_new([
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		]);
		$this->assertNotFalse($key);
		$details = openssl_pkey_get_details($key);
		$this->assertIsArray($details);
		$d = str_pad((string) $details['ec']['d'], 32, "\0", STR_PAD_LEFT);
		$x = str_pad((string) $details['ec']['x'], 32, "\0", STR_PAD_LEFT);
		$y = str_pad((string) $details['ec']['y'], 32, "\0", STR_PAD_LEFT);
		$public = rtrim(strtr(base64_encode("\x04" . $x . $y), '+/', '-_'), '=');
		$private = rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

		$this->assertTrue(PushNotificationService::vapidKeysMatch($public, $private));
		$this->assertFalse(PushNotificationService::vapidKeysMatch($public, strrev($private)));
		$this->assertFalse(PushNotificationService::vapidKeysMatch('not-a-key', $private));
	}

	public function testIsVapidOkFalseWhenNotConfigured(): void
	{
		PushNotificationService::setVapidOkOverride(null);
		PushNotificationService::setConfiguredOverride(null);
		$this->assertFalse(PushNotificationService::isConfigured());
		$this->assertFalse(PushNotificationService::isVapidOk());
	}

	public function testTestClientPayloadShapeOmitsSecrets(): void
	{
		$payload = PushNotificationService::testClientPayload([
			'ok'        => true,
			'delivered' => 1,
			'attempted' => 1,
			'failed'    => 0,
			'skipped'   => null,
			'lastError' => null,
			'failures'  => [],
		], true);
		$this->assertSame([
			'ok'         => true,
			'delivered'  => 1,
			'attempted'  => 1,
			'failed'     => 0,
			'skipped'    => null,
			'subscribed' => true,
			'lastError'  => null,
			'failures'   => [],
		], $payload);

		$failed = PushNotificationService::testClientPayload([
			'ok'        => false,
			'delivered' => 0,
			'attempted' => 1,
			'failed'    => 1,
			'skipped'   => null,
			'lastError' => PushNotificationService::formatDeliveryFailure(
				'https://fcm.googleapis.com/fcm/send/secret-token',
				false,
				'403 Forbidden',
				403
			),
			'failures'  => [
				PushNotificationService::failureEntry('https://fcm.googleapis.com/fcm/send/secret-token', 403, false),
				['host' => 'https://evil.example/token', 'status' => 'nope', 'expired' => 1],
			],
		], true);
		$this->assertFalse($failed['ok']);
		$this->assertSame(1, $failed['failed']);
		$this->assertSame('fcm.googleapis.com', $failed['failures'][0]['host']);
		$this->assertSame(403, $failed['failures'][0]['status']);
		$this->assertFalse($failed['failures'][0]['expired']);
		$this->assertSame('unknown', $failed['failures'][1]['host']);
		$this->assertStringNotContainsString('secret-token', json_encode($failed, JSON_THROW_ON_ERROR));
		$this->assertStringContainsString('status=403', (string) $failed['lastError']);
	}

	public function testSkipVapidMismatchPayload(): void
	{
		$result = PushNotificationService::skipResult(PushNotificationService::SKIP_VAPID_MISMATCH);
		$payload = PushNotificationService::testClientPayload($result, true);
		$this->assertSame('vapid_mismatch', $payload['skipped']);
		$this->assertSame(0, $payload['attempted']);
		$this->assertFalse($payload['ok']);
	}
}
