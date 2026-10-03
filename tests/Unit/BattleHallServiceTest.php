<?php

use HiveNova\Core\BattleHallService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class BattleHallServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testEmptyUniverseReturnsNoRowsAndSkipsNameQuery(): void
	{
		$db = new class extends RecordingDatabase {
			public int $selectCalls = 0;

			public function select($qry, array $params = array())
			{
				$this->selectCalls++;
				$this->selects[] = [$qry, $params];

				return [];
			}
		};
		$this->swapDatabaseInstance($db);

		$out = (new BattleHallService($db))->listTopBattles(1);

		$this->assertSame([], $out);
		$this->assertSame(1, $db->selectCalls);
		$this->assertStringContainsString('FROM %%TOPKB%%', $db->selects[0][0]);
		$this->assertStringNotContainsString('TOPKB_USERS', $db->selects[0][0]);
	}

	public function testAssemblesAttackerAndDefenderFromSingleJoinPass(): void
	{
		$db = new class extends RecordingDatabase {
			public function select($qry, array $params = array())
			{
				$this->selects[] = [$qry, $params];

				if (str_contains($qry, 'FROM %%TOPKB%%')) {
					return [
						[
							'rid'    => 'abc',
							'units'  => 5000,
							'result' => 'a',
							'time'   => 1700000000,
						],
						[
							'rid'    => 'def',
							'units'  => 1000,
							'result' => 'w',
							'time'   => 1700000100,
						],
					];
				}

				return [
					['rid' => 'abc', 'role' => 1, 'uid' => 2, 'snapshot_name' => 'Bob', 'live_name' => 'Robert'],
					['rid' => 'abc', 'role' => 1, 'uid' => 1, 'snapshot_name' => 'Alice', 'live_name' => 'Alice'],
					['rid' => 'abc', 'role' => 2, 'uid' => 3, 'snapshot_name' => '', 'live_name' => 'Carol'],
					['rid' => 'abc', 'role' => 9, 'uid' => 8, 'snapshot_name' => 'Ignored', 'live_name' => 'Ignored'],
					['rid' => 'def', 'role' => 1, 'uid' => 4, 'snapshot_name' => 'Dave', 'live_name' => null],
					['rid' => 'def', 'role' => 2, 'uid' => 5, 'snapshot_name' => 'Eve', 'live_name' => 'Eve'],
				];
			}

			public function quote($str)
			{
				return "'" . addslashes((string) $str) . "'";
			}
		};
		$this->swapDatabaseInstance($db);

		$out = (new BattleHallService($db))->listTopBattles(3, 50);

		$this->assertCount(2, $db->selects);
		$this->assertSame(3, $db->selects[0][1][':universe']);
		$this->assertStringContainsString('LIMIT 50', $db->selects[0][0]);
		$this->assertStringContainsString('ORDER BY units DESC', $db->selects[0][0]);
		$this->assertStringContainsString('FROM %%TOPKB_USERS%%', $db->selects[1][0]);
		$this->assertStringContainsString('LEFT JOIN %%USERS%%', $db->selects[1][0]);
		$this->assertStringNotContainsString('INNER JOIN', $db->selects[1][0]);
		$this->assertStringContainsString('tk.username AS snapshot_name', $db->selects[1][0]);
		$this->assertStringContainsString("'abc'", $db->selects[1][0]);
		$this->assertStringContainsString("'def'", $db->selects[1][0]);
		$this->assertStringNotContainsString('SELECT *', $db->selects[0][0]);

		$this->assertSame([
			[
				'result'   => 'a',
				'time'     => 1700000000,
				'units'    => 5000,
				'rid'      => 'abc',
				'attacker' => 'Alice & Bob',
				'defender' => 'Carol',
			],
			[
				'result'   => 'w',
				'time'     => 1700000100,
				'units'    => 1000,
				'rid'      => 'def',
				'attacker' => 'Dave',
				'defender' => 'Eve',
			],
		], $out);
	}

	public function testCapsLimitAt100(): void
	{
		$db = new class extends RecordingDatabase {
			public function select($qry, array $params = array())
			{
				$this->selects[] = [$qry, $params];

				return [];
			}
		};
		$this->swapDatabaseInstance($db);

		(new BattleHallService($db))->listTopBattles(1, 999);

		$this->assertStringContainsString('LIMIT 100', $db->selects[0][0]);
	}

	public function testDeletedUserKeepsSnapshotName(): void
	{
		$db = new class extends RecordingDatabase {
			public function select($qry, array $params = array())
			{
				$this->selects[] = [$qry, $params];

				if (str_contains($qry, 'FROM %%TOPKB%%')) {
					return [
						[
							'rid'    => 'fight-1',
							'units'  => 9000,
							'result' => 'a',
							'time'   => 1700000200,
						],
						[
							'rid'    => 'fight-2',
							'units'  => 8000,
							'result' => 'r',
							'time'   => 1700000300,
						],
						[
							'rid'    => 'fight-3',
							'units'  => 7000,
							'result' => 'a',
							'time'   => 1700000400,
						],
					];
				}

				return [
					['rid' => '', 'role' => 1, 'uid' => 1, 'snapshot_name' => 'Skip', 'live_name' => 'Skip'],
					// Defender account was deleted; snapshot still has the fight name.
					['rid' => 'fight-1', 'role' => 1, 'uid' => 10, 'snapshot_name' => 'eco', 'live_name' => 'eco'],
					['rid' => 'fight-1', 'role' => 2, 'uid' => 404, 'snapshot_name' => 'SomePlayer', 'live_name' => null],
					// Snapshot empty, live account renamed away — use the current username.
					['rid' => 'fight-2', 'role' => 1, 'uid' => 11, 'snapshot_name' => '', 'live_name' => 'Current'],
					['rid' => 'fight-2', 'role' => 2, 'uid' => 12, 'snapshot_name' => 'OldName', 'live_name' => 'NewName'],
					// Both the snapshot and the user row are gone.
					['rid' => 'fight-3', 'role' => 1, 'uid' => 13, 'snapshot_name' => '   ', 'live_name' => null],
					['rid' => 'fight-3', 'role' => 2, 'uid' => 14, 'snapshot_name' => '', 'live_name' => ''],
				];
			}

			public function quote($str)
			{
				return "'" . addslashes((string) $str) . "'";
			}
		};
		$this->swapDatabaseInstance($db);

		$out = (new BattleHallService($db))->listTopBattles(1, 100, '(deleted)');

		$this->assertSame('eco', $out[0]['attacker']);
		$this->assertSame('SomePlayer', $out[0]['defender']);
		$this->assertSame('Current', $out[1]['attacker']);
		$this->assertSame('OldName', $out[1]['defender']);
		$this->assertSame('(deleted)', $out[2]['attacker']);
		$this->assertSame('(deleted)', $out[2]['defender']);
		$this->assertStringContainsString('LEFT JOIN %%USERS%%', $db->selects[1][0]);
		$this->assertStringNotContainsString('INNER JOIN', $db->selects[1][0]);

		$fallback = (new BattleHallService($db))->listTopBattles(1, 100, '   ');
		$this->assertSame(BattleHallService::MISSING_NAME, $fallback[2]['defender']);
	}
}
