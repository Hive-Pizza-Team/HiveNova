<?php

use HiveNova\Core\Config;
use HiveNova\Core\FleetDispatchService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetDispatchServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->defineMissionModules();

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        Config::setInstance(new Config([
            'uni'                  => 1,
            'moduls'               => implode(';', array_fill(0, 50, 1)),
            'max_galaxy'           => 9,
            'max_system'           => 499,
            'max_planets'          => 15,
            'max_dm_missions'      => 2,
            'fleet_speed'          => 2500,
            'halt_speed'           => 1,
            'noobprotection'       => 1,
            'noobprotectiontime'   => 5000,
            'noobprotectionmulti'  => 5,
            'adm_attack'           => 1,
        ]), 1);

        $GLOBALS['LNG'] = [
            'fl_error_same_planet'          => 'Same planet',
            'fl_invalid_target'             => 'Invalid target',
            'fl_no_noresource'              => 'No resources',
            'fl_resources'                  => 'Resources not allowed',
            'fl_no_noresource_exchange'     => 'No exchange amount',
            'fl_invalid_mission'            => 'Invalid mission',
            'fl_not_all_ship_avalible'      => 'Ships unavailable',
            'fl_not_enough_deuterium'       => 'Not enough deuterium',
            'fl_not_enough_space'           => 'Not enough space',
            'fl_no_slots'                   => 'No fleet slots',
            'fl_target_exists'              => 'Target already exists',
            'fl_only_planets_colonizable'   => 'Only planets can be colonized',
            'fl_no_target'                  => 'No target',
            'fl_empty_target'               => 'Empty target',
            'fl_admin_attack'               => 'Admin attack',
            'fl_player_is_noob'             => 'Player is noob',
            'fl_player_is_strong'           => 'Player is strong',
            'fl_bash_protection'            => 'Bash protection',
            'fl_no_expedition_slot'         => 'No expedition slot',
            'fl_no_same_alliance'           => 'Not same alliance',
            'fl_stronger_techs'             => 'Stronger techs',
            'fl_hold_time_not_exists'       => 'Hold time invalid',
            'fl_not_enough_resource'        => 'Not enough resource',
        ];

        $GLOBALS['resource'][108] = 'computer_tech';
        $GLOBALS['resource'][109] = 'weapons_tech';
        $GLOBALS['resource'][110] = 'shielding_tech';
        $GLOBALS['resource'][111] = 'armour_tech';
        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $GLOBALS['resource'][202] = 'light_fighter';
        $GLOBALS['resource'][901] = 'metal';
        $GLOBALS['resource'][902] = 'crystal';
        $GLOBALS['resource'][903] = 'deuterium';

        $GLOBALS['pricelist'][202] = array_merge(
            $GLOBALS['pricelist'][202] ?? [],
            [
                'speed'       => 12500,
                'tech'        => 1,
                'consumption' => 20,
                'capacity'    => 50,
            ]
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['USER']);

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // validateTarget
    // -------------------------------------------------------------------------

    public function testValidateTargetThrowsWhenDispatchingToSamePlanet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Same planet');

        FleetDispatchService::validateTarget(
            $this->baseValidateTargetParams(),
            ['id' => 1],
            $this->basePlanet()
        );
    }

    public function testValidateTargetThrowsOnInvalidCoordinates(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid target');

        $params = $this->baseValidateTargetParams([
            'targetGalaxy' => 0,
            'targetSystem' => 1,
            'targetPlanet' => 5,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
        ]));
    }

    public function testValidateTargetThrowsWhenTransportHasNoCargo(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No resources');

        $params = $this->baseValidateTargetParams([
            'targetMission'      => 3,
            'TransportMetal'     => 0,
            'TransportCrystal'   => 0,
            'TransportDeuterium'   => 0,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
        ]));
    }

    public function testValidateTargetThrowsWhenMarketSellIncludesResources(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resources not allowed');

        $params = $this->baseValidateTargetParams([
            'targetMission'      => 16,
            'markettype'           => 1,
            'TransportMetal'     => 100,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
        ]));
    }

    public function testValidateTargetThrowsWhenExchangeAmountMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No exchange amount');

        $params = $this->baseValidateTargetParams([
            'targetMission'        => 16,
            'markettype'           => 0,
            'TransportMetal'       => 100,
            'WantedResourceAmount' => 0,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
        ]));
    }

    public function testValidateTargetThrowsWhenExchangeAmountAbsurdlyLarge(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid mission');

        $params = $this->baseValidateTargetParams([
            'targetMission'        => 16,
            'markettype'           => 0,
            'TransportMetal'       => 100,
            'WantedResourceAmount' => pow(10, 51),
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
        ]));
    }

    public function testValidateTargetThrowsWhenShipCountExceedsPlanetStock(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ships unavailable');

        $params = $this->baseValidateTargetParams([
            'fleetArray' => [202 => 5],
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy'        => 2,
            'light_fighter' => 2,
        ]));
    }

    public function testValidateTargetThrowsWhenDeuteriumInsufficientForFuel(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough deuterium');

        $params = $this->baseValidateTargetParams([
            'consumption' => 500,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy'     => 2,
            'deuterium'  => 100,
        ]));
    }

    public function testValidateTargetThrowsWhenFleetStorageTooSmall(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough space');

        $params = $this->baseValidateTargetParams([
            'TransportMetal'   => 1000,
            'fleetStorage'     => 10,
            'consumption'      => 0,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
            'metal'  => 5000,
        ]));
    }

    public function testValidateTargetPassesForValidTransport(): void
    {
        $params = $this->baseValidateTargetParams([
            'targetMission'      => 3,
            'TransportMetal'     => 100,
            'fleetStorage'       => 500,
            'consumption'        => 50,
        ]);

        FleetDispatchService::validateTarget($params, ['id' => 1], $this->basePlanet([
            'galaxy' => 2,
        ]));

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // validateSlots
    // -------------------------------------------------------------------------

    public function testValidateSlotsThrowsWhenFleetSlotsFull(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No fleet slots');

        $this->fake->fleetCountResult = 1;

        FleetDispatchService::validateSlots([
            'id'     => 1,
            'factor' => ['FleetSlots' => 0],
        ], 0);
    }

    public function testValidateSlotsPassesWhenSlotsAvailable(): void
    {
        $this->fake->fleetCountResult = 0;

        FleetDispatchService::validateSlots([
            'id'            => 1,
            'computer_tech' => 2,
            'factor'        => ['FleetSlots' => 0],
        ], 0);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // validateMission
    // -------------------------------------------------------------------------

    public function testValidateMissionAcceptsNullPlanetDataForColonize(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empty target');

        FleetDispatchService::validateMission(
            null,
            [],
            7,
            ['id' => 1, 'authlevel' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [7]]]),
            new Config([])
        );
    }

    public function testValidateMissionThrowsWhenColonizeTargetAlreadyExists(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Target already exists');

        FleetDispatchService::validateMission(
            ['id' => 99, 'id_owner' => 5, 'destruyed' => 0],
            ['id' => 5, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'vacation' => 0],
            7,
            ['id' => 1, 'authlevel' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [7]]]),
            new Config([])
        );
    }

    public function testValidateMissionThrowsWhenColonizeTargetsNonPlanet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only planets can be colonized');

        FleetDispatchService::validateMission(
            null,
            ['id' => 5, 'authlevel' => 0, 'onlinetime' => TIMESTAMP],
            7,
            ['id' => 1, 'authlevel' => 0],
            $this->baseFleetData([
                'targetType'        => 3,
                'availableMissions' => ['MissionSelector' => [7]],
            ]),
            new Config([])
        );
    }

    public function testValidateMissionThrowsWhenTargetPlanetMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No target');

        FleetDispatchService::validateMission(
            null,
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0],
            3,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [3]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenTargetPlanetDestroyed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No target');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => TIMESTAMP],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0],
            3,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [3]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenMissionNotAvailable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid mission');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0],
            1,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [3]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenTargetOnVacation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Target already exists');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP - 60, 'urlaubs_modus' => 1],
            1,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [1]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenExpeditionSlotsFull(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No expedition slot');

        $this->fake->fleetCountResult = 1;

        FleetDispatchService::validateMission(
            null,
            ['id' => 1, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0],
            15,
            ['id' => 1, 'authlevel' => 0, 'astrophysics_tech' => 0, 'factor' => ['Expedition' => 0]],
            $this->baseFleetData([
                'availableMissions' => [
                    'MissionSelector' => [15],
                    'StayBlock'       => [1 => 1],
                ],
                'stayTime' => 1,
            ]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenDMMissionSlotsFull(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No expedition slot');

        $this->fake->fleetCountResult = 2;

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0, 'ally_id' => 0],
            11,
            ['id' => 1, 'authlevel' => 0, 'universe' => 1, 'factor' => ['Expedition' => 0]],
            $this->baseFleetData([
                'availableMissions' => [
                    'MissionSelector' => [11],
                    'StayBlock'       => [1 => 1],
                ],
                'stayTime' => 1,
            ]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenBashProtectionApplies(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bash protection');

        $GLOBALS['USER'] = ['id' => 1];
        $this->fake->planetRowsById[10] = ['id_owner' => 2];
        $this->fake->userOnlinetime[2] = TIMESTAMP;
        $this->fake->bashLogCount = 5;

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0],
            1,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [1]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenAttackingHigherAuthAdmin(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Admin attack');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'authattack' => 3, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0],
            1,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [1]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenTargetIsNoob(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player is noob');

        $this->fake->achievement->statPoints = ['total_points' => 50000];

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'authattack' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0, 'total_points' => 1000],
            1,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [1]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenTargetIsTooStrong(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player is strong');

        $this->fake->achievement->statPoints = ['total_points' => 1000];

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'authattack' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0, 'total_points' => 50000],
            1,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 0],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [1]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenSpyTargetNotBuddyOrAlly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not same alliance');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'authattack' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0, 'ally_id' => 99],
            5,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 1],
            $this->baseFleetData([
                'availableMissions' => [
                    'MissionSelector' => [5],
                    'StayBlock'       => [1 => 1],
                ],
                'stayTime' => 1,
            ]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenMission17TechTooWeak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stronger techs');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            [
                'id'             => 2,
                'authlevel'      => 0,
                'onlinetime'     => TIMESTAMP,
                'urlaubs_modus'  => 0,
                'banaday'        => 0,
                'ally_id'        => 0,
                'weapons_tech'   => 10,
                'shielding_tech' => 10,
                'armour_tech'    => 10,
                'universe'       => 1,
            ],
            17,
            [
                'id'           => 1,
                'authlevel'    => 0,
                'ally_id'      => 0,
                'weapons_tech' => 1,
                'shielding_tech' => 1,
                'armour_tech'  => 1,
                'universe'     => 1,
                'factor'       => ['Attack' => 0, 'Defensive' => 0, 'Shield' => 0],
            ],
            $this->baseFleetData(['availableMissions' => ['MissionSelector' => [17]]]),
            Config::get()
        );
    }

    public function testValidateMissionThrowsWhenHoldTimeInvalid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hold time invalid');

        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0, 'ally_id' => 1],
            5,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 1],
            $this->baseFleetData([
                'availableMissions' => [
                    'MissionSelector' => [5],
                    'StayBlock'       => [2 => 2],
                ],
                'stayTime' => 99,
            ]),
            Config::get()
        );
    }

    public function testValidateMissionPassesForAllySpyMission(): void
    {
        FleetDispatchService::validateMission(
            ['id' => 10, 'id_owner' => 2, 'destruyed' => 0],
            ['id' => 2, 'authlevel' => 0, 'onlinetime' => TIMESTAMP, 'urlaubs_modus' => 0, 'banaday' => 0, 'ally_id' => 5],
            5,
            ['id' => 1, 'authlevel' => 0, 'ally_id' => 5],
            $this->baseFleetData([
                'availableMissions' => [
                    'MissionSelector' => [5],
                    'StayBlock'       => [1 => 1],
                ],
                'stayTime' => 1,
            ]),
            Config::get()
        );

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // calculateMetrics
    // -------------------------------------------------------------------------

    public function testCalculateMetricsReturnsDurationConsumptionAndSpeed(): void
    {
        $user = [
            'combustion_tech'         => 0,
            'impulse_motor_tech'      => 0,
            'hyperspace_motor_tech'   => 0,
            'factor'                  => ['FlyTime' => 0],
        ];

        $result = FleetDispatchService::calculateMetrics([202 => 1], 1005, 10.0, $user);

        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('consumption', $result);
        $this->assertArrayHasKey('fleetMaxSpeed', $result);
        $this->assertGreaterThan(0, $result['duration']);
        $this->assertGreaterThan(0, $result['consumption']);
        $this->assertGreaterThan(0, $result['fleetMaxSpeed']);
    }

    // -------------------------------------------------------------------------
    // dispatch
    // -------------------------------------------------------------------------

    public function testDispatchDeductsResourcesAndReturnsFleetId(): void
    {
        $planet = [
            'id'            => 42,
            'galaxy'        => 1,
            'system'        => 5,
            'planet'        => 8,
            'planet_type'   => 1,
            'metal'         => 10000,
            'crystal'       => 5000,
            'deuterium'     => 3000,
            'light_fighter' => 10,
        ];

        $this->fake->planetRowsById[42] = [
            'metal'     => 10000,
            'crystal'   => 5000,
            'deuterium' => 3000,
        ];

        $fleetId = FleetDispatchService::dispatch([
            'fleetArray'         => [202 => 1],
            'targetMission'      => 3,
            'USER'               => ['id' => 1, 'universe' => 1],
            'targetPlanetData'   => ['id' => 99, 'id_owner' => 2],
            'targetGalaxy'       => 1,
            'targetSystem'       => 6,
            'targetPlanet'       => 3,
            'targetType'         => 1,
            'fleetResource'      => [901 => 100, 902 => 50, 903 => 25],
            'fleetStartTime'     => TIMESTAMP + 3600,
            'fleetStayTime'      => 0,
            'fleetEndTime'       => TIMESTAMP + 7200,
            'fleetGroup'         => 0,
            'consumption'        => 75,
            'markettype'         => 0,
            'WantedResourceType' => 0,
            'WantedResourceAmount' => 0,
            'maxFlightTime'      => 0,
            'visibility'         => 0,
        ], $planet);

        $this->assertSame(99, $fleetId);
        $this->assertSame(9900, $planet['metal']);
        $this->assertSame(4950, $planet['crystal']);
        $this->assertSame(2900, $planet['deuterium']);
    }

    public function testDispatchThrowsWhenLockedPlanetLacksResources(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough resource');

        $planet = [
            'id'          => 42,
            'galaxy'      => 1,
            'system'      => 5,
            'planet'      => 8,
            'planet_type' => 1,
            'metal'       => 10000,
            'crystal'     => 5000,
            'deuterium'   => 3000,
        ];

        $this->fake->planetRowsById[42] = [
            'metal'     => 10,
            'crystal'   => 5000,
            'deuterium' => 3000,
        ];

        FleetDispatchService::dispatch([
            'fleetArray'         => [202 => 1],
            'targetMission'      => 3,
            'USER'               => ['id' => 1, 'universe' => 1],
            'targetPlanetData'   => ['id' => 99, 'id_owner' => 2],
            'targetGalaxy'       => 1,
            'targetSystem'       => 6,
            'targetPlanet'       => 3,
            'targetType'         => 1,
            'fleetResource'      => [901 => 100, 902 => 0, 903 => 0],
            'fleetStartTime'     => TIMESTAMP + 3600,
            'fleetStayTime'      => 0,
            'fleetEndTime'       => TIMESTAMP + 7200,
            'fleetGroup'         => 0,
            'consumption'        => 0,
            'markettype'         => 0,
            'WantedResourceType' => 0,
            'WantedResourceAmount' => 0,
            'maxFlightTime'      => 0,
            'visibility'         => 0,
        ], $planet);
    }

    public function testDispatchInsertsTradeRowForMarketMission(): void
    {
        $planet = [
            'id'            => 42,
            'galaxy'        => 1,
            'system'        => 5,
            'planet'        => 8,
            'planet_type'   => 1,
            'metal'         => 10000,
            'crystal'       => 5000,
            'deuterium'     => 3000,
            'light_fighter' => 5,
        ];

        $this->fake->planetRowsById[42] = [
            'metal'     => 10000,
            'crystal'   => 5000,
            'deuterium' => 3000,
        ];

        $fleetId = FleetDispatchService::dispatch([
            'fleetArray'           => [202 => 1],
            'targetMission'        => 16,
            'USER'                 => ['id' => 1, 'universe' => 1],
            'targetPlanetData'     => ['id' => 0, 'id_owner' => 0],
            'targetGalaxy'         => 1,
            'targetSystem'         => 16,
            'targetPlanet'         => 1,
            'targetType'           => 1,
            'fleetResource'        => [901 => 500, 902 => 0, 903 => 0],
            'fleetStartTime'       => TIMESTAMP + 3600,
            'fleetStayTime'        => TIMESTAMP + 7200,
            'fleetEndTime'         => TIMESTAMP + 10800,
            'fleetGroup'           => 0,
            'consumption'          => 50,
            'markettype'           => 0,
            'WantedResourceType'   => 902,
            'WantedResourceAmount' => 200,
            'maxFlightTime'        => 4,
            'visibility'           => 1,
        ], $planet);

        $this->assertSame(99, $fleetId);
        $this->assertSame(9500, $planet['metal']);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function baseValidateTargetParams(array $overrides = []): array
    {
        return array_merge([
            'targetMission'        => 1,
            'targetGalaxy'         => 1,
            'targetSystem'         => 5,
            'targetPlanet'         => 8,
            'targetType'           => 1,
            'TransportMetal'       => 0,
            'TransportCrystal'     => 0,
            'TransportDeuterium'   => 0,
            'WantedResourceAmount' => 1,
            'markettype'           => 0,
            'fleetArray'           => [202 => 1],
            'fleetStorage'         => 1000,
            'fleetSpeed'           => 10,
            'distance'             => 1005,
            'consumption'          => 10,
        ], $overrides);
    }

    private function basePlanet(array $overrides = []): array
    {
        return array_merge([
            'id'            => 1,
            'galaxy'        => 1,
            'system'        => 5,
            'planet'        => 8,
            'planet_type'   => 1,
            'light_fighter' => 10,
            'metal'         => 5000,
            'crystal'       => 3000,
            'deuterium'     => 1000,
        ], $overrides);
    }

    private function baseFleetData(array $overrides = []): array
    {
        return array_merge([
            'fleetArray'        => [202 => 1],
            'fleetGroup'        => 0,
            'targetType'        => 1,
            'stayTime'          => 0,
            'availableMissions' => ['MissionSelector' => [3]],
        ], $overrides);
    }

    private function defineMissionModules(): void
    {
        if (!defined('BASH_ON')) {
            define('BASH_ON', true);
        }
        if (!defined('BASH_COUNT')) {
            define('BASH_COUNT', 3);
        }
        if (!defined('BASH_TIME')) {
            define('BASH_TIME', 86400);
        }

        $modules = [
            'MODULE_MISSION_ATTACK'     => 1,
            'MODULE_MISSION_ACS'        => 42,
            'MODULE_MISSION_TRANSPORT'    => 34,
            'MODULE_MISSION_HOLD'       => 33,
            'MODULE_MISSION_SPY'        => 24,
            'MODULE_MISSION_DESTROY'    => 29,
            'MODULE_MISSION_EXPEDITION' => 30,
            'MODULE_MISSION_RECYCLE'    => 32,
            'MODULE_MISSION_COLONY'     => 35,
            'MODULE_MISSION_STATION'    => 36,
            'MODULE_MISSION_TRADE'      => 44,
            'MODULE_MISSION_TRANSFER'   => 45,
            'MODULE_MISSION_DARKMATTER' => 31,
        ];
        foreach ($modules as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
