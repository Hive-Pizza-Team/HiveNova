<?php

use HiveNova\Core\BuildingCompletePushService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/BuildingCompletePushDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class BuildingCompletePushServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private BuildingCompletePushDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new BuildingCompletePushDatabaseStub();
		$this->swapDatabaseInstance($this->db);
		\HiveNova\Core\PushNotificationService::setErrorLogger(static function (): void {});
	}

	protected function tearDown(): void
	{
		\HiveNova\Core\PushNotificationService::setErrorLogger(null);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testDueConstructionJobsParsesSerializedQueue(): void
	{
		$now = 1_700_000_100;
		$queue = serialize([
			[1, 12, 60, 1_700_000_050, 'build'],
			[2, 8, 60, 1_700_000_200, 'build'],
			[14, 3, 60, 1_700_000_010, 'destroy'],
			'bad',
		]);

		$jobs = BuildingCompletePushService::dueConstructionJobs($queue, $now);

		$this->assertCount(1, $jobs);
		$this->assertSame(1, $jobs[0]['elementId']);
		$this->assertSame(12, $jobs[0]['level']);
		$this->assertSame(1_700_000_050, $jobs[0]['buildEnd']);
	}

	public function testDueConstructionJobsAcceptsArrayAndSkipsInvalid(): void
	{
		$now = 100;
		$jobs = BuildingCompletePushService::dueConstructionJobs([
			[1, 1, 10, 50, 'build'],
			[1, 0, 10, 50, 'build'],
			[200, 1, 10, 50, 'build'],
			[1, 1, 10, 0, 'build'],
			[],
		], $now);

		$this->assertCount(1, $jobs);
		$this->assertSame(1, $jobs[0]['elementId']);
	}

	public function testDueConstructionJobsReturnsEmptyForGarbage(): void
	{
		$this->assertSame([], BuildingCompletePushService::dueConstructionJobs('', 1));
		$this->assertSame([], BuildingCompletePushService::dueConstructionJobs(null, 1));
		$this->assertSame([], BuildingCompletePushService::dueConstructionJobs('not-serialized', 1));
	}

	public function testJobKeyIsStable(): void
	{
		$this->assertSame('9:1:12:100', BuildingCompletePushService::jobKey(9, 1, 12, 100));
	}

	public function testBuildingCompleteMessageUsesLanguageAndDeepLink(): void
	{
		$message = BuildingCompletePushService::buildingCompleteMessage('Ore Extractor', 12, 'Homeworld', [
			'push_building_title' => 'Building complete',
			'push_building_body'  => '%s (level %d) finished on %s',
		]);

		$this->assertSame('Building complete', $message['title']);
		$this->assertSame('Ore Extractor (level 12) finished on Homeworld', $message['body']);
		$this->assertSame('game.php?page=buildings', $message['data']['url']);
		$this->assertSame('building_complete', $message['data']['type']);
		$this->assertSame(1, $message['data']['count']);
	}

	public function testBuildingCompleteDigestMergesMultipleJobs(): void
	{
		$message = BuildingCompletePushService::buildingCompleteDigest([
			['name' => 'Ore Extractor', 'level' => 12, 'planetName' => 'Homeworld'],
			['name' => 'Silicon Synthesizer', 'level' => 8, 'planetName' => 'Colony'],
			['name' => 'Research Lab', 'level' => 3, 'planetName' => 'Homeworld'],
		], [
			'push_building_title'     => 'Building complete',
			'push_building_body_many' => '%s (level %d) on %s and %d more finished',
		]);

		$this->assertSame('Ore Extractor (level 12) on Homeworld and 2 more finished', $message['body']);
		$this->assertSame(3, $message['data']['count']);
	}

	public function testBuildingCompleteMessageFallsBackWithoutLng(): void
	{
		$previous = $GLOBALS['LNG'] ?? null;
		unset($GLOBALS['LNG']);
		try {
			$message = BuildingCompletePushService::buildingCompleteMessage('Lab', 2, 'Moon');
			$this->assertStringContainsString('Lab', $message['body']);
			$this->assertStringContainsString('Moon', $message['body']);
		} finally {
			if ($previous === null) {
				unset($GLOBALS['LNG']);
			} else {
				$GLOBALS['LNG'] = $previous;
			}
		}
	}

	public function testNotifyJobSkippedWhenNotConfigured(): void
	{
		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function () use (&$sent): void {
				$sent[] = func_get_args();
			},
			configured: false,
		);

		$this->assertFalse($service->notifyJob(1, 2, 'Home', 1, 5, 100, 'en'));
		$this->assertSame([], $sent);
		$this->assertSame([], $this->db->inserts);
	}

	public function testNotifyJobSkippedForInvalidIds(): void
	{
		$service = new BuildingCompletePushService(configured: true);
		$this->assertFalse($service->notifyJob(0, 2, 'Home', 1, 5, 100));
		$this->assertFalse($service->notifyJob(1, 0, 'Home', 1, 5, 100));
		$this->assertFalse($service->notifyJob(1, 2, 'Home', 0, 5, 100));
		$this->assertFalse($service->notifyJob(1, 2, 'Home', 1, 0, 100));
		$this->assertFalse($service->notifyJob(1, 2, 'Home', 1, 5, 0));
		$this->assertFalse($service->notifyJob(1, 2, 'Home', 200, 5, 100));
	}

	public function testNotifyJobSendsOnceAndDedups(): void
	{
		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'title', 'body', 'data');
			},
			configured: true,
			languageLoader: static fn (string $lang): array => [
				'push_building_title' => 'Building complete',
				'push_building_body'  => '%s (level %d) finished on %s',
				'tech'                => [1 => 'Ore Extractor'],
			],
		);

		$this->assertTrue($service->notifyJob(42, 9, 'Homeworld', 1, 12, 500, 'en'));
		$this->assertFalse($service->notifyJob(42, 9, 'Homeworld', 1, 12, 500, 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame(42, $sent[0]['userId']);
		$this->assertSame('Ore Extractor (level 12) finished on Homeworld', $sent[0]['body']);
		$this->assertSame('game.php?page=buildings', $sent[0]['data']['url']);
		$this->assertCount(1, $this->db->inserts);
	}

	public function testNotifyJobsMergesMultipleJobsIntoOnePush(): void
	{
		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'title', 'body', 'data');
			},
			configured: true,
			languageLoader: static fn (string $lang): array => [
				'push_building_title'     => 'Building complete',
				'push_building_body'      => '%s (level %d) finished on %s',
				'push_building_body_many' => '%s (level %d) on %s and %d more finished',
				'tech'                    => [1 => 'Ore Extractor', 2 => 'Silicon Synthesizer'],
			],
		);

		$this->assertSame(1, $service->notifyJobs(7, [
			['planetId' => 3, 'planetName' => 'Homeworld', 'elementId' => 1, 'level' => 10, 'buildEnd' => 100],
			['planetId' => 3, 'planetName' => 'Homeworld', 'elementId' => 2, 'level' => 4, 'buildEnd' => 110],
		], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame('Ore Extractor (level 10) on Homeworld and 1 more finished', $sent[0]['body']);
		$this->assertSame(2, $sent[0]['data']['count']);
		$this->assertCount(2, $this->db->inserts);
	}

	public function testNotifyJobUsesFallbackBuildingName(): void
	{
		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = $body;
			},
			configured: true,
			languageLoader: static fn (): array => [
				'push_building_body' => '%s (level %d) finished on %s',
			],
		);

		$this->assertTrue($service->notifyJob(1, 2, 'Alpha', 6, 3, 10, 'en'));
		$this->assertSame('Building #6 (level 3) finished on Alpha', $sent[0]);
	}

	public function testNotifyJobSwallowsDatabaseErrors(): void
	{
		$throwing = new class extends BuildingCompletePushDatabaseStub {
			public function selectSingle($qry, array $params = [], $field = false)
			{
				throw new RuntimeException('db down');
			}
		};
		$this->swapDatabaseInstance($throwing);

		$service = new BuildingCompletePushService(configured: true);
		$this->assertFalse($service->notifyJob(1, 2, 'Home', 1, 5, 100));
	}

	public function testStaticNotifyCompletedJobNoopsWhenNotConfigured(): void
	{
		$this->assertFalse(BuildingCompletePushService::notifyCompletedJob(1, 2, 'Home', 1, 5, 100));
	}

	public function testIsConfiguredDefersToPushServiceWhenUnset(): void
	{
		$service = new BuildingCompletePushService();
		$this->assertFalse($service->isConfigured());
	}

	public function testNotifyJobDefaultNotifierDoesNotMarkSentWhenPushServiceNoops(): void
	{
		$service = new BuildingCompletePushService(configured: true);
		$this->assertFalse($service->notifyJob(1, 2, 'Home', 1, 5, 100, 'en'));
		$this->assertSame(0, $this->db->notified['2:1:5:100']['notified_at'] ?? 1);
	}

	public function testNotifyJobsDoesNotMarkSentWhenDeliveryFailsAndRetries(): void
	{
		$failing = new BuildingCompletePushService(
			notifier: static fn (): bool => false,
			configured: true,
			languageLoader: static fn (): array => ['tech' => [1 => 'Ore Extractor']],
		);
		$this->assertSame(0, $failing->notifyJobs(7, [[
			'planetId'   => 3,
			'planetName' => 'Home',
			'elementId'  => 1,
			'level'      => 10,
			'buildEnd'   => 100,
		]], 'en'));
		$this->assertSame(0, $this->db->notified['3:1:10:100']['notified_at']);

		$sent = [];
		$retry = new BuildingCompletePushService(
			notifier: static function () use (&$sent): void {
				$sent[] = 1;
			},
			configured: true,
			languageLoader: static fn (): array => [
				'push_building_body' => '%s (level %d) finished on %s',
				'tech'               => [1 => 'Ore Extractor'],
			],
		);
		$this->assertSame(1, $retry->notifyJobs(7, [[
			'planetId'   => 3,
			'planetName' => 'Home',
			'elementId'  => 1,
			'level'      => 10,
			'buildEnd'   => 100,
		]], 'en'));
		$this->assertCount(1, $sent);
		$this->assertGreaterThan(0, $this->db->notified['3:1:10:100']['notified_at']);
	}

	public function testRunRetriesPendingAfterQueueWasCleared(): void
	{
		$this->db->notified['3:1:10:100'] = [
			'planet_id'   => 3,
			'element_id'  => 1,
			'level'       => 10,
			'build_end'   => 100,
			'notified_at' => 0,
		];
		$this->db->planets[] = [
			'planet_id'        => 3,
			'planet_name'      => 'Homeworld',
			'b_building_id'    => '',
			'b_building'       => 0,
			'user_id'          => 7,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];

		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId) use (&$sent): void {
				$sent[] = $userId;
			},
			configured: true,
			languageLoader: static fn (): array => ['tech' => [1 => 'Ore Extractor']],
		);

		$this->assertSame(1, $service->run(1_700_000_200));
		$this->assertSame([7], $sent);
		$this->assertGreaterThan(0, $this->db->notified['3:1:10:100']['notified_at']);
	}

	public function testLanguageLoaderNonArrayUsesFallbackName(): void
	{
		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = $body;
			},
			configured: true,
			languageLoader: static fn (): string => 'nope',
		);

		$this->assertTrue($service->notifyJob(1, 2, 'Home', 4, 2, 10, 'en'));
		$this->assertSame('Building #4 (level 2) finished on Home', $sent[0]);
	}

	public function testRunUsesTimestampWhenNowOmitted(): void
	{
		$service = new BuildingCompletePushService(configured: true);
		$this->assertSame(0, $service->run());
	}

	public function testRunSkipsWhenNotConfigured(): void
	{
		$service = new BuildingCompletePushService(configured: false);
		$this->assertSame(0, $service->run(1_700_000_000));
	}

	public function testRunNotifiesDueJobsAndCleansOldRows(): void
	{
		$now = 1_700_000_200;
		$this->db->notified['1:1:1:1'] = [
			'planet_id' => 1,
			'element_id' => 1,
			'level' => 1,
			'build_end' => 1,
			'notified_at' => 1,
		];
		$this->db->planets[] = [
			'planet_id'        => 3,
			'planet_name'      => 'Colony',
			'b_building_id'    => serialize([
				[1, 10, 60, $now - 50, 'build'],
				[2, 4, 60, $now + 50, 'build'],
			]),
			'b_building'       => $now - 50,
			'user_id'          => 7,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];
		$this->db->planets[] = [
			'planet_id'        => 4,
			'planet_name'      => 'Muted',
			'b_building_id'    => serialize([[1, 2, 60, $now - 10, 'build']]),
			'b_building'       => $now - 10,
			'user_id'          => 8,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 0,
		];

		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId) use (&$sent): void {
				$sent[] = $userId;
			},
			configured: true,
			languageLoader: static fn (): array => [
				'tech' => [1 => 'Ore Extractor'],
			],
		);

		$this->assertSame(1, $service->run($now));
		$this->assertSame([7], $sent);
		$this->assertNotEmpty($this->db->deletes);
		$this->assertArrayNotHasKey('1:1:1:1', $this->db->notified);
	}

	public function testRunMergesDueJobsAcrossPlanetsForOneUser(): void
	{
		$now = 1_700_000_200;
		$this->db->planets[] = [
			'planet_id'        => 3,
			'planet_name'      => 'Homeworld',
			'b_building_id'    => serialize([[1, 10, 60, $now - 50, 'build']]),
			'b_building'       => $now - 50,
			'user_id'          => 7,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];
		$this->db->planets[] = [
			'planet_id'        => 5,
			'planet_name'      => 'Colony',
			'b_building_id'    => serialize([[2, 4, 60, $now - 10, 'build']]),
			'b_building'       => $now - 10,
			'user_id'          => 7,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];

		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'body', 'data');
			},
			configured: true,
			languageLoader: static fn (): array => [
				'push_building_body_many' => '%s (level %d) on %s and %d more finished',
				'tech'                    => [1 => 'Ore Extractor', 2 => 'Silicon Synthesizer'],
			],
		);

		$this->assertSame(1, $service->run($now));
		$this->assertCount(1, $sent);
		$this->assertSame(7, $sent[0]['userId']);
		$this->assertSame(2, $sent[0]['data']['count']);
	}

	public function testRunDoesNotResendWhenCompletionIsOlderThanCleanupWindow(): void
	{
		$now = 1_700_000_200;
		$buildEnd = $now - (8 * 86400);
		$this->db->planets[] = [
			'planet_id'        => 3,
			'planet_name'      => 'Colony',
			'b_building_id'    => serialize([[1, 10, 60, $buildEnd, 'build']]),
			'b_building'       => $buildEnd,
			'user_id'          => 7,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];

		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId) use (&$sent): void {
				$sent[] = $userId;
			},
			configured: true,
			languageLoader: static fn (): array => ['tech' => [1 => 'Ore Extractor']],
		);

		$this->assertSame(1, $service->run($now));
		$this->assertSame(0, $service->run($now));
		$this->assertCount(1, $sent);
	}

	public function testCurrentDueConstructionJobsIgnoresLaterQueuedBuilds(): void
	{
		$now = 1_700_000_200;
		$jobs = BuildingCompletePushService::currentDueConstructionJobs([
			[1, 10, 60, $now - 50, 'build'],
			[2, 4, 60, $now - 10, 'build'],
		], $now);

		$this->assertCount(1, $jobs);
		$this->assertSame(1, $jobs[0]['elementId']);
	}

	public function testRunReturnsZeroWhenNoCandidates(): void
	{
		$service = new BuildingCompletePushService(configured: true);
		$this->assertSame(0, $service->run(1_700_000_000));
		$this->assertNotEmpty($this->db->deletes);
	}

	public function testRunSwallowsUnexpectedFailures(): void
	{
		$throwing = new class extends BuildingCompletePushDatabaseStub {
			public function select($qry, array $params = [])
			{
				throw new RuntimeException('boom');
			}
		};
		$this->swapDatabaseInstance($throwing);

		$service = new BuildingCompletePushService(configured: true);
		$this->assertSame(0, $service->run(1));
	}

	public function testLoadLanguageReadsRepoFiles(): void
	{
		$sent = [];
		$service = new BuildingCompletePushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = [$title, $body];
			},
			configured: true,
		);

		$this->assertTrue($service->notifyJob(1, 2, 'Homeworld', 1, 4, 99, 'en'));
		$this->assertSame('Building complete', $sent[0][0]);
		$this->assertStringContainsString('Ore Extractor', $sent[0][1]);
		$this->assertStringContainsString('Homeworld', $sent[0][1]);
	}
}
