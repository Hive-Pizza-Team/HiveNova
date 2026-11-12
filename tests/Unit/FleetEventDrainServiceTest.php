<?php

use HiveNova\Core\FleetEventDrainService;
use HiveNova\Core\FlyingFleetHandler;
use HiveNova\Mission\FlyingFleetHandlerProbeMission;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/FlyingFleetHandlerProbeMission.php';

class FleetEventDrainServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	/** @var array<int, string> */
	private array $originalPattern;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
		$this->originalPattern = FlyingFleetHandler::$missionObjPattern;
		FlyingFleetHandler::$missionObjPattern[99] = FlyingFleetHandlerProbeMission::class;
		FlyingFleetHandlerProbeMission::reset();
	}

	protected function tearDown(): void
	{
		FlyingFleetHandler::$missionObjPattern = $this->originalPattern;
		FlyingFleetHandlerProbeMission::reset();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testDrainStopsWhenNoDueEvents(): void
	{
		$this->fake->fleetEventLockCounts = [0];
		$passes = FleetEventDrainService::drainDueEvents($this->fake, TIMESTAMP, 5);
		$this->assertSame(0, $passes);
		$this->assertCount(1, $this->fake->fleetEventUpdates);
	}

	public function testDrainRunsMultiplePassesUntilIdle(): void
	{
		$this->fake->fleetEventLockCounts = [1, 1, 0];
		$tokenSeq = ['tok-a', 'tok-b', 'tok-c'];
		$handlerCalls = 0;

		$passes = FleetEventDrainService::drainDueEvents(
			$this->fake,
			TIMESTAMP,
			5,
			static function () use (&$tokenSeq): string {
				return array_shift($tokenSeq) ?? 'tok-extra';
			},
			function (string $token) use (&$handlerCalls): FlyingFleetHandler {
				$handlerCalls++;
				$this->fake->eventFleets = [
					[
						'fleet_id' => $handlerCalls,
						'fleet_mission' => 99,
						'fleet_mess' => FLEET_HOLD,
					],
				];
				$handler = new FlyingFleetHandler();
				$handler->setToken($token);

				return $handler;
			}
		);

		$this->assertSame(2, $passes);
		$this->assertSame(2, $handlerCalls);
		$this->assertSame(2, FlyingFleetHandlerProbeMission::$endStay);
		$this->assertSame(
			['tok-a', 'tok-b', 'tok-c'],
			array_column(
				array_map(
					static fn (array $u): array => $u['params'],
					array_values(array_filter(
						$this->fake->fleetEventUpdates,
						static fn (array $u): bool => str_contains($u['sql'], '`lock` IS NULL')
					))
				),
				':token'
			)
		);
	}

	public function testReleaseLockKeepsFailedFleetIdsLocked(): void
	{
		$this->fake->fleetEvents = [
			['fleetID' => 1, 'time' => TIMESTAMP - 10, 'lock' => 'tok'],
			['fleetID' => 2, 'time' => TIMESTAMP - 10, 'lock' => 'tok'],
		];

		FleetEventDrainService::releaseLock($this->fake, 'tok', [1]);

		$this->assertSame('tok', $this->fake->fleetEvents[0]['lock']);
		$this->assertNull($this->fake->fleetEvents[1]['lock']);
	}

	public function testFleetHandlerEntrypointDelegatesToService(): void
	{
		$source = (string) file_get_contents(ROOT_PATH . 'includes/FleetHandler.php');
		$this->assertStringContainsString('FleetEventDrainService::drainDueEvents', $source);
		$this->assertStringNotContainsString('for ($pass = 0;', $source);
	}
}
