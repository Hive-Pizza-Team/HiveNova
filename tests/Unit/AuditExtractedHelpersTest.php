<?php

use HiveNova\Core\AllianceDiplomacyService;
use HiveNova\Core\BanListData;
use HiveNova\Core\FleetPlanetDeduction;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

if (!defined('BANNED_USERS_PER_PAGE')) {
	define('BANNED_USERS_PER_PAGE', 25);
}

class AuditExtractedHelpersTest extends TestCase
{
	use SwapDatabaseInstance;

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testBuildDiplomaticListBucketsAcceptedOutgoingAndIncoming(): void
	{
		$db = new RecordingDatabase();
		$db->selectResult = [
			['id' => 1, 'level' => 1, 'accept' => 1, 'owner_1' => 10, 'owner_2' => 20, 'ally_name' => 'Accepted'],
			['id' => 2, 'level' => 5, 'accept' => 0, 'owner_1' => 10, 'owner_2' => 30, 'ally_name' => 'Outgoing'],
			['id' => 3, 'level' => 2, 'accept' => 0, 'owner_1' => 40, 'owner_2' => 10, 'ally_name' => 'Incoming'],
		];
		$this->swapDatabaseInstance($db);

		$list = AllianceDiplomacyService::buildDiplomaticList(10);

		$this->assertSame('Accepted', $list[0][1][1]);
		$this->assertSame('Outgoing', $list[2][5][2]);
		$this->assertSame('Incoming', $list[1][2][3]);
		$this->assertSame(10, $db->selects[0][1][':allianceId']);
	}

	public function testBanListDataFetchPaginatesAndFormatsRows(): void
	{
		$db = new RecordingDatabase();
		$db->selectSingleResult = ['count' => 26];
		$db->selectResult = [
			[
				'who' => 'BannedOne',
				'theme' => 'spam',
				'time' => 1700000000,
				'longer' => 1700003600,
				'author' => 'Admin',
				'email' => 'admin@example.com',
			],
		];
		$this->swapDatabaseInstance($db);

		$result = BanListData::fetch(1, 99, 'Y-m-d', 'UTC', 'Mail %s');

		$this->assertSame(26, $result['banCount']);
		$this->assertSame(2, $result['maxPage']);
		$this->assertSame(2, $result['page']);
		$this->assertCount(1, $result['banList']);
		$this->assertSame('BannedOne', $result['banList'][0]['player']);
		$this->assertSame('Mail Admin', $result['banList'][0]['info']);
		$this->assertSame(25, $db->selects[1][1][':offset']);
		$this->assertSame(25, $db->selects[1][1][':limit']);
	}

	public function testFleetPlanetDeductionSkipsWhenPlanetIdIsZero(): void
	{
		$db = new RecordingDatabase();
		FleetPlanetDeduction::deductShipsAndDeuterium($db, 0, [204 => 1], [204 => 'light_fighter'], [], [], 0.0);
		$this->assertSame([], $db->updates);
	}

	public function testFleetPlanetDeductionLegacyPathUpdatesWithoutPdo(): void
	{
		$db = new RecordingDatabase();
		$resource = [204 => 'light_fighter', 903 => 'deuterium'];
		$params = [':planetId' => 7, ':light_fighter' => '2'];
		$planetQuery = ['light_fighter = light_fighter - :light_fighter'];

		FleetPlanetDeduction::deductShipsAndDeuterium($db, 7, [204 => 2], $resource, $params, $planetQuery, 0.0);

		$this->assertCount(1, $db->updates);
		$this->assertStringContainsString('WHERE id = :planetId;', $db->updates[0][0]);
		$this->assertStringNotContainsString('FOR UPDATE', $db->updates[0][0]);
	}

	public function testFleetPlanetDeductionPdoPathLocksAndGuards(): void
	{
		$db = $this->pdoDatabase(false);
		$db->selectSingleResult = ['light_fighter' => 5, 'deuterium' => 100];
		$db->forcedRowCount = 1;
		$resource = [204 => 'light_fighter', 903 => 'deuterium'];
		$params = [
			':planetId' => 9,
			':light_fighter' => '3',
			':deuterium' => '10',
		];
		$planetQuery = [
			'light_fighter = light_fighter - :light_fighter',
			'deuterium = deuterium - :deuterium',
		];

		FleetPlanetDeduction::deductShipsAndDeuterium($db, 9, [204 => 3], $resource, $params, $planetQuery, 10.0);

		$this->assertSame(1, $db->beginCount);
		$this->assertSame(1, $db->commitCount);
		$this->assertSame(0, $db->rollbackCount);
		$this->assertStringContainsString('FOR UPDATE', $db->selects[0][0]);
		$this->assertStringContainsString('light_fighter >= :light_fighter', $db->updates[0][0]);
		$this->assertStringContainsString('deuterium >= :deuterium', $db->updates[0][0]);
	}

	public function testFleetPlanetDeductionPdoPathThrowsWhenPlanetMissing(): void
	{
		$db = $this->pdoDatabase(false);
		$db->selectSingleResult = false;

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Planet not found for fleet dispatch');

		FleetPlanetDeduction::deductShipsAndDeuterium(
			$db,
			9,
			[204 => 1],
			[204 => 'light_fighter'],
			[':planetId' => 9, ':light_fighter' => '1'],
			['light_fighter = light_fighter - :light_fighter'],
			0.0
		);
	}

	public function testFleetPlanetDeductionPdoPathThrowsWhenShipsInsufficient(): void
	{
		$db = $this->pdoDatabase(false);
		$db->selectSingleResult = ['light_fighter' => 1];

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Insufficient ships on planet');

		FleetPlanetDeduction::deductShipsAndDeuterium(
			$db,
			9,
			[204 => 5],
			[204 => 'light_fighter'],
			[':planetId' => 9, ':light_fighter' => '5'],
			['light_fighter = light_fighter - :light_fighter'],
			0.0
		);
	}

	public function testFleetPlanetDeductionPdoPathThrowsWhenDeuteriumInsufficient(): void
	{
		$db = $this->pdoDatabase(false);
		$db->selectSingleResult = ['light_fighter' => 5, 'deuterium' => 1];

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Insufficient deuterium on planet');

		FleetPlanetDeduction::deductShipsAndDeuterium(
			$db,
			9,
			[204 => 1],
			[204 => 'light_fighter', 903 => 'deuterium'],
			[':planetId' => 9, ':light_fighter' => '1', ':deuterium' => '10'],
			['light_fighter = light_fighter - :light_fighter', 'deuterium = deuterium - :deuterium'],
			10.0
		);
	}

	public function testFleetPlanetDeductionPdoPathThrowsWhenUpdateAffectsNoRows(): void
	{
		$db = $this->pdoDatabase(false);
		$db->selectSingleResult = ['light_fighter' => 5];
		$db->forcedRowCount = 0;

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Insufficient ships or deuterium on planet');

		FleetPlanetDeduction::deductShipsAndDeuterium(
			$db,
			9,
			[204 => 1],
			[204 => 'light_fighter'],
			[':planetId' => 9, ':light_fighter' => '1'],
			['light_fighter = light_fighter - :light_fighter'],
			0.0
		);
	}

	public function testFleetPlanetDeductionPdoPathSkipsOuterTransactionWhenAlreadyOpen(): void
	{
		$db = $this->pdoDatabase(true);
		$db->selectSingleResult = ['light_fighter' => 5];
		$db->forcedRowCount = 1;

		FleetPlanetDeduction::deductShipsAndDeuterium(
			$db,
			9,
			[204 => 1],
			[204 => 'light_fighter'],
			[':planetId' => 9, ':light_fighter' => '1'],
			['light_fighter = light_fighter - :light_fighter'],
			0.0
		);

		$this->assertSame(0, $db->beginCount);
		$this->assertSame(0, $db->commitCount);
	}

	private function pdoDatabase(bool $alreadyInTransaction): PdoCapableRecordingDatabase
	{
		$pdo = $this->getMockBuilder(PDO::class)
			->disableOriginalConstructor()
			->onlyMethods(['inTransaction'])
			->getMock();
		$pdo->method('inTransaction')->willReturn($alreadyInTransaction);

		return new PdoCapableRecordingDatabase($pdo);
	}
}

class PdoCapableRecordingDatabase extends RecordingDatabase
{
	public int $beginCount = 0;
	public int $commitCount = 0;
	public int $rollbackCount = 0;
	public int $forcedRowCount = 1;
	private PDO $pdo;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function getHandle(): ?PDO
	{
		return $this->pdo;
	}

	public function beginTransaction(): void
	{
		$this->beginCount++;
		parent::beginTransaction();
	}

	public function commit(): void
	{
		$this->commitCount++;
		parent::commit();
	}

	public function rollback(): void
	{
		$this->rollbackCount++;
		parent::rollback();
	}

	public function rowCount()
	{
		return $this->forcedRowCount;
	}
}
