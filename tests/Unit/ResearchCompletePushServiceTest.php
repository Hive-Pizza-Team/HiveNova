<?php

use HiveNova\Core\ResearchCompletePushService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/ResearchCompletePushDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class ResearchCompletePushServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private ResearchCompletePushDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new ResearchCompletePushDatabaseStub();
		$this->swapDatabaseInstance($this->db);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testDueResearchJobsParsesSerializedQueue(): void
	{
		$now = 1_700_000_100;
		$queue = serialize([
			[113, 8, 60, 1_700_000_050, 1],
			[115, 4, 60, 1_700_000_200, 1],
			[1, 3, 60, 1_700_000_010, 1],
			'bad',
		]);

		$jobs = ResearchCompletePushService::dueResearchJobs($queue, $now);

		$this->assertCount(1, $jobs);
		$this->assertSame(113, $jobs[0]['elementId']);
		$this->assertSame(8, $jobs[0]['level']);
		$this->assertSame(1_700_000_050, $jobs[0]['techEnd']);
	}

	public function testDueResearchJobsAcceptsArrayAndSkipsInvalid(): void
	{
		$now = 100;
		$jobs = ResearchCompletePushService::dueResearchJobs([
			[113, 1, 10, 50, 1],
			[113, 0, 10, 50, 1],
			[14, 1, 10, 50, 1],
			[113, 1, 10, 0, 1],
			[],
		], $now);

		$this->assertCount(1, $jobs);
		$this->assertSame(113, $jobs[0]['elementId']);
	}

	public function testDueResearchJobsReturnsEmptyForGarbage(): void
	{
		$this->assertSame([], ResearchCompletePushService::dueResearchJobs('', 1));
		$this->assertSame([], ResearchCompletePushService::dueResearchJobs(null, 1));
		$this->assertSame([], ResearchCompletePushService::dueResearchJobs('not-serialized', 1));
	}

	public function testJobKeyIsStable(): void
	{
		$this->assertSame('9:113:8:100', ResearchCompletePushService::jobKey(9, 113, 8, 100));
	}

	public function testResearchCompleteMessageUsesSingleTemplate(): void
	{
		$message = ResearchCompletePushService::researchCompleteMessage([
			['name' => 'Energy Technology', 'level' => 8],
		], [
			'push_research_title' => 'Research complete',
			'push_research_body'  => '%s (level %d) finished',
		]);

		$this->assertSame('Research complete', $message['title']);
		$this->assertSame('Energy Technology (level 8) finished', $message['body']);
		$this->assertSame('game.php?page=research', $message['data']['url']);
		$this->assertSame('research_complete', $message['data']['type']);
		$this->assertSame(1, $message['data']['count']);
	}

	public function testResearchCompleteMessageMergesMultipleJobs(): void
	{
		$message = ResearchCompletePushService::researchCompleteMessage([
			['name' => 'Energy Technology', 'level' => 8],
			['name' => 'Combustion Engine', 'level' => 4],
			['name' => 'Computer Technology', 'level' => 10],
		], [
			'push_research_title'     => 'Research complete',
			'push_research_body_many' => '%s (level %d) and %d more finished',
		]);

		$this->assertSame('Energy Technology (level 8) and 2 more finished', $message['body']);
		$this->assertSame(3, $message['data']['count']);
	}

	public function testResearchCompleteMessageFallsBackWithoutLng(): void
	{
		$previous = $GLOBALS['LNG'] ?? null;
		unset($GLOBALS['LNG']);
		try {
			$message = ResearchCompletePushService::researchCompleteMessage([
				['name' => 'Spy Technology', 'level' => 2],
			]);
			$this->assertStringContainsString('Spy Technology', $message['body']);
		} finally {
			if ($previous === null) {
				unset($GLOBALS['LNG']);
			} else {
				$GLOBALS['LNG'] = $previous;
			}
		}
	}

	public function testNotifyJobsSkippedWhenNotConfigured(): void
	{
		$sent = [];
		$service = new ResearchCompletePushService(
			notifier: static function () use (&$sent): void {
				$sent[] = func_get_args();
			},
			configured: false,
		);

		$this->assertSame(0, $service->notifyJobs(1, [[
			'elementId' => 113,
			'level'     => 5,
			'techEnd'   => 100,
		]], 'en'));
		$this->assertSame([], $sent);
		$this->assertSame([], $this->db->inserts);
	}

	public function testNotifyJobsSkippedForInvalidIds(): void
	{
		$service = new ResearchCompletePushService(configured: true);
		$this->assertSame(0, $service->notifyJobs(0, [[
			'elementId' => 113,
			'level'     => 5,
			'techEnd'   => 100,
		]]));
		$this->assertSame(0, $service->notifyJobs(1, [[
			'elementId' => 1,
			'level'     => 5,
			'techEnd'   => 100,
		]]));
		$this->assertSame(0, $service->notifyJobs(1, [[
			'elementId' => 113,
			'level'     => 0,
			'techEnd'   => 100,
		]]));
		$this->assertSame(0, $service->notifyJobs(1, [[
			'elementId' => 113,
			'level'     => 5,
			'techEnd'   => 0,
		]]));
	}

	public function testNotifyJobsSendsOnceAndDedups(): void
	{
		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyJobs(42, [[
			'elementId' => 113,
			'level'     => 8,
			'techEnd'   => 500,
		]], 'en'));
		$this->assertSame(0, $service->notifyJobs(42, [[
			'elementId' => 113,
			'level'     => 8,
			'techEnd'   => 500,
		]], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame(42, $sent[0]['userId']);
		$this->assertSame('Energy Technology (level 8) finished', $sent[0]['body']);
		$this->assertSame('game.php?page=research', $sent[0]['data']['url']);
		$this->assertCount(1, $this->db->inserts);
	}

	public function testNotifyJobsDedupsIdenticalJobsInTheSameWindow(): void
	{
		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyJobs(7, [
			['elementId' => 113, 'level' => 8, 'techEnd' => 100],
			['elementId' => 113, 'level' => 8, 'techEnd' => 100],
		], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame('Energy Technology (level 8) finished', $sent[0]['body']);
		$this->assertSame(1, $sent[0]['data']['count']);
		$this->assertCount(1, $this->db->inserts);
	}

	public function testNotifyJobsMergesMultipleJobsIntoOnePush(): void
	{
		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyJobs(7, [
			['elementId' => 113, 'level' => 8, 'techEnd' => 100],
			['elementId' => 115, 'level' => 4, 'techEnd' => 110],
			['elementId' => 108, 'level' => 10, 'techEnd' => 120],
		], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame('Energy Technology (level 8) and 2 more finished', $sent[0]['body']);
		$this->assertSame(3, $sent[0]['data']['count']);
		$this->assertCount(3, $this->db->inserts);
	}

	public function testNotifyJobsIgnoresAlreadyNotifiedJobsWhenMerging(): void
	{
		$this->db->notified['7:113:8:100'] = [
			'user_id'    => 7,
			'element_id' => 113,
			'level'      => 8,
			'tech_end'   => 100,
		];

		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->notifyJobs(7, [
			['elementId' => 113, 'level' => 8, 'techEnd' => 100],
			['elementId' => 115, 'level' => 4, 'techEnd' => 110],
		], 'en'));

		$this->assertCount(1, $sent);
		$this->assertSame('Combustion Engine (level 4) finished', $sent[0]['body']);
		$this->assertSame(1, $sent[0]['data']['count']);
	}

	public function testNotifyJobUsesFallbackResearchName(): void
	{
		$sent = [];
		$service = new ResearchCompletePushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = $body;
			},
			configured: true,
			languageLoader: static fn (): array => [
				'push_research_body' => '%s (level %d) finished',
			],
		);

		$this->assertSame(1, $service->notifyJobs(1, [[
			'elementId' => 124,
			'level'     => 3,
			'techEnd'   => 10,
		]], 'en'));
		$this->assertSame('Research #124 (level 3) finished', $sent[0]);
	}

	public function testNotifyJobsSwallowsDatabaseErrors(): void
	{
		$throwing = new class extends ResearchCompletePushDatabaseStub {
			public function selectSingle($qry, array $params = [], $field = false)
			{
				throw new RuntimeException('db down');
			}
		};
		$this->swapDatabaseInstance($throwing);

		$service = new ResearchCompletePushService(configured: true);
		$this->assertSame(0, $service->notifyJobs(1, [[
			'elementId' => 113,
			'level'     => 5,
			'techEnd'   => 100,
		]]));
	}

	public function testStaticNotifyCompletedJobNoopsWhenNotConfigured(): void
	{
		$this->assertSame(0, ResearchCompletePushService::notifyCompletedJob(1, 113, 5, 100));
	}

	public function testIsConfiguredDefersToPushServiceWhenUnset(): void
	{
		$service = new ResearchCompletePushService();
		$this->assertFalse($service->isConfigured());
	}

	public function testNotifyJobsDefaultNotifierDoesNotThrow(): void
	{
		$service = new ResearchCompletePushService(configured: true);
		$this->assertSame(1, $service->notifyJobs(1, [[
			'elementId' => 113,
			'level'     => 5,
			'techEnd'   => 100,
		]], 'en'));
	}

	public function testLanguageLoaderNonArrayUsesFallbackName(): void
	{
		$sent = [];
		$service = new ResearchCompletePushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = $body;
			},
			configured: true,
			languageLoader: static fn (): string => 'nope',
		);

		$this->assertSame(1, $service->notifyJobs(1, [[
			'elementId' => 106,
			'level'     => 2,
			'techEnd'   => 10,
		]], 'en'));
		$this->assertSame('Research #106 (level 2) finished', $sent[0]);
	}

	public function testRunUsesTimestampWhenNowOmitted(): void
	{
		$service = new ResearchCompletePushService(configured: true);
		$this->assertSame(0, $service->run());
	}

	public function testRunSkipsWhenNotConfigured(): void
	{
		$service = new ResearchCompletePushService(configured: false);
		$this->assertSame(0, $service->run(1_700_000_000));
	}

	public function testRunMergesDueJobsForOneUserAndCleansOldRows(): void
	{
		$now = 1_700_000_200;
		$this->db->notified['1:113:1:1'] = [
			'user_id'    => 1,
			'element_id' => 113,
			'level'      => 1,
			'tech_end'   => 1,
		];
		$this->db->users[] = [
			'user_id'          => 7,
			'b_tech_queue'     => serialize([
				[113, 10, 60, $now - 50, 1],
				[115, 4, 60, $now - 10, 1],
				[108, 2, 60, $now + 50, 1],
			]),
			'b_tech'           => $now - 50,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];
		$this->db->users[] = [
			'user_id'          => 8,
			'b_tech_queue'     => serialize([[113, 2, 60, $now - 10, 1]]),
			'b_tech'           => $now - 10,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 0,
		];

		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(1, $service->run($now));
		$this->assertCount(1, $sent);
		$this->assertSame(7, $sent[0]['userId']);
		$this->assertSame(2, $sent[0]['data']['count']);
		$this->assertNotEmpty($this->db->deletes);
		$this->assertArrayNotHasKey('1:113:1:1', $this->db->notified);
	}

	public function testRunSendsSeparateDigestsPerUser(): void
	{
		$now = 1_700_000_200;
		$this->db->users[] = [
			'user_id'          => 7,
			'b_tech_queue'     => serialize([[113, 2, 60, $now - 10, 1]]),
			'b_tech'           => $now - 10,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];
		$this->db->users[] = [
			'user_id'          => 9,
			'b_tech_queue'     => serialize([[115, 3, 60, $now - 5, 1]]),
			'b_tech'           => $now - 5,
			'lang'             => 'en',
			'has_subscription' => true,
			'settings_push'    => 1,
		];

		$sent = [];
		$service = $this->serviceCollecting($sent);

		$this->assertSame(2, $service->run($now));
		$this->assertSame([7, 9], array_column($sent, 'userId'));
	}

	public function testRunReturnsZeroWhenNoCandidates(): void
	{
		$service = new ResearchCompletePushService(configured: true);
		$this->assertSame(0, $service->run(1_700_000_000));
		$this->assertNotEmpty($this->db->deletes);
	}

	public function testRunSwallowsUnexpectedFailures(): void
	{
		$throwing = new class extends ResearchCompletePushDatabaseStub {
			public function select($qry, array $params = [])
			{
				throw new RuntimeException('boom');
			}
		};
		$this->swapDatabaseInstance($throwing);

		$service = new ResearchCompletePushService(configured: true);
		$this->assertSame(0, $service->run(1));
	}

	public function testLoadLanguageReadsRepoFiles(): void
	{
		$sent = [];
		$service = new ResearchCompletePushService(
			notifier: static function (int $userId, string $title, string $body) use (&$sent): void {
				$sent[] = [$title, $body];
			},
			configured: true,
		);

		$this->assertSame(1, $service->notifyJobs(1, [[
			'elementId' => 113,
			'level'     => 4,
			'techEnd'   => 99,
		]], 'en'));
		$this->assertSame('Research complete', $sent[0][0]);
		$this->assertStringContainsString('Energy Technology', $sent[0][1]);
	}

	/**
	 * @param list<array{userId: int, title: string, body: string, data: array<string, mixed>}> $sent
	 */
	private function serviceCollecting(array &$sent): ResearchCompletePushService
	{
		return new ResearchCompletePushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): void {
				$sent[] = compact('userId', 'title', 'body', 'data');
			},
			configured: true,
			languageLoader: static fn (string $lang): array => [
				'push_research_title'     => 'Research complete',
				'push_research_body'      => '%s (level %d) finished',
				'push_research_body_many' => '%s (level %d) and %d more finished',
				'tech'                    => [
					113 => 'Energy Technology',
					115 => 'Combustion Engine',
					108 => 'Computer Technology',
				],
			],
		);
	}
}
