<?php

declare(strict_types=1);

use HiveNova\Core\EventFirehoseWriter;
use HiveNova\Core\LobbyActivityFeed;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class LobbyActivityFeedTest extends TestCase
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
			'ef_event_moon' => 'Moon',
			'ef_size_small' => 'Skirmish',
			'ef_size_medium' => 'Clash',
			'ef_size_large' => 'Major battle',
			'ef_size_moon_small' => 'Small',
			'ef_size_moon_medium' => 'Medium',
			'ef_size_moon_large' => 'Large',
			'ef_outcome_attacker' => 'Attackers prevailed',
			'ef_outcome_defender' => 'Defenders held',
			'ef_outcome_draw' => 'Stalemate',
			'ef_outcome_formed' => 'Formed',
		];
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_present_includes_universe_and_ts(): void
	{
		$presented = LobbyActivityFeed::present([
			'id' => 9,
			'universe' => 3,
			'time' => 1_700_000_000,
			'event_type' => 'battle',
			'size_bucket' => 'large',
			'outcome' => 'attacker',
		], $this->lng, 'UTC', [3 => 'Season Arena']);

		$this->assertSame(LobbyActivityFeed::JSON_KEYS, array_keys($presented));
		$this->assertSame(9, $presented['id']);
		$this->assertSame(1_700_000_000, $presented['ts']);
		$this->assertSame(3, $presented['universeId']);
		$this->assertSame('Season Arena', $presented['universe']);
		$this->assertSame('Major battle', $presented['size']);
	}

	public function test_fetch_across_universes(): void
	{
		EventFirehoseWriter::record(1, 100, 10, 'a');
		EventFirehoseWriter::record(3, 101, 10, 'r');
		EventFirehoseWriter::record(2, 102, 10, 'w');

		$rows = LobbyActivityFeed::fetch(
			[1, 3],
			$this->lng,
			'UTC',
			[1 => 'Classic', 3 => 'Season']
		);

		$this->assertCount(2, $rows);
		$this->assertSame(3, $rows[0]['universeId']);
		$this->assertSame('Season', $rows[0]['universe']);
		$this->assertSame(1, $rows[1]['universeId']);
	}

	public function test_fetch_since_id_returns_newest_first(): void
	{
		EventFirehoseWriter::record(1, 100, 10, 'a');
		EventFirehoseWriter::record(1, 101, 10, 'r');
		EventFirehoseWriter::record(1, 102, 10, 'w');
		EventFirehoseWriter::record(1, 103, 10, 'a');

		$all = LobbyActivityFeed::fetch([1], $this->lng, 'UTC', [1 => 'Classic']);
		$this->assertGreaterThanOrEqual(4, count($all));
		// Oldest of the newest-first page — fetch everything after it.
		$sinceId = $all[count($all) - 2]['id'];

		$newer = LobbyActivityFeed::fetch(
			[1],
			$this->lng,
			'UTC',
			[1 => 'Classic'],
			$sinceId
		);

		$this->assertGreaterThanOrEqual(2, count($newer));
		$this->assertGreaterThan($sinceId, $newer[0]['id']);
		for ($i = 1, $n = count($newer); $i < $n; $i++) {
			$this->assertGreaterThan($newer[$i - 1]['id'], $newer[$i]['id']);
		}
	}

	public function test_fetch_returns_empty_when_database_throws(): void
	{
		$db = new class extends FakeDatabase {
			public function select($qry, array $params = []): array
			{
				throw new RuntimeException('lobby feed unavailable');
			}
		};
		$this->swapDatabaseInstance($db);

		$this->assertSame([], LobbyActivityFeed::fetch([1], $this->lng, 'UTC'));
	}

	public function test_empty_universe_list_returns_empty(): void
	{
		$this->assertSame([], LobbyActivityFeed::fetch([], $this->lng, 'UTC'));
	}
}

