<?php

declare(strict_types=1);

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
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

	public function __construct()
	{
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
}
