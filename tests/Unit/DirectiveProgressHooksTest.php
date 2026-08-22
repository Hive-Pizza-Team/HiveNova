<?php

use HiveNova\Core\Config;
use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\DirectiveHooks;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';

class DirectiveProgressHooksTest extends TestCase
{
	use SwapDatabaseInstance;
	use RestoreGameGlobals;

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
		$GLOBALS['reslist']['defense'] = [401, 402];
		$GLOBALS['reslist']['tech'] = [106];
		$GLOBALS['reslist']['build'] = [1, 2, 3];
	}

	protected function tearDown(): void
	{
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testBuildingCompletionIncrementsIndustrial(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::INDUSTRIAL);
		DirectiveHooks::afterBuildCompleted([1 => 2], ['id' => 8, 'universe' => 1]);
		DirectiveHooks::afterBuildCompleted([106 => 1], ['id' => 8, 'universe' => 1]);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(3, $progress['build_complete']);
	}

	public function testUnknownDefenseIdFallsBackWhenOver400(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::DEFENSIVE);
		DirectiveHooks::afterBuildCompleted([499 => 1], ['id' => 8, 'universe' => 1]);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress['defense_complete']);
	}

	public function testDefenseCompletionIncrementsDefensive(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::DEFENSIVE);
		DirectiveHooks::afterBuildCompleted([401 => 3], ['id' => 8, 'universe' => 1]);
		DirectiveHooks::afterHoldSuccess(8, 1);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(3, $progress['defense_complete']);
		$this->assertSame(1, $progress['hold_success']);
	}

	public function testExpeditionDispatchIncrementsExploration(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::EXPLORATION);
		DirectiveHooks::afterExpeditionDispatch(8, 1);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress['expedition_dispatch']);
	}

	public function testTransportAboveThresholdIncrementsTrade(): void
	{
		DirectiveService::selectDirective(8, 1, DirectiveCatalog::TRADE);
		DirectiveHooks::afterTransport(8, 8000, 2000, 0, 1);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress['trade_run']);
	}
}
