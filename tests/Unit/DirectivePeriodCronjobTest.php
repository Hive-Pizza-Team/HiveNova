<?php

use HiveNova\Core\Config;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\DirectivePeriodCronjob;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';

class DirectivePeriodCronjobTest extends TestCase
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
	}

	protected function tearDown(): void
	{
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testRunCreatesPeriodAndAutoResolvesOldBranches(): void
	{
		$this->db->planets[1] = ['id' => 1, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];
		ExpeditionChoiceService::createPendingBranch(8, 2, 1, 'resource_find', 'aggressive', [
			'metal' => 500,
			'crystal' => 0,
			'deuterium' => 0,
		], []);
		$this->db->pendingChoices[8]['created_at'] = TIMESTAMP - 200000;

		(new DirectivePeriodCronjob())->run();

		$this->assertNotEmpty($this->db->periods);
		$this->assertNotEmpty($this->db->pendingChoices[8]['resolved_at']);
		$this->assertNotNull(DirectiveService::getCurrentPeriod(1));
	}

	public function testRunSwallowsUnexpectedFailures(): void
	{
		$throwing = new class extends CommanderDatabaseStub {
			public function select($qry, array $params = array())
			{
				throw new RuntimeException('boom');
			}
		};
		$this->swapDatabaseInstance($throwing);
		(new DirectivePeriodCronjob())->run();
		$this->assertTrue(true);
	}
}
