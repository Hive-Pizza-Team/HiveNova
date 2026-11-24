<?php

use HiveNova\Core\PublicBattleReportService;
use PHPUnit\Framework\TestCase;

class PublicBattleReportServiceTest extends TestCase
{
	public function testFormatCompactUnits(): void
	{
		$this->assertSame('488.7M', PublicBattleReportService::formatCompactUnits(488700000));
		$this->assertSame('1.2B', PublicBattleReportService::formatCompactUnits(1200000000));
		$this->assertSame('999', PublicBattleReportService::formatCompactUnits(999));
		$this->assertSame('0', PublicBattleReportService::formatCompactUnits(-12));
		$this->assertSame('1.5K', PublicBattleReportService::formatCompactUnits(1500));
		$this->assertSame('2K', PublicBattleReportService::formatCompactUnits(2000));
	}

	public function testLoadPublicReportReturnsNullWhenHallMisses(): void
	{
		$service = new PublicBattleReportService(new PublicReportHallStub(null), new PublicReportDb());
		$this->assertNull($service->loadPublicReport('missing'));
	}

	public function testLoadPublicReportNormalizesLegacyCombatBlob(): void
	{
		$hall = [
			'result' => 'a',
			'time' => 10,
			'units' => 1500,
			'rid' => 'abc',
			'universe' => 1,
			'attacker' => 'Ann',
			'defender' => 'Bob',
		];
		$db = new PublicReportDb();
		$db->raport = serialize([
			'moon' => ['desfail' => 1, 'name' => 'Luna', 'chance' => 20, 'chance2' => 10, 'fleetfail' => 0],
			'debris' => [100, 40],
			'steal' => ['metal' => 5, 'crystal' => 6, 'deuterium' => 7],
		]);
		$service = new PublicBattleReportService(new PublicReportHallStub($hall), $db);
		$loaded = $service->loadPublicReport('abc');

		$this->assertNotNull($loaded);
		$this->assertSame('abc', $loaded['hall']['rid']);
		$this->assertSame('Luna', $loaded['combat']['moon']['moonName']);
		$this->assertFalse($loaded['combat']['moon']['moonDestroySuccess']);
		$this->assertTrue($loaded['combat']['moon']['fleetDestroySuccess']);
		$this->assertSame(100, $loaded['combat']['debris'][RESOURCE_METAL]);
		$this->assertSame(5, $loaded['combat']['steal'][RESOURCE_METAL]);
	}

	public function testLoadPublicReportNormalizesIndexedMoonAndIgnoresGarbage(): void
	{
		$hall = [
			'result' => 'd',
			'time' => 11,
			'units' => 1,
			'rid' => 'idx',
			'universe' => 1,
			'attacker' => '',
			'defender' => '',
		];
		$db = new PublicReportDb();
		$db->raport = serialize([
			'moon' => [20, 'Io', 0, 5, 1],
		]);
		$service = new PublicBattleReportService(new PublicReportHallStub($hall), $db);
		$loaded = $service->loadPublicReport('idx');
		$this->assertSame('Io', $loaded['combat']['moon']['moonName']);
		$this->assertTrue($loaded['combat']['moon']['moonDestroySuccess']);
		$this->assertFalse($loaded['combat']['moon']['fleetDestroySuccess']);

		$db->raport = serialize('not-an-array');
		$empty = $service->loadPublicReport('idx');
		$this->assertNull($empty['combat']);

		$db->raport = '';
		$blank = $service->loadPublicReport('idx');
		$this->assertNull($blank['combat']);
	}

	public function testDefaultHallAndDatabaseAccessorsRun(): void
	{
		$db = new PublicReportDb();
		$service = new PublicBattleReportService(null, $db);
		$this->assertNull($service->loadPublicReport(''));
	}
}

class PublicReportHallStub extends \HiveNova\Core\BattleHallService
{
	/** @param array<string, mixed>|null $found */
	public function __construct(private ?array $found)
	{
		parent::__construct(null);
	}

	public function findByRid(string $rid): ?array
	{
		return $this->found;
	}
}

class PublicReportDb implements \HiveNova\Core\DatabaseInterface
{
	public string $raport = '';

	public function select($qry, array $params = [])
	{
		return [];
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (!str_contains((string) $qry, '%%RW%%') || $this->raport === '') {
			return $field === false ? null : false;
		}
		$row = ['raport' => $this->raport];

		return $field === false ? $row : ($row[$field] ?? false);
	}

	public function insert($qry, array $params = [])
	{
		return true;
	}

	public function update($qry, array $params = [])
	{
		return true;
	}

	public function delete($qry, array $params = [])
	{
		return true;
	}

	public function replace($qry, array $params = [])
	{
		return true;
	}

	public function query($qry)
	{
		return true;
	}

	public function nativeQuery($qry)
	{
		return [];
	}

	public function lastInsertId()
	{
		return 1;
	}

	public function rowCount()
	{
		return 0;
	}

	public function getQueryCounter()
	{
		return 0;
	}

	public function quote($str)
	{
		return $str;
	}

	public function disconnect()
	{
	}

	public function getHandle(): ?\PDO
	{
		return null;
	}

	public function beginTransaction(): void
	{
	}

	public function commit(): void
	{
	}

	public function rollback(): void
	{
	}
}
