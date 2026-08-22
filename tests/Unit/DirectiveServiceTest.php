<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class DirectiveServiceTest extends TestCase
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

	public function testPeriodWindowAnchorsToMondayUtc(): void
	{
		$thursday = (new DateTime('2026-08-20 12:00:00', new DateTimeZone('UTC')))->getTimestamp();
		$monday = (new DateTime('2026-08-17 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
		$window = DirectiveService::periodWindow($thursday);
		$this->assertSame($monday, $window['start']);
		$this->assertSame($monday + 7 * 86400, $window['end']);
	}

	public function testSelectDirectiveLocksChoice(): void
	{
		$row = DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
		$this->assertSame(DirectiveCatalog::INDUSTRIAL, $row['directive_key']);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_LOCKED);
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::TRADE);
	}

	public function testUnknownDirectiveRejected(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_UNKNOWN);
		DirectiveService::selectDirective(10, 1, 'not_a_real_directive');
	}

	public function testClaimRewardIsIdempotent(): void
	{
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
		$this->db->userDirectives[0]['completed_at'] = TIMESTAMP;
		$this->db->planets[5] = ['id' => 5, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];

		$first = DirectiveService::claimReward(10, 1, 5);
		$this->assertGreaterThanOrEqual(50000, $first['metal']);
		$this->assertGreaterThanOrEqual(25000, $first['crystal']);
		$this->assertSame(50000, $this->db->planets[5]['metal']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_CLAIMED);
		DirectiveService::claimReward(10, 1, 5);
	}

	public function testModuleDisabledRejectsSelect(): void
	{
		$modules = array_fill(0, 49, 1);
		$modules[MODULE_COMMANDER] = 0;
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', $modules),
		]), 1);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_DISABLED);
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
	}

	public function testSameOriginRejectsForeignOrigin(): void
	{
		$_SERVER['HTTP_HOST'] = 'moon.hive.pizza';
		$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
		$this->assertFalse(DirectiveService::isSameOriginRequest());

		$_SERVER['HTTP_ORIGIN'] = 'https://moon.hive.pizza';
		$this->assertTrue(DirectiveService::isSameOriginRequest());
		unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_ORIGIN']);
	}
}
