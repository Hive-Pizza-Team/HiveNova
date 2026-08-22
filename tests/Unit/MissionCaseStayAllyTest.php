<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseStayAlly;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';

class TestableMissionCaseStayAlly extends MissionCaseStayAlly
{
	public function SaveFleet()
	{
		$this->_fleet['saved'] = 1;
	}
}

class MissionCaseStayAllyTest extends TestCase
{
	use SwapDatabaseInstance;
	use RestoreGameGlobals;

	protected function setUp(): void
	{
		parent::setUp();
		$this->snapshotGameGlobals();
		$this->swapDatabaseInstance(new CommanderDatabaseStub());
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 49, 1)),
		]), 1);
	}

	protected function tearDown(): void
	{
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testEndStayRecordsHoldAndReturnsFleet(): void
	{
		$mission = new TestableMissionCaseStayAlly([
			'fleet_id' => 1,
			'fleet_owner' => 8,
			'fleet_universe' => 1,
			'fleet_end_time' => TIMESTAMP + 10,
			'fleet_array' => '202,1;',
		]);
		$mission->EndStayEvent();
		$this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
		$this->assertSame(1, $mission->_fleet['saved']);
	}
}
