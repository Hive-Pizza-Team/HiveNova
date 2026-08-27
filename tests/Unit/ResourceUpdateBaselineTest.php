<?php

use HiveNova\Core\Config;
use HiveNova\Core\ResourceUpdate;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class ResourceUpdateBaselineTest extends TestCase
{
	use SwapDatabaseInstance;

	private RecordingDatabase $db;

	protected function setUp(): void
	{
		parent::setUp();
		ResourceUpdate::resetResourceBaselinesForTests();
		$this->db = new RecordingDatabase();
		$this->swapDatabaseInstance($this->db);
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 49, 1)),
		]), 1);
		if (!defined('MODULE_ACHIEVEMENTS')) {
			define('MODULE_ACHIEVEMENTS', 25);
		}
		$GLOBALS['resource'] = [
			901 => 'metal',
			902 => 'crystal',
			903 => 'deuterium',
			921 => 'darkmatter',
		];
		$GLOBALS['reslist'] = [
			'one' => [],
			'prod' => [],
			'build' => [],
			'tech' => [],
			'defense' => [],
		];
	}

	protected function tearDown(): void
	{
		ResourceUpdate::resetResourceBaselinesForTests();
		unset($GLOBALS['PLANET']);
		parent::tearDown();
	}

	public function testSavePlanetToDbWritesResourceDeltas(): void
	{
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($GLOBALS['resource'], $GLOBALS['reslist']);

		$USER = $this->user(100);
		$PLANET = $this->planet(7, 1000, 500, 200);

		$eco->setData($USER, $PLANET);

		$PLANET['metal'] = 1100;
		$PLANET['crystal'] = 450;
		$USER['darkmatter'] = 5;

		$eco->SavePlanetToDB($USER, $PLANET);

		[$sql, $params] = $this->lastUpdate();
		$this->assertStringContainsString('p.metal + :metalDelta', $sql);
		$this->assertSame(100.0, (float) $params[':metalDelta']);
		$this->assertSame(-50.0, (float) $params[':crystalDelta']);
		$this->assertSame(0.0, (float) $params[':deuteriumDelta']);
		$this->assertSame(5.0, (float) $params[':darkmatterDelta']);
	}

	public function testExternalSqlSyncShiftsBaseline(): void
	{
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($GLOBALS['resource'], $GLOBALS['reslist']);

		$USER = $this->user(100);
		$PLANET = $this->planet(7, 1000, 500, 200);
		$eco->setData($USER, $PLANET);

		$PLANET['metal'] -= 200;
		$PLANET['deuterium'] -= 50;
		ResourceUpdate::adjustPlanetResourceBaseline(7, -200.0, 0.0, -50.0);

		$PLANET['metal'] += 100;

		$eco->SavePlanetToDB($USER, $PLANET);

		[, $params] = $this->lastUpdate();
		$this->assertSame(100.0, (float) $params[':metalDelta']);
		$this->assertSame(0.0, (float) $params[':crystalDelta']);
		$this->assertSame(0.0, (float) $params[':deuteriumDelta']);
	}

	public function testDirectiveSessionHelperAdjustsBaseline(): void
	{
		$GLOBALS['PLANET'] = $this->planet(7, 1000, 500, 200);
		$eco = new ResourceUpdate(false, false);
		$eco->setResourceData($GLOBALS['resource'], $GLOBALS['reslist']);
		$eco->setData($this->user(100), $GLOBALS['PLANET']);

		\HiveNova\Core\DirectiveService::addResourcesToSessionPlanet(7, [
			'metal' => 50,
			'crystal' => 0,
			'deuterium' => 10,
		]);

		$baseline = ResourceUpdate::peekPlanetResourceBaseline(7);
		$this->assertNotNull($baseline);
		$this->assertSame(1050.0, $baseline['metal']);
		$this->assertSame(210.0, $baseline['deuterium']);
		$this->assertSame(1050.0, (float) $GLOBALS['PLANET']['metal']);
	}

	/**
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	private function lastUpdate(): array
	{
		$this->assertNotEmpty($this->db->updates);
		return $this->db->updates[array_key_last($this->db->updates)];
	}

	/** @return array<string, mixed> */
	private function user(int $id): array
	{
		return [
			'id' => $id,
			'universe' => 1,
			'darkmatter' => 0,
			'b_tech' => 0,
			'b_tech_id' => 0,
			'b_tech_planet' => 0,
			'b_tech_queue' => '',
			'factor' => ['Resource' => 1, 'Energy' => 1],
		];
	}

	/** @return array<string, mixed> */
	private function planet(int $id, float $metal, float $crystal, float $deuterium): array
	{
		return [
			'id' => $id,
			'metal' => $metal,
			'crystal' => $crystal,
			'deuterium' => $deuterium,
			'eco_hash' => '',
			'last_update' => 1,
			'b_building' => 0,
			'b_building_id' => '',
			'field_current' => 0,
			'b_hangar_id' => '',
			'metal_perhour' => 0,
			'crystal_perhour' => 0,
			'deuterium_perhour' => 0,
			'metal_max' => 100000,
			'crystal_max' => 100000,
			'deuterium_max' => 100000,
			'energy_used' => 0,
			'energy' => 0,
			'b_hangar' => 0,
			'planet' => 'planet',
			'temp_max' => 50,
		];
	}
}
