<?php

use HiveNova\Core\Config;
use HiveNova\Core\FleetTargetInfoService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetTargetInfoServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(new Config([
            'uni'         => 1,
            'max_planets' => 15,
        ]), 1);

        $GLOBALS['LNG'] = [
            'type_planet_2'    => 'Debris Field',
            'type_planet_3'    => 'Moon',
            'type_mission_15'  => 'Expedition',
            'type_mission_16'  => 'Market',
            'fl_target_uninhabited' => 'Uninhabited',
        ];
    }

    public function testFormatLabelIncludesPlanetOwnerAndAlliance(): void
    {
        $label = FleetTargetInfoService::formatLabel([
            'coords'          => '2:100:5',
            'locationName'    => 'Homeworld',
            'ownerUsername'   => 'Alice',
            'allyTag'         => 'HIVE',
            'typeLabel'       => null,
        ]);

        $this->assertSame('Homeworld — Alice (HIVE) [2:100:5]', $label);
    }

    public function testFormatLabelFallsBackToCoordsOnly(): void
    {
        $label = FleetTargetInfoService::formatLabel([
            'coords'          => '2:100:5',
            'locationName'    => null,
            'ownerUsername'   => null,
            'allyTag'         => null,
            'typeLabel'       => null,
        ]);

        $this->assertSame('[2:100:5]', $label);
    }

    public function testResolveReturnsPlanetOwnerAndAlliance(): void
    {
        $this->fake->planetRowsById[10] = [
            'id'          => 10,
            'universe'    => 1,
            'galaxy'      => 2,
            'system'      => 100,
            'planet'      => 5,
            'planet_type' => 1,
            'name'        => 'Homeworld',
            'id_owner'    => 42,
        ];
        $this->fake->achievement->users[42] = [
            'id'       => 42,
            'username' => 'Alice',
            'ally_id'  => 7,
        ];
        $this->fake->achievement->alliances[7] = [
            'id'       => 7,
            'ally_tag' => 'HIVE',
        ];

        $info = FleetTargetInfoService::resolve(2, 100, 5, 1, 1);

        $this->assertSame('Homeworld', $info['locationName']);
        $this->assertSame('Alice', $info['ownerUsername']);
        $this->assertSame('HIVE', $info['allyTag']);
        $this->assertSame('Homeworld — Alice (HIVE) [2:100:5]', FleetTargetInfoService::formatLabel($info));
    }

    public function testResolveUsesParentPlanetOwnerForDebrisField(): void
    {
        $this->fake->planetRowsById[11] = [
            'id'          => 11,
            'universe'    => 1,
            'galaxy'      => 3,
            'system'      => 120,
            'planet'      => 8,
            'planet_type' => 1,
            'name'        => 'Colony',
            'id_owner'    => 55,
        ];
        $this->fake->achievement->users[55] = [
            'id'       => 55,
            'username' => 'Bob',
            'ally_id'  => 0,
        ];

        $info = FleetTargetInfoService::resolve(3, 120, 8, 2, 1);

        $this->assertSame('Debris Field', $info['typeLabel']);
        $this->assertSame('Bob', $info['ownerUsername']);
        $this->assertSame('Debris Field — Bob [3:120:8]', FleetTargetInfoService::formatLabel($info));
    }

    public function testResolveReturnsMoonName(): void
    {
        $this->fake->planetRowsById[12] = [
            'id'          => 12,
            'universe'    => 1,
            'galaxy'      => 4,
            'system'      => 200,
            'planet'      => 9,
            'planet_type' => 3,
            'name'        => 'Luna',
            'id_owner'    => 77,
        ];
        $this->fake->achievement->users[77] = [
            'id'       => 77,
            'username' => 'Carol',
            'ally_id'  => 0,
        ];

        $info = FleetTargetInfoService::resolve(4, 200, 9, 3, 1);

        $this->assertSame('Luna', $info['locationName']);
        $this->assertSame('Luna — Carol [4:200:9]', FleetTargetInfoService::formatLabel($info));
    }

    public function testResolveLabelsUninhabitedPosition(): void
    {
        $info = FleetTargetInfoService::resolve(5, 300, 4, 1, 1);

        $this->assertSame('Uninhabited', $info['typeLabel']);
        $this->assertSame('Uninhabited [5:300:4]', FleetTargetInfoService::formatLabel($info));
    }

    public function testResolveLabelsExpeditionSlot(): void
    {
        $info = FleetTargetInfoService::resolve(1, 50, 16, 1, 1);

        $this->assertSame('Expedition', $info['typeLabel']);
        $this->assertSame('Expedition [1:50:16]', FleetTargetInfoService::formatLabel($info));
    }
}
