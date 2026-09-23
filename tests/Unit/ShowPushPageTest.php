<?php

declare(strict_types=1);

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\PushNotificationService;
use HiveNova\Page\Game\ShowPushPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/PushSubscriptionDatabaseStub.php';

if (!defined('TIMESTAMP')) {
	define('TIMESTAMP', 1_700_000_000);
}

/**
 * @internal
 */
final class TestableShowPushPage extends ShowPushPage
{
	public ?array $jsonResponse = null;

	public string $testBody = '';

	public int $testAt = 0;

	/** @var array<string, mixed>|null */
	public ?array $storedTestResult = null;

	public function __construct()
	{
	}

	protected function lastTestAt(): int
	{
		return $this->testAt;
	}

	protected function rememberTestAt(int $now): void
	{
		$this->testAt = $now;
	}

	protected function lastTestResult(): ?array
	{
		return $this->storedTestResult;
	}

	protected function rememberTestResult(array $result): void
	{
		$this->storedTestResult = $result;
	}

	protected function sendJSON($data): void
	{
		$this->jsonResponse = $data;
	}

	protected function save(): void
	{
	}

	protected function readSubscribeBody(): string
	{
		return $this->testBody;
	}
}

class ShowPushPageTest extends TestCase
{
	private ?DatabaseInterface $previousDb = null;

	private array $previousServer = [];

	protected function setUp(): void
	{
		global $USER;

		$this->previousServer = $_SERVER;
		$USER = ['id' => 42];

		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$this->previousDb = $prop->getValue();
	}

	protected function tearDown(): void
	{
		global $USER;

		$_SERVER = $this->previousServer;
		unset($USER);

		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		if ($this->previousDb instanceof DatabaseInterface) {
			Database::setInstance($this->previousDb);
		} else {
			$prop->setValue(null);
		}
	}

	private function validSubscriptionJson(): string
	{
		return json_encode([
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
			'keys'     => [
				'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
				'auth'   => 'tBHItJI5svbpez7KI4CCXg',
			],
		], JSON_THROW_ON_ERROR);
	}

	private function invokeSubscribe(TestableShowPushPage $page): void
	{
		$method = new ReflectionMethod(ShowPushPage::class, 'subscribe');
		$method->invoke($page);
	}

	public function testSubscribeRejectsGetRequest(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$page = new TestableShowPushPage();

		$this->invokeSubscribe($page);

		$this->assertSame(['error' => 'method_not_allowed'], $page->jsonResponse);
	}

	public function testSubscribeRejectsEmptyBody(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$page = new TestableShowPushPage();
		$page->testBody = '';

		$this->invokeSubscribe($page);

		$this->assertSame(['error' => 'empty_body'], $page->jsonResponse);
	}

	public function testSubscribeRejectsInvalidJson(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$page = new TestableShowPushPage();
		$page->testBody = '{not json';

		$this->invokeSubscribe($page);

		$this->assertSame(['error' => 'invalid_json'], $page->jsonResponse);
	}

	public function testSubscribeRejectsInvalidSubscription(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$page = new TestableShowPushPage();
		$page->testBody = '{"endpoint":"http://insecure.test","keys":{"p256dh":"x","auth":"y"}}';

		$this->invokeSubscribe($page);

		$this->assertSame(['error' => 'invalid_subscription'], $page->jsonResponse);
	}

	public function testSubscribeAcceptsValidSubscription(): void
	{
		$stub = new PushSubscriptionDatabaseStub();
		Database::setInstance($stub);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$page = new TestableShowPushPage();
		$page->testBody = $this->validSubscriptionJson();

		$this->invokeSubscribe($page);

		$this->assertSame(['ok' => true], $page->jsonResponse);
		$this->assertCount(1, $stub->inserts);
		$this->assertSame(1, $stub->settingsPushByUser[42] ?? 0);
	}

	public function testStatusIncludesSubscribedBoolean(): void
	{
		$stub = new PushSubscriptionDatabaseStub();
		Database::setInstance($stub);
		$page = new TestableShowPushPage();
		$page->status();
		$this->assertFalse($page->jsonResponse['subscribed']);

		$stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/example'] = [
			'user_id'  => 42,
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
			'p256dh'   => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
			'auth'     => 'tBHItJI5svbpez7KI4CCXg',
		];
		$page->status();
		$this->assertTrue($page->jsonResponse['subscribed']);
		$this->assertArrayHasKey('configured', $page->jsonResponse);
		$this->assertArrayHasKey('enabled', $page->jsonResponse);
		$this->assertArrayHasKey('vapidOk', $page->jsonResponse);
		$this->assertFalse($page->jsonResponse['vapidOk']);
	}

	public function testTestPingRejectsGetAndRateLimits(): void
	{
		$stub = new PushSubscriptionDatabaseStub();
		Database::setInstance($stub);
		$page = new TestableShowPushPage();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$page->test();
		$this->assertSame(['error' => 'method_not_allowed'], $page->jsonResponse);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$page->testAt = TIMESTAMP;
		$page->storedTestResult = [
			'ok'        => false,
			'delivered' => 0,
			'attempted' => 1,
			'failed'    => 1,
			'skipped'   => null,
			'lastError' => 'PushNotificationService: flush failed host=fcm.googleapis.com status=403 expired=0 reason=Forbidden',
			'failures'  => [['host' => 'fcm.googleapis.com', 'status' => 403, 'expired' => false]],
		];
		$page->test();
		$this->assertSame('rate_limited', $page->jsonResponse['error']);
		$this->assertSame(PushNotificationService::TEST_COOLDOWN, $page->jsonResponse['retryAfter']);
		$this->assertSame(1, $page->jsonResponse['failed']);
		$this->assertSame('fcm.googleapis.com', $page->jsonResponse['failures'][0]['host']);

		$page->testAt = 0;
		$page->test();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertFalse($page->jsonResponse['subscribed']);
		$this->assertArrayHasKey('failed', $page->jsonResponse);
		$this->assertArrayHasKey('lastError', $page->jsonResponse);
		$this->assertArrayHasKey('failures', $page->jsonResponse);
		$this->assertSame(0, $page->testAt);
	}

	public function testTestPingSkipsVapidMismatchWithoutCooldown(): void
	{
		$stub = new PushSubscriptionDatabaseStub();
		Database::setInstance($stub);
		PushNotificationService::setConfiguredOverride(true);
		PushNotificationService::setVapidOkOverride(false);
		try {
			$page = new TestableShowPushPage();
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$page->test();
			$this->assertFalse($page->jsonResponse['ok']);
			$this->assertSame(PushNotificationService::SKIP_VAPID_MISMATCH, $page->jsonResponse['skipped']);
			$this->assertSame(0, $page->jsonResponse['attempted']);
			$this->assertSame(0, $page->testAt);
		} finally {
			PushNotificationService::setConfiguredOverride(null);
			PushNotificationService::setVapidOkOverride(null);
		}
	}

	public function testTestPingStartsCooldownOnlyAfterFlushAttempt(): void
	{
		$stub = new PushSubscriptionDatabaseStub();
		$stub->subscriptionsByEndpoint['https://fcm.googleapis.com/fcm/send/ok'] = [
			'user_id'  => 42,
			'endpoint' => 'https://fcm.googleapis.com/fcm/send/ok',
			'p256dh'   => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpY',
			'auth'     => 'tBHItJI5svbpez7KI4CCXg',
		];
		Database::setInstance($stub);
		PushNotificationService::setConfiguredOverride(true);
		PushNotificationService::setVapidOkOverride(true);
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
		try {
			$page = new TestableShowPushPage();
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$page->test();
			$this->assertTrue($page->jsonResponse['ok']);
			$this->assertSame(1, $page->jsonResponse['delivered']);
			$this->assertSame(0, $page->jsonResponse['failed']);
			$this->assertNull($page->jsonResponse['skipped']);
			$this->assertSame([], $page->jsonResponse['failures']);
			$this->assertGreaterThan(0, $page->testAt);
			$this->assertSame(1, $page->storedTestResult['delivered'] ?? 0);
		} finally {
			PushNotificationService::setConfiguredOverride(null);
			PushNotificationService::setVapidOkOverride(null);
			PushNotificationService::setWebPushFactory(null);
		}
	}
}
