<?php

use HiveNova\Core\BotDetectionService;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\BotDetectionCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/BotDetectionFakeDatabase.php';

/**
 * Source-inspection and runtime tests for BotDetectionCronjob.
 */
class BotDetectionCronjobTest extends TestCase
{
	use SwapDatabaseInstance;

	private string $source;

	private BotDetectionFakeDatabase $fake;

	protected function setUp(): void
	{
		$this->source = file_get_contents(
			__DIR__ . '/../../includes/classes/cronjob/BotDetectionCronjob.php'
		);

		if (!defined('AUTH_ADM')) {
			define('AUTH_ADM', 3);
		}
		if (!defined('AUTH_USR')) {
			define('AUTH_USR', 0);
		}
		if (!defined('ROOT_UNI')) {
			define('ROOT_UNI', 1);
		}

		$this->fake = new BotDetectionFakeDatabase();
		$this->swapDatabaseInstance($this->fake);

		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue([1]);
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null);

		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	private function seedContinuousEvents(
		int $userId,
		string $username,
		int $count,
		int $gapSeconds,
		?int $longGapAfterIndex = null,
		int $longGapSeconds = 0,
	): void {
		$cutoff = BotDetectionService::cutoffTimestamp();
		$time   = $cutoff + 60;
		for ($i = 0; $i < $count; $i++) {
			$this->fake->botDetectionEvents[] = [
				'user_id'    => $userId,
				'username'   => $username,
				'event_time' => $time,
				'source'     => 'fleet',
			];
			if ($longGapAfterIndex !== null && $i === $longGapAfterIndex) {
				$time += $longGapSeconds;
			} else {
				$time += $gapSeconds;
			}
		}
	}

	public function test_run_does_nothing_when_no_events(): void
	{
		(new BotDetectionCronjob())->run();

		$this->assertSame([], $this->fake->achievement->messages);
	}

	public function test_run_skips_users_with_fewer_than_min_actions(): void
	{
		$this->seedContinuousEvents(42, 'CasualPlayer', BotDetectionService::MIN_ACTIONS - 1, 7140);
		$this->fake->adminUsers = [['id' => 99]];

		(new BotDetectionCronjob())->run();

		$this->assertSame([], $this->fake->achievement->messages);
	}

	public function test_run_skips_users_with_natural_sleep_break(): void
	{
		$this->seedContinuousEvents(
			42,
			'Sleeper',
			85,
			7140,
			40,
			BotDetectionService::SLEEP_THRESHOLD
		);
		$this->fake->adminUsers = [['id' => 99]];

		(new BotDetectionCronjob())->run();

		$this->assertSame([], $this->fake->achievement->messages);
	}

	public function test_run_sends_bot_report_to_admins(): void
	{
		$this->seedContinuousEvents(42, 'BotSuspect', 85, 7140);
		$this->fake->adminUsers = [
			['id' => 99],
			['id' => 100],
		];

		(new BotDetectionCronjob())->run();

		$this->assertCount(2, $this->fake->achievement->messages);

		$recipientIds = array_map(
			static fn (array $row): int => (int) $row[':userId'],
			$this->fake->achievement->messages
		);
		$this->assertSame([99, 100], $recipientIds);

		$message = $this->fake->achievement->messages[0];
		$this->assertSame('Bot Detection Report', $message[':subject']);
		$this->assertSame('Game Master', $message[':from']);
		$this->assertSame(1, $message[':unread']);
		$this->assertStringContainsString('BotSuspect', $message[':text']);
		$this->assertStringContainsString(
			'no natural sleep break in the last ' . BotDetectionService::DAYS_WINDOW . ' days',
			$message[':text']
		);
	}

	public function test_run_skips_duplicate_digest(): void
	{
		$this->seedContinuousEvents(42, 'BotSuspect', 85, 7140);
		$this->fake->adminUsers = [['id' => 99]];

		(new BotDetectionCronjob())->run();
		$this->assertCount(1, $this->fake->achievement->messages);

		(new BotDetectionCronjob())->run();
		$this->assertCount(1, $this->fake->achievement->messages);
	}

	public function test_run_processes_each_available_universe(): void
	{
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue([1, 2]);

		$this->seedContinuousEvents(42, 'UniOneBot', 85, 7140);
		$this->fake->adminUsers = [['id' => 99]];

		(new BotDetectionCronjob())->run();

		$this->assertCount(2, $this->fake->achievement->messages);
		$this->assertSame(99, $this->fake->achievement->messages[0][':userId']);
		$this->assertSame(99, $this->fake->achievement->messages[1][':userId']);
	}

	public function testSleepThresholdIs7200(): void
	{
		$this->assertSame(7200, BotDetectionCronjob::SLEEP_THRESHOLD);
	}

	public function testDaysWindowIs7(): void
	{
		$this->assertSame(7, BotDetectionCronjob::DAYS_WINDOW);
	}

	public function testMinActionsIs10(): void
	{
		$this->assertSame(10, BotDetectionCronjob::MIN_ACTIONS);
	}

	public function testDelegatesToBotDetectionService(): void
	{
		$this->assertStringContainsString('BotDetectionService', $this->source);
		$this->assertStringContainsString('findSuspects', $this->source);
		$this->assertStringContainsString('shouldNotify', $this->source);
		$this->assertStringContainsString('markNotified', $this->source);
	}

	public function testReportsAreSentToAdminsOnly(): void
	{
		$this->assertStringContainsString(
			'adminRecipientIds',
			$this->source,
			'Bot detection reports must be sent to admin-level users (AUTH_ADM)'
		);
	}

	public function testMessageSubject(): void
	{
		$this->assertStringContainsString(
			'Bot Detection Report',
			$this->source,
			"Message subject must be 'Bot Detection Report'"
		);
	}

	public function testMessageSenderIsGameMaster(): void
	{
		$this->assertStringContainsString(
			'Game Master',
			$this->source,
			"Message sender name must be 'Game Master'"
		);
	}

	public function testSendsMessageViaPlayerUtil(): void
	{
		$this->assertStringContainsString(
			'PlayerUtil::sendMessage(',
			$this->source,
			'Messages must be sent via PlayerUtil::sendMessage()'
		);
	}

	public function testMessageUnreadFlagIsSet(): void
	{
		$this->assertMatchesRegularExpression(
			'/PlayerUtil::sendMessage\([^;]+,\s*1\s*,\s*\$uni\s*\)/s',
			$this->source,
			'sendMessage must pass unread=1 as the second-to-last argument'
		);
	}
}
