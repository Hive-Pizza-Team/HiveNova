<?php

use HiveNova\Core\AllianceRankService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class AllianceRankServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	/** @var list<string> */
	private array $availableRanks = array(
		'MEMBERLIST',
		'ONLINESTATE',
		'RANKS',
		'MANAGEUSERS',
	);

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testCreateRankInsertsNameAndPermittedColumns(): void
	{
		$db = new RecordingDatabase();
		$this->swapDatabaseInstance($db);

		$rights = array(
			'MEMBERLIST'	=> true,
			'ONLINESTATE'	=> false,
			'RANKS'			=> true,
			'MANAGEUSERS'	=> true,
		);

		AllianceRankService::createRank(
			10,
			'Officer',
			array(0 => 1, 1 => 1, 2 => 0, 3 => 1),
			$this->availableRanks,
			$rights
		);

		$this->assertCount(1, $db->inserts);
		$sql = $db->inserts[0][0];
		$params = $db->inserts[0][1];

		$this->assertStringContainsString('INSERT INTO %%ALLIANCE_RANK%%', $sql);
		$this->assertStringContainsString('`MEMBERLIST`', $sql);
		$this->assertStringContainsString('`RANKS`', $sql);
		$this->assertStringContainsString('`MANAGEUSERS`', $sql);
		$this->assertStringNotContainsString('`ONLINESTATE`', $sql);
		$this->assertSame('Officer', $params[':rankName']);
		$this->assertSame(10, $params[':allianceID']);
		$this->assertSame(1, $params[':MEMBERLIST']);
		$this->assertSame(0, $params[':RANKS']);
		$this->assertSame(1, $params[':MANAGEUSERS']);
	}

	public function testDeleteRankRemovesRankAndClearsMembers(): void
	{
		$db = new RecordingDatabase();
		$this->swapDatabaseInstance($db);

		AllianceRankService::deleteRank(10, 5);

		$this->assertCount(1, $db->deletes);
		$this->assertStringContainsString('DELETE FROM %%ALLIANCE_RANK%%', $db->deletes[0][0]);
		$this->assertSame(10, $db->deletes[0][1][':allianceId']);
		$this->assertSame(5, $db->deletes[0][1][':rankID']);

		$this->assertCount(1, $db->updates);
		$this->assertStringContainsString('ally_rank_id = 0', $db->updates[0][0]);
		$this->assertSame(5, $db->updates[0][1][':rankID']);
		$this->assertSame(10, $db->updates[0][1][':allianceId']);
	}

	public function testLoadAssignableRanksIncludesDefaultAndFilteredRanks(): void
	{
		$db = new RecordingDatabase();
		$db->selectResult = array(
			array('rankID' => 2, 'MEMBERLIST' => 1, 'ONLINESTATE' => 1, 'RANKS' => 1, 'MANAGEUSERS' => 1),
		);
		$this->swapDatabaseInstance($db);

		$rights = array(
			'MEMBERLIST' => true,
			'ONLINESTATE' => true,
			'RANKS' => true,
			'MANAGEUSERS' => true,
		);
		$list = AllianceRankService::loadAssignableRanks(10, $this->availableRanks, $rights);
		$this->assertArrayHasKey(0, $list);
		$this->assertArrayHasKey(2, $list);
	}

	public function testReassignMemberRanksSkipsOwnerAndUnknownRanks(): void
	{
		$db = new RecordingDatabase();
		$this->swapDatabaseInstance($db);

		$rankList = array(
			0 => array('MEMBERLIST' => true),
			2 => array('MEMBERLIST' => true),
		);
		AllianceRankService::reassignMemberRanks(10, 1, array(1 => 2, 7 => 2, 8 => 99), $rankList);

		$this->assertCount(1, $db->updates);
		$this->assertSame(7, $db->updates[0][1][':userId']);
		$this->assertSame(2, $db->updates[0][1][':rankID']);
	}
}
