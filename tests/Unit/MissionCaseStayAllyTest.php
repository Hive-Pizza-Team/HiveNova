<?php

use HiveNova\Core\Config;
use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\Universe;
use HiveNova\Mission\MissionCaseStayAlly;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';
require_once __DIR__ . '/../Support/DirectiveUnlockFixtures.php';

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
	use DirectiveUnlockFixtures;

	private CommanderDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->snapshotGameGlobals();
		$this->db = new CommanderDatabaseStub();
		$this->swapDatabaseInstance($this->db);
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 49, 1)),
		]), 1);
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, [1]);
	}

	protected function tearDown(): void
	{
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testEndStayRecordsHoldAndReturnsFleet(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::DEFENSIVE, $this->directiveUnlockedUser(), $this->directiveUnlockedPlanet());

		$mission = new TestableMissionCaseStayAlly([
			'fleet_id' => 1,
			'fleet_owner' => 8,
			'fleet_universe' => 1,
			'fleet_end_time' => TIMESTAMP + 10,
			'fleet_end_stay' => TIMESTAMP,
			'fleet_array' => '202,1;',
		]);
		$mission->EndStayEvent();
		$this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
		$this->assertSame(1, $mission->_fleet['saved']);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress['hold_success']);
	}

	public function testEndStayReturnsFleetBeforeCallingHoldHook(): void
	{
		$source = (string) file_get_contents(ROOT_PATH . 'includes/classes/missions/MissionCaseStayAlly.php');
		$returnPos = strpos($source, 'setState(FLEET_RETURN)');
		$hookPos = strpos($source, 'afterHoldSuccess');
		$this->assertNotFalse($returnPos);
		$this->assertNotFalse($hookPos);
		$this->assertLessThan($hookPos, $returnPos, 'Fleet must enter RETURN before the directive hook');
	}

	public function testEndStayReturnsFleetWhenHoldHookThrows(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::DEFENSIVE, $this->directiveUnlockedUser(), $this->directiveUnlockedPlanet());

		$db = new class extends CommanderDatabaseStub {
			public function selectSingle($qry, array $params = array(), $field = null)
			{
				if (str_contains((string) $qry, '%%DIRECTIVE_PERIODS%%')) {
					throw new RuntimeException('period boom');
				}

				return parent::selectSingle($qry, $params, $field);
			}
		};
		$db->periods = $this->db->periods;
		$db->userDirectives = $this->db->userDirectives;
		$this->swapDatabaseInstance($db);

		$mission = new TestableMissionCaseStayAlly([
			'fleet_id' => 2,
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
