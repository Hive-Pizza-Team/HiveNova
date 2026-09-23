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
					['rid' => 'abc', 'role' => 1, 'names' => 'Alice & Bob'],
					['rid' => 'abc', 'role' => 2, 'names' => 'Carol'],
					['rid' => 'def', 'role' => 1, 'names' => 'Dave'],
					['rid' => 'def', 'role' => 2, 'names' => 'Eve'],
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
}
