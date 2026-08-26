<?php

use HiveNova\Core\Config;
use HiveNova\Core\DatabaseSeasonStore;
use HiveNova\Core\SeasonWipeService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class DatabaseSeasonStoreTest extends TestCase
{
	use SwapDatabaseInstance;

	private RecordingDatabase $db;

	protected function setUp(): void
	{
		parent::setUp();
		if (!defined('AUTH_USR')) {
			define('AUTH_USR', 0);
		}
		$this->db = new RecordingDatabase();
		$this->swapDatabaseInstance($this->db);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testFindUserSql(): void
	{
		$this->db->selectSingleResult = ['id' => 10, 'hive_account' => 'aliceaaa', 'universe' => 2, 'authlevel' => 0];
		$user = (new DatabaseSeasonStore())->findUser(10);
		$this->assertSame('aliceaaa', $user['hive_account']);
		$this->assertStringContainsString('%%USERS%%', $this->db->selects[0][0]);
	}

	public function testInsertAndFindEntrySql(): void
	{
		$store = new DatabaseSeasonStore();
		$this->assertTrue($store->insertEntry([
			'universe' => 2, 'season_id' => 1, 'user_id' => 10, 'hive_account' => 'aliceaaa',
			'pizza_amount' => 1.5, 'trx_id' => 'abc', 'created_at' => 1,
		]));
		$this->assertStringContainsString('%%SEASON_ENTRIES%%', $this->db->inserts[0][0]);

		$this->db->selectSingleResult = ['id' => 1, 'user_id' => 10];
		$row = $store->findEntry(2, 1, 10);
		$this->assertSame(10, (int) $row['user_id']);
	}

	public function testRankingRowsMapsOrder(): void
	{
		$this->db->selectResult = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 9, 'rank' => 0],
			['user_id' => 11, 'hive_account' => 'bobbbbbb', 'authlevel' => 0, 'points' => 3, 'rank' => 0],
		];
		$rows = (new DatabaseSeasonStore())->rankingRows(2, 1);
		$this->assertSame(1, $rows[0]['rank']);
		$this->assertSame(2, $rows[1]['rank']);
		$this->assertStringContainsString('%%STATPOINTS%%', $this->db->selects[0][0]);
		$this->assertStringContainsString('%%SEASON_ENTRIES%%', $this->db->selects[0][0]);
	}

	public function testPayoutLifecycleSql(): void
	{
		$store = new DatabaseSeasonStore();
		$store->insertPayouts([[
			'universe' => 2, 'season_id' => 1, 'user_id' => 10, 'hive_account' => 'aliceaaa',
			'rank' => 1, 'points' => 100, 'pizza_amount' => 1.5, 'trx_id' => '', 'status' => 'pending',
		]]);
		$store->markPayout(4, 'sent', 'trxZ');
		$this->assertStringContainsString('%%SEASON_PAYOUTS%%', $this->db->inserts[0][0]);
		$this->assertSame('sent', $this->db->updates[0][1][':status']);
	}

	public function testUpsertWeekInsertsThenUpdates(): void
	{
		$store = new DatabaseSeasonStore();
		$this->db->selectSingleResult = false;
		$store->upsertWeek([
			'universe' => 2, 'season_id' => 1, 'starts_at' => 1, 'closes_at' => 2,
			'status' => 'running', 'pool_pizza' => 0, 'house_cut_pizza' => 0, 'payout_budget' => 0,
		]);
		$this->assertNotEmpty($this->db->inserts);

		$this->db->selectSingleResult = ['id' => 1, 'season_id' => 1];
		$store->upsertWeek([
			'universe' => 2, 'season_id' => 1, 'starts_at' => 1, 'closes_at' => 2,
			'status' => 'paying', 'pool_pizza' => 9, 'house_cut_pizza' => 1, 'payout_budget' => 8,
		]);
		$this->assertNotEmpty($this->db->updates);
	}

	public function testWipeProgressDelegates(): void
	{
		$wiped = [];
		$wipe = $this->getMockBuilder(SeasonWipeService::class)
			->disableOriginalConstructor()
			->onlyMethods(['wipe'])
			->getMock();
		$wipe->expects($this->once())->method('wipe')->willReturnCallback(
			function (int $uni) use (&$wiped): void {
				$wiped[] = $uni;
			}
		);
		$store = new DatabaseSeasonStore($wipe);
		$store->wipeProgress(2, new Config(['uni' => 2, 'metal_start' => 0, 'crystal_start' => 0, 'deuterium_start' => 0, 'darkmatter_start' => 0]));
		$this->assertSame([2], $wiped);
	}

	public function testSumPoolAndHasTrx(): void
	{
		$store = new DatabaseSeasonStore();
		$this->db->selectSingleResult = ['pool' => '3.500', 'id' => 9];
		$this->assertSame(3.5, $store->sumPool(2, 1));
		$this->assertTrue($store->hasTrx(2, 'abc'));
		$this->assertFalse($store->hasTrx(2, ''));
	}

	public function testOpenPayoutsAndPlayers(): void
	{
		$store = new DatabaseSeasonStore();
		$this->db->selectResult = [[
			'id' => 10, 'user_id' => 10, 'hive_account' => 'aliceaaa',
			'pizza_amount' => 1.2, 'status' => 'pending', 'trx_id' => '',
			'lang' => 'en',
		]];
		$open = $store->openPayouts(2, 1);
		$this->assertSame(1.2, $open[0]['pizza_amount']);
		$players = $store->playersInUniverse(2);
		$this->assertSame(10, $players[0]['id']);
	}

	public function testReplaceSnapshotsDeletesThenInserts(): void
	{
		$store = new DatabaseSeasonStore();
		$store->replaceSnapshots(2, 1, [[
			'user_id' => 10, 'hive_account' => 'aliceaaa', 'rank' => 1, 'points' => 5,
		]]);
		$this->assertStringContainsString('%%SEASON_SNAPSHOTS%%', $this->db->deletes[0][0]);
		$this->assertStringContainsString('%%SEASON_SNAPSHOTS%%', $this->db->inserts[0][0]);
	}

	public function testReportQueriesSql(): void
	{
		$store = new DatabaseSeasonStore();
		$this->db->selectResult = [
			['rank' => 1, 'hive_account' => 'aliceaaa', 'points' => 9, 'username' => 'Alice', 'pizza_amount' => '1.5'],
		];
		$rows = $store->reportRanking(2, 1, 20);
		$this->assertSame('Alice', $rows[0]['username']);
		$this->assertStringContainsString('%%SEASON_SNAPSHOTS%%', $this->db->selects[0][0]);
		$this->assertStringContainsString('%%SEASON_PAYOUTS%%', $this->db->selects[0][0]);

		$this->db->selectResult = [
			['units' => 100, 'result' => 'awon', 'attacker' => 'A', 'defender' => 'B'],
		];
		$hof = $store->reportHallOfFame(2, 10);
		$this->assertSame(100, $hof[0]['units']);
		$this->assertStringContainsString('%%TOPKB%%', $this->db->selects[1][0]);

		$this->db->selectResult = [
			['feat_key' => 'feat_first_ship', 'claimed_at' => 50, 'username' => 'Sam', 'hive_account' => 'samacct'],
		];
		$feats = $store->reportFeats(2, 1, 100);
		$this->assertSame('feat_first_ship', $feats[0]['feat_key']);
		$this->assertStringContainsString('%%FEAT_STATES%%', $this->db->selects[2][0]);

		$this->db->selectSingleResult = ['c' => 4];
		$this->assertSame(4, $store->countEntries(2, 1));
	}
}
