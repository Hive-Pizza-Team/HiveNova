<?php

use HiveNova\Core\Config;
use HiveNova\Core\FleetDispatchService;

use PHPUnit\Framework\TestCase;

class FleetDispatchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // validateMission uses global $LNG for error strings
        $GLOBALS['LNG'] = [
            'fl_target_exists'          => 'Target already exists',
            'fl_only_planets_colonizable' => 'Only planets can be colonized',
            'fl_no_target'              => 'No target',
            'fl_empty_target'           => 'Empty target',
            'fl_invalid_mission'        => 'Invalid mission',
            'fl_admin_attack'           => 'Admin attack',
            'fl_player_is_noob'         => 'Player is noob',
            'fl_player_is_strong'       => 'Player is strong',
            'fl_bash_protection'        => 'Bash protection',
            'fl_no_expedition_slot'     => 'No expedition slot',
        ];
    }

    // -------------------------------------------------------------------------
    // validateMission — null target planet (regression: was `false` before fix)
    // -------------------------------------------------------------------------

    /**
     * Regression test: selectSingle() returns false when no planet row exists.
     * The caller coerces false → null so validateMission(?array) doesn't get
     * a TypeError. For a colonize mission to an empty coordinate, null planet
     * data is valid; the method should throw a domain RuntimeException (empty
     * player), not a PHP TypeError.
     */
    public function testValidateMissionAcceptsNullPlanetDataForColonize(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empty target');

        FleetDispatchService::validateMission(
            null,           // planet not found — coerced from false by the caller
            [],             // no player → triggers RuntimeException before any DB use
            7,              // MISSION_COLONIZE
            ['id' => 1, 'authlevel' => 0],
            [
                'fleetArray'        => [202 => 1],
                'fleetGroup'        => 0,
                'targetType'        => 1,
                'stayTime'          => 0,
                'availableMissions' => ['MissionSelector' => [7]],
            ],
            new Config([])
        );
    }

    /**
     * Colonize to a coordinate that already has a planet should throw
     * regardless of null vs populated planet data.
     */
    public function testValidateMissionThrowsWhenColonizeTargetAlreadyExists(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Target already exists');

        FleetDispatchService::validateMission(
            ['id' => 99, 'id_owner' => 5, 'destruyed' => 0, 'ally_deposit' => 0],
            ['id' => 5, 'authlevel' => 0, 'onlinetime' => 0, 'vacation' => 0],
            7,              // MISSION_COLONIZE — target must be empty
            ['id' => 1, 'authlevel' => 0],
            [
                'fleetArray'        => [202 => 1],
                'fleetGroup'        => 0,
                'targetType'        => 1,
                'stayTime'          => 0,
                'availableMissions' => ['MissionSelector' => [7]],
            ],
            new Config([])
        );
    }
}
