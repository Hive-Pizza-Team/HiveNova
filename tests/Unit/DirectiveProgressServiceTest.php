<?php

use HiveNova\Core\Config;
use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\DirectiveProgressService;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class DirectiveProgressServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private CommanderDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
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
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testRecordIncrementsAndCompletes(): void
	{
		DirectiveService::selectDirective(3, 1, DirectiveCatalog::EXPLORATION);
		for ($i = 0; $i < 5; $i++) {
			DirectiveProgressService::record(3, 'expedition_dispatch', ['universe' => 1]);
		}
		$row = $this->db->userDirectives[0];
		$progress = json_decode((string) $row['progress_json'], true);
		$this->assertSame(5, $progress['expedition_dispatch']);
		$this->assertNotEmpty($row['completed_at']);
	}

	public function testTradeIgnoresSubThresholdCargo(): void
	{
		DirectiveService::selectDirective(3, 1, DirectiveCatalog::TRADE);
		DirectiveProgressService::record(3, 'transport_delivery', ['universe' => 1, 'cargo' => 100]);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(0, $progress['trade_run']);

		DirectiveProgressService::record(3, 'transport_delivery', [
			'universe' => 1,
			'cargo' => DirectiveCatalog::TRADE_CARGO_THRESHOLD,
		]);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress['trade_run']);
	}

	public function testNoDirectiveMeansNoProgress(): void
	{
		DirectiveService::ensureCurrentPeriod(1);
		DirectiveProgressService::record(99, 'expedition_dispatch', ['universe' => 1]);
		$this->assertSame([], $this->db->userDirectives);
	}

	public function testTargetsMetRequiresEveryCounter(): void
	{
		$this->assertFalse(DirectiveProgressService::targetsMet(['a' => 1], ['a' => 2, 'b' => 1]));
		$this->assertTrue(DirectiveProgressService::targetsMet(['a' => 2, 'b' => 1], ['a' => 2, 'b' => 1]));
	}
}
