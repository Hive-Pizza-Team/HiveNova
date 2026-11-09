<?php

use HiveNova\Core\FleetReturnPushService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FleetReturnPushDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetReturnPushServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private FleetReturnPushDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new FleetReturnPushDatabaseStub();
		$this->swapDatabaseInstance($this->db);
		\HiveNova\Core\PushNotificationService::setErrorLogger(static function (): void {});
	}

	protected function tearDown(): void
	{
		\HiveNova\Core\PushNotificationService::setErrorLogger(null);
		\HiveNova\Core\PushNotificationService::setWebPushFactory(null);
		\HiveNova\Core\PushNotificationService::setConfiguredOverride(null);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testNormalizeFleetRejectsInvalid(): void
	{
		$this->assertNull(FleetReturnPushService::normalizeFleet([]));
		$this->assertNull(FleetReturnPushService::normalizeFleet([
			'fleetId' => 0, 'mission' => 3, 'returnEnd' => 10,
		]));
		$this->assertNull(FleetReturnPushService::normalizeFleet([
			'fleetId' => 1, 'mission' => 0, 'returnEnd' => 10,
		]));
		$this->assertNull(FleetReturnPushService::normalizeFleet([
			'fleetId' => 1, 'mission' => 3, 'returnEnd' => 0,
		]));
	}

	public function testNormalizeFleetAcceptsValid(): void
	{
		$fleet = FleetReturnPushService::normalizeFleet([
			'fleetId'    => 9,
			'mission'    => 3,
			'galaxy'     => 1,
			'system'     => 2,
			'planet'     => 3,
			'planetName' => 'Home',
			'returnEnd'  => 100,
		]);

		$this->assertSame(9, $fleet['fleetId']);
		$this->assertSame(3, $fleet['mission']);
		$this->assertSame('Home', $fleet['planetName']);
	}

	public function testFleetKeyIsStable(): void
	{
		$this->assertSame('42', FleetReturnPushService::fleetKey(42));
	}

	public function testFleetReturnDigestUsesLanguageAndDeepLink(): void
	{
		$message = FleetReturnPushService::fleetReturnDigest([
			[
				'missionName' => 'Transport',
				'planetName'  => 'Homeworld',
				'galaxy'      => 1,
				'system'      => 2,
				'planet'      => 3,
			],
		], [
			'push_fleet_return_title' => 'Fleet returned',
			'push_fleet_return_body'  => '%s returned to %s [%d:%d:%d]',
		]);

		$this->assertSame('Fleet returned', $message['title']);
		$this->assertSame('Transport returned to Homeworld [1:2:3]', $message['body']);
		$this->assertSame('game.php?page=fleetTable', $message['data']['url']);
		$this->assertSame('fleet_return', $message['data']['type']);
		$this->assertSame(1, $message['data']['count']);
	}

	public function testFleetReturnDigestMergesMultipleFleets(): void
	{
		$message = FleetReturnPushService::fleetReturnDigest([
			['missionName' => 'Transport', 'planetName' => 'Home', 'galaxy' => 1, 'system' => 1, 'planet' => 1],
			['missionName' => 'Attack', 'planetName' => 'Colony', 'galaxy' => 2, 'system' => 2, 'planet' => 2],
			['missionName' => 'Spy', 'planetName' => 'Home', 'galaxy' => 1, 'system' => 1, 'planet' => 1],
		], [
			'push_fleet_return_body_many' => '%s returned to %s and %d more fleets are home',
		]);

		$this->assertSame('Transport returned to Home and 2 more fleets are home', $message['body']);
		$this->assertSame(3, $message['data']['count']);
	}

	public function testNotifyFleetsSkippedWhenNotConfigured(): void
	{
		$sent = [];
		$service = new FleetReturnPushService(
			notifier: static function () use (&$sent): void {
				$sent[] = func_get_args();
			},
			configured: false,
		);

		$this->assertSame(0, $service->notifyFleets(1, [[
			'fleetId' => 1, 'mission' => 3, 'galaxy' => 1, 'system' => 1, 'planet' => 1,
			'planetName' => 'Home', 'returnEnd' => 100,
		]], 'en'));
		$this->assertSame([], $sent);
		$this->assertSame([], $this->db->inserts);
	}

	public function testNotifyFleetsSkippedForInvalidIds(): void
	{
		$service = new FleetReturnPushService(configured: true);
		$this->assertSame(0, $service->notifyFleets(0, [[
			'fleetId' => 1, 'mission' => 3, 'returnEnd' => 100,
		]]));
		$this->assertSame(0, $service->notifyFleets(1, [[
			'fleetId' => 0, 'mission' => 3, 'returnEnd' => 100,
		]]));
	}

	public function testNotifyFleetsSendsOnceAndDedups(): void
	{
		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyFleets(42, [[
			'fleetId' => 9, 'mission' => 3, 'galaxy' => 1, 'system' => 2, 'planet' => 3,
			'planetName' => 'Homeworld', 'returnEnd' => 500,
		]], 'en'));
		$this->assertSame(0, $service->notifyFleets(42, [[
			'fleetId' => 9, 'mission' => 3, 'galaxy' => 1, 'system' => 2, 'planet' => 3,
			'planetName' => 'Homeworld', 'returnEnd' => 500,
		]], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame(42, $sent[0]['userId']);
		$this->assertSame('Transport returned to Homeworld [1:2:3]', $sent[0]['body']);
		$this->assertSame('game.php?page=fleetTable', $sent[0]['data']['url']);
		$this->assertCount(1, $this->db->inserts);
	}

	public function testNotifyFleetsMergesMultipleFleetsIntoOnePush(): void
	{
		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyFleets(7, [
			['fleetId' => 1, 'mission' => 3, 'galaxy' => 1, 'system' => 1, 'planet' => 1, 'planetName' => 'Home', 'returnEnd' => 100],
			['fleetId' => 2, 'mission' => 1, 'galaxy' => 1, 'system' => 1, 'planet' => 1, 'planetName' => 'Home', 'returnEnd' => 110],
		], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame('Transport returned to Home and 1 more fleets are home', $sent[0]['body']);
		$this->assertSame(2, $sent[0]['data']['count']);
		$this->assertCount(2, $this->db->inserts);
	}

	public function testNotifyFleetsIgnoresAlreadyNotifiedWhenMerging(): void
	{
		$this->db->notified['1'] = [
			'fleet_id' => 1, 'user_id' => 7, 'mission' => 3, 'galaxy' => 1, 'system' => 1,
			'planet' => 1, 'planet_name' => 'Home', 'return_end' => 100, 'notified_at' => 50,
		];

		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyFleets(7, [
			['fleetId' => 1, 'mission' => 3, 'galaxy' => 1, 'system' => 1, 'planet' => 1, 'planetName' => 'Home', 'returnEnd' => 100],
			['fleetId' => 2, 'mission' => 6, 'galaxy' => 2, 'system' => 2, 'planet' => 2, 'planetName' => 'Colony', 'returnEnd' => 110],
		], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame('Espionage returned to Colony [2:2:2]', $sent[0]['body']);
		$this->assertSame(1, $sent[0]['data']['count']);
	}

	public function testNotifyFleetsSwallowsDatabaseErrors(): void
	{
		$throwing = new class extends FleetReturnPushDatabaseStub {
			public function selectSingle($qry, array $params = [], $field = false)
			{
				throw new RuntimeException('db down');
			}
		};
		$this->swapDatabaseInstance($throwing);

		$service = new FleetReturnPushService(configured: true);
		$this->assertSame(0, $service->notifyFleets(1, [[
			'fleetId' => 1, 'mission' => 3, 'returnEnd' => 100,
		]]));
	}

	public function testStaticNotifyReturnedFleetNoopsWhenNotConfigured(): void
	{
		$this->assertFalse(FleetReturnPushService::notifyReturnedFleet(
			1, 2, 3, 1, 1, 1, 'Home', 100
		));
	}

	public function testIsConfiguredDefersToPushServiceWhenUnset(): void
	{
		$service = new FleetReturnPushService();
		$this->assertFalse($service->isConfigured());
	}

	public function testNotifyFleetsDoesNotMarkSentWhenDeliveryFails(): void
	{
		$failing = new FleetReturnPushService(
			notifier: static fn (): bool => false,
			configured: true,
			languageLoader: static fn (): array => [
				'type_mission_3' => 'Transport',
				'push_fleet_return_body' => '%s returned to %s [%d:%d:%d]',
			],
		);
		$this->assertSame(0, $failing->notifyFleets(7, [[
			'fleetId' => 3, 'mission' => 3, 'galaxy' => 1, 'system' => 1, 'planet' => 1,
			'planetName' => 'Home', 'returnEnd' => 100,
		]], 'en'));
		$this->assertSame(0, $this->db->notified['3']['notified_at']);
	}

	public function testRunRetriesPendingAfterFleetWasCleared(): void
	{
		$this->db->notified['3'] = [
			'fleet_id' => 3, 'user_id' => 7, 'mission' => 3, 'galaxy' => 1, 'system' => 1,
			'planet' => 1, 'planet_name' => 'Homeworld', 'return_end' => 100, 'notified_at' => 0,
			'has_subscription' => true, 'settings_push' => 1, 'lang' => 'en',
		];

		$sent = [];
		$service = new FleetReturnPushService(
			notifier: static function (int $userId) use (&$sent): void {
				$sent[] = $userId;
			},
			configured: true,
			languageLoader: static fn (): array => ['type_mission_3' => 'Transport'],
		);

		$this->assertSame(1, $service->run(1_700_000_200));
		$this->assertSame([7], $sent);
		$this->assertGreaterThan(0, $this->db->notified['3']['notified_at']);
	}

	public function testRunNotifiesDueFleetsAndCleansOldRows(): void
	{
		$now = 1_700_000_200;
		$this->db->notified['99'] = [
			'fleet_id' => 99, 'user_id' => 1, 'mission' => 3, 'galaxy' => 1, 'system' => 1,
			'planet' => 1, 'planet_name' => 'Old', 'return_end' => 1, 'notified_at' => 1,
		];
		$this->db->fleets[] = [
			'fleet_id' => 3, 'user_id' => 7, 'fleet_mission' => 3, 'return_end' => $now - 50,
			'galaxy' => 1, 'system' => 2, 'planet' => 3, 'planet_name' => 'Colony',
			'fleet_mess' => 1, 'lang' => 'en', 'has_subscription' => true, 'settings_push' => 1,
		];
		$this->db->fleets[] = [
			'fleet_id' => 4, 'user_id' => 8, 'fleet_mission' => 3, 'return_end' => $now - 10,
			'galaxy' => 1, 'system' => 1, 'planet' => 1, 'planet_name' => 'Muted',
			'fleet_mess' => 1, 'lang' => 'en', 'has_subscription' => true, 'settings_push' => 0,
		];

		$sent = [];
		$service = new FleetReturnPushService(
			notifier: static function (int $userId) use (&$sent): void {
				$sent[] = $userId;
			},
			configured: true,
			languageLoader: static fn (): array => ['type_mission_3' => 'Transport'],
		);

		$this->assertSame(1, $service->run($now));
		$this->assertSame([7], $sent);
		$this->assertNotEmpty($this->db->deletes);
		$this->assertArrayNotHasKey('99', $this->db->notified);
	}

	public function testRunMergesDueFleetsForOneUser(): void
	{
		$now = 1_700_000_200;
		$this->db->fleets[] = [
			'fleet_id' => 3, 'user_id' => 7, 'fleet_mission' => 3, 'return_end' => $now - 50,
			'galaxy' => 1, 'system' => 1, 'planet' => 1, 'planet_name' => 'Homeworld',
			'fleet_mess' => 1, 'lang' => 'en', 'has_subscription' => true, 'settings_push' => 1,
		];
		$this->db->fleets[] = [
			'fleet_id' => 5, 'user_id' => 7, 'fleet_mission' => 1, 'return_end' => $now - 10,
			'galaxy' => 1, 'system' => 1, 'planet' => 1, 'planet_name' => 'Homeworld',
			'fleet_mess' => 1, 'lang' => 'en', 'has_subscription' => true, 'settings_push' => 1,
		];

		$sent = [];
		$service = new FleetReturnPushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'body', 'data');
			},
			configured: true,
			languageLoader: static fn (): array => [
				'push_fleet_return_body_many' => '%s returned to %s and %d more fleets are home',
				'type_mission_3' => 'Transport',
				'type_mission_1' => 'Attack',
			],
		);

		$this->assertSame(1, $service->run($now));
		$this->assertCount(1, $sent);
		$this->assertSame(7, $sent[0]['userId']);
		$this->assertSame(2, $sent[0]['data']['count']);
	}

	public function testRunSkipsWhenNotConfigured(): void
	{
		$service = new FleetReturnPushService(configured: false);
		$this->assertSame(0, $service->run(1_700_000_000));
	}

	public function testRunReturnsZeroWhenNoCandidates(): void
	{
		$service = new FleetReturnPushService(configured: true);
		$this->assertSame(0, $service->run(1_700_000_000));
		$this->assertNotEmpty($this->db->deletes);
	}

	public function testRunSwallowsUnexpectedFailures(): void
	{
		$throwing = new class extends FleetReturnPushDatabaseStub {
			public function select($qry, array $params = [])
			{
				throw new RuntimeException('boom');
			}
		};
		$this->swapDatabaseInstance($throwing);

		$service = new FleetReturnPushService(configured: true);
		$this->assertSame(0, $service->run(1));
	}

	public function testLoadLanguageReadsRepoFiles(): void
	{
		$sent = [];
		$service = new FleetReturnPushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = [$title, $body];
			},
			configured: true,
		);

		$this->assertSame(1, $service->notifyFleets(1, [[
			'fleetId' => 8, 'mission' => 3, 'galaxy' => 1, 'system' => 2, 'planet' => 3,
			'planetName' => 'Homeworld', 'returnEnd' => 99,
		]], 'en'));
		$this->assertSame('Fleet returned', $sent[0][0]);
		$this->assertStringContainsString('Homeworld', $sent[0][1]);
		$this->assertStringContainsString('[1:2:3]', $sent[0][1]);
	}

	/**
	 * @param list<array{userId: int, title: string, body: string, data: array<string, mixed>}> $sent
	 */
	private function serviceCollecting(array &$sent): FleetReturnPushService
	{
		return new FleetReturnPushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'title', 'body', 'data');
			},
			configured: true,
			languageLoader: static fn (): array => [
				'push_fleet_return_title'     => 'Fleet returned',
				'push_fleet_return_body'      => '%s returned to %s [%d:%d:%d]',
				'push_fleet_return_body_many' => '%s returned to %s and %d more fleets are home',
				'type_mission_1'              => 'Attack',
				'type_mission_3'              => 'Transport',
				'type_mission_6'              => 'Espionage',
			],
		);
	}
}
