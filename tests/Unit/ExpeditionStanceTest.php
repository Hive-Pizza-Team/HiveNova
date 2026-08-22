<?php

use HiveNova\Core\Config;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\FleetDispatchService;
use HiveNova\Core\FleetFunctions;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';

class ExpeditionStanceTest extends TestCase
{
	use SwapDatabaseInstance;
	use RestoreGameGlobals;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		parent::setUp();
		$this->snapshotGameGlobals();
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 49, 1)),
		]), 1);
		$GLOBALS['LNG']['fl_invalid_stance'] = 'Invalid expedition stance';
		$GLOBALS['resource'][202] = 'light_cargo';
		$GLOBALS['resource'][903] = 'deuterium';
	}

	protected function tearDown(): void
	{
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testNormalizeExpeditionStanceIgnoresNonExpedition(): void
	{
		$this->assertNull(FleetDispatchService::normalizeExpeditionStance('aggressive', 3));
		$this->assertSame('aggressive', FleetDispatchService::normalizeExpeditionStance('aggressive', 15));
	}

	public function testInvalidStanceRejectedAtDispatch(): void
	{
		$this->expectException(RuntimeException::class);
		FleetDispatchService::normalizeExpeditionStance('turbo', 15);
	}

	public function testSendFleetStoresStanceMeta(): void
	{
		if (!defined('FLEET_OUTWARD')) {
			define('FLEET_OUTWARD', 0);
		}
		FleetFunctions::sendFleet(
			[202 => 1],
			15,
			1,
			10,
			1,
			1,
			1,
			1,
			0,
			0,
			1,
			2,
			16,
			1,
			[901 => 0, 902 => 0, 903 => 0],
			TIMESTAMP + 100,
			TIMESTAMP + 200,
			TIMESTAMP + 300,
			0,
			0,
			0,
			0,
			1,
			['stance' => 'cautious']
		);
		$row = $this->fake->fleetRowsById[$this->fake->lastFleetInsertId];
		$this->assertSame('cautious', ExpeditionChoiceService::stanceFromMeta($row['fleet_meta']));
	}

	public function testEncodeFleetMetaHandlesInvalidInput(): void
	{
		$this->assertNull(FleetFunctions::encodeFleetMeta(null));
		$this->assertNull(FleetFunctions::encodeFleetMeta(''));
		$this->assertNull(FleetFunctions::encodeFleetMeta('not-json'));
		$this->assertNull(FleetFunctions::encodeFleetMeta(12));
		$json = FleetFunctions::encodeFleetMeta(['stance' => 'cautious']);
		$this->assertIsString($json);
		$this->assertSame($json, FleetFunctions::encodeFleetMeta($json));
	}

	public function testCautiousVersusAggressivePreviewCurves(): void
	{
		$base = ['metal' => 1000, 'crystal' => 0, 'deuterium' => 0, 'ships' => [202 => 10]];
		$c = ExpeditionChoiceService::buildOptions('resource_find', 'cautious', $base);
		$a = ExpeditionChoiceService::buildOptions('resource_find', 'aggressive', $base);
		$this->assertLessThan($a['aggressive']['metal'], $c['aggressive']['metal']);
		$this->assertSame([], $c['cautious']['loss_ships']);
		$this->assertNotEmpty($a['aggressive']['loss_ships']);
		$this->assertLessThan(
			$a['aggressive']['loss_ships'][202],
			$c['aggressive']['loss_ships'][202]
		);
	}
}
