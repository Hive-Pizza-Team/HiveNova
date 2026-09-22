<?php

use HiveNova\Core\DatabaseUni3ClaimStore;
use HiveNova\Core\Uni3ClaimGate;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class DatabaseUni3ClaimStoreTest extends TestCase
{
	use SwapDatabaseInstance;

	private RecordingDatabase $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new RecordingDatabase();
		$this->swapDatabaseInstance($this->db);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testLinkAndMedalSql(): void
	{
		$store = new DatabaseUni3ClaimStore();
		$this->assertTrue($store->insertHiveLink([
			'universe' => 3, 'season_id' => 2, 'user_id' => 9,
			'hive_account' => 'aliceaaa', 'origin' => Uni3ClaimGate::ORIGIN_EMAIL, 'linked_at' => 10,
		]));
		$this->assertStringContainsString('%%SEASON_HIVE_LINKS%%', $this->db->inserts[0][0]);

		$this->db->selectSingleResult = ['user_id' => 9, 'hive_account' => 'aliceaaa', 'origin' => 'email'];
		$row = $store->findHiveLinkByAccount(3, 2, 'aliceaaa');
		$this->assertSame(9, (int) $row['user_id']);
		$this->assertSame(9, (int) $store->findHiveLinkByUser(3, 2, 9)['user_id']);

		$this->db->selectSingleResult = ['id' => 9];
		$this->assertSame(9, $store->hiveOwnerId(3, 'aliceaaa'));
		$store->setUserHiveAccount(3, 9, 'aliceaaa');
		$this->assertStringContainsString('%%USERS%%', $this->db->updates[0][0]);

		$this->db->selectSingleResult = false;
		$store->upsertMedal([
			'universe' => 3, 'season_id' => 2, 'user_id' => 9, 'hive_account' => 'aliceaaa',
			'tier' => 'participant', 'status' => 'pending_claim', 'points' => 80, 'claimed_at' => 0,
		]);
		$this->assertStringContainsString('%%SEASON_MEDALS%%', $this->db->inserts[1][0]);

		$this->db->selectSingleResult = ['user_id' => 9, 'status' => 'pending_claim'];
		$this->assertSame('pending_claim', $store->findMedal(3, 2, 9)['status']);
		$store->upsertMedal([
			'universe' => 3, 'season_id' => 2, 'user_id' => 9, 'hive_account' => 'aliceaaa',
			'tier' => 'participant', 'status' => 'pending_claim', 'points' => 80, 'claimed_at' => 0,
		]);
		$store->markMedal(3, 2, 9, 'claimed', 99, 'aliceaaa');
		$this->assertSame('claimed', $this->db->updates[1][1][':status']);
	}

	public function testMissingOwnerIsNull(): void
	{
		$this->db->selectSingleResult = false;
		$this->assertNull((new DatabaseUni3ClaimStore())->hiveOwnerId(3, 'nobodyxx'));
		$this->assertNull((new DatabaseUni3ClaimStore())->findHiveLinkByAccount(3, 2, 'nobodyxx'));
	}
}
