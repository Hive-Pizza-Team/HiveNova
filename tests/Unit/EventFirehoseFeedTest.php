<?php

declare(strict_types=1);

use HiveNova\Core\EventFirehoseFeed;
use HiveNova\Core\EventFirehoseWriter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class EventFirehoseFeedTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	/** @var array<string, string> */
	private array $lng;

	protected function setUp(): void
	{
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
		$this->lng = [
			'php_tdformat' => 'Y-m-d',
			'ef_event_battle' => 'Battle',
			'ef_size_small' => 'Skirmish',
			'ef_size_medium' => 'Clash',
			'ef_size_large' => 'Major battle',
			'ef_outcome_attacker' => 'Attackers prevailed',
			'ef_outcome_defender' => 'Defenders held',
			'ef_outcome_draw' => 'Stalemate',
		];
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_json_keys_are_allowlisted(): void
	{
		$presented = EventFirehoseFeed::present([
			'id' => 3,
			'time' => 1_700_000_000,
			'event_type' => 'battle',
			'size_bucket' => 'small',
			'outcome' => 'draw',
			'rid' => 'leak',
			'username' => 'alice',
			'galaxy' => 1,
		], $this->lng, 'UTC');

		$this->assertSame(EventFirehoseFeed::JSON_KEYS, array_keys($presented));
		$this->assertArrayNotHasKey('rid', $presented);
		$this->assertArrayNotHasKey('username', $presented);
		$this->assertArrayNotHasKey('galaxy', $presented);
		$this->assertArrayNotHasKey('units', $presented);
	}

	public function test_universe_isolation_and_since_id(): void
	{
		EventFirehoseWriter::record(1, 100, 10, 'a');
		EventFirehoseWriter::record(2, 100, 10, 'r');
		EventFirehoseWriter::record(1, 101, 10, 'w');

		$uni1 = EventFirehoseFeed::fetch(1, $this->lng, 'UTC');
		$this->assertCount(2, $uni1);

		$newer = EventFirehoseFeed::fetch(1, $this->lng, 'UTC', (int) $uni1[1]['id']);
		$this->assertCount(1, $newer);
		$this->assertSame($uni1[0]['id'], $newer[0]['id']);
	}

	public function test_empty_feed_and_limit(): void
	{
		$this->assertSame([], EventFirehoseFeed::fetch(1, $this->lng, 'UTC'));

		for ($i = 0; $i < 60; $i++) {
			EventFirehoseWriter::record(1, 100 + $i, 10, 'a');
		}
		$rows = EventFirehoseFeed::fetch(1, $this->lng, 'UTC');
		$this->assertCount(50, $rows);
	}

	public function test_since_id_returns_oldest_new_events_first(): void
	{
		for ($i = 0; $i < 70; $i++) {
			EventFirehoseWriter::record(1, 100 + $i, 10, 'a');
		}

		$caughtUp = EventFirehoseFeed::fetch(1, $this->lng, 'UTC', 5);
		$this->assertCount(50, $caughtUp);
		$this->assertSame(55, $caughtUp[0]['id']);
		$this->assertSame(6, $caughtUp[49]['id']);
	}
}
