<?php

use HiveNova\Core\FlyingFleetHandler;
use HiveNova\Mission\FlyingFleetHandlerProbeMission;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/FlyingFleetHandlerProbeMission.php';

class FlyingFleetHandlerTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    /** @var array<int, string> */
    private array $originalPattern;

    protected function setUp(): void
    {
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

    public function testRunContinuesAfterMissionThrow(): void
    {
        $this->fake->eventFleets = [
            [
                'fleet_id' => 1,
                'fleet_mission' => 99,
                'fleet_mess' => FLEET_OUTWARD,
            ],
            [
                'fleet_id' => 2,
                'fleet_mission' => 99,
                'fleet_mess' => FLEET_OUTWARD,
            ],
        ];
        FlyingFleetHandlerProbeMission::$throwOnFleetId = 1;

        $handler = new FlyingFleetHandler();
        $handler->setToken('tok');
        $handler->run();

        $this->assertSame(2, FlyingFleetHandlerProbeMission::$started);
        $this->assertSame(1, FlyingFleetHandlerProbeMission::$finished);
    }
}
