<?php

use HiveNova\Core\PublicBattleReportService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PublicBattleReportServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testRejectsInvalidRidWithoutQuery(): void
	{
		$db = new RecordingDatabase();
		$this->swapDatabaseInstance($db);

		$this->assertNull((new PublicBattleReportService($db))->findPublicReport(''));
		$this->assertNull((new PublicBattleReportService($db))->findPublicReport('not-a-rid'));
		$this->assertSame([], $db->selects);
	}

	public function testReturnsNullWhenNotInTopkb(): void
	{
		$db = new RecordingDatabase();
		$db->selectSingleResult = false;
		$this->swapDatabaseInstance($db);

		$rid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$this->assertNull((new PublicBattleReportService($db))->findPublicReport($rid));
		$this->assertStringContainsString('%%TOPKB%%', $db->selects[0][0]);
	}

	public function testLoadsTopkbReportWithSideNamesAndNormalizesDebris(): void
	{
		$rid = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$payload = [
			'result' => 'a',
			'time'   => 1700000000,
			'units'  => [100, 200],
			'debris' => [1200000, 800000],
			'steal'  => ['metal' => 10, 'crystal' => 20, 'deuterium' => 30],
		];

		$db = new class ($rid, $payload) extends RecordingDatabase {
			/** @param array<string, mixed> $payload */
			public function __construct(private string $rid, private array $payload)
			{
			}

			public function selectSingle($qry, array $params = array(), $field = false)
			{
				$this->selects[] = [$qry, $params];

				return [
					'rid'      => $this->rid,
					'units'    => 488700000,
					'result'   => 'a',
					'time'     => 1700000000,
					'universe' => 1,
					'raport'   => serialize($this->payload),
				];
			}

			public function select($qry, array $params = array())
			{
				$this->selects[] = [$qry, $params];

				return [
					['role' => 1, 'names' => 'vanen3'],
					['role' => 2, 'names' => 'crazyphantombr'],
				];
			}
		};
		$this->swapDatabaseInstance($db);

		$out = (new PublicBattleReportService($db))->findPublicReport($rid);

		$this->assertNotNull($out);
		$this->assertSame($rid, $out['rid']);
		$this->assertSame('vanen3', $out['attacker']);
		$this->assertSame('crazyphantombr', $out['defender']);
		$this->assertSame(488700000, $out['units']);
		$this->assertSame(RESOURCE_METAL, array_key_first($out['combatReport']['debris']));
		$this->assertSame(1200000, $out['combatReport']['debris'][RESOURCE_METAL]);
		$this->assertSame(10, $out['combatReport']['steal'][RESOURCE_METAL]);
	}

	public function testNormalizeLegacyMoonAndSimu(): void
	{
		$service = new PublicBattleReportService(new RecordingDatabase());
		$normalized = $service->normalizeLegacyReport([
			'moon' => [
				'desfail'   => 0,
				'name'      => 'Luna',
				'chance'    => 20,
				'chance2'   => 5,
				'fleetfail' => 1,
			],
			'simu' => 'extra',
		]);

		$this->assertTrue($normalized['moon']['moonDestroySuccess']);
		$this->assertFalse($normalized['moon']['fleetDestroySuccess']);
		$this->assertSame('extra', $normalized['additionalInfo']);
	}
}
