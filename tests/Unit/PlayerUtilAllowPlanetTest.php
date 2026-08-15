<?php

use HiveNova\Core\Config;
use HiveNova\Core\PlayerUtil;

use PHPUnit\Framework\TestCase;

class PlayerUtilAllowPlanetTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        Config::setInstance(new Config([
            'uni' => 1,
            'max_planets' => 15,
        ]), 1);

        $GLOBALS['resource'][124] = 'astrophysics_tech';
    }

    public function test_allowPlanetPosition_default_slot_with_level_one(): void
    {
        $user = ['universe' => 1, 'astrophysics_tech' => 1];
        $this->assertTrue(PlayerUtil::allowPlanetPosition(5, $user));
    }

    public function test_allowPlanetPosition_edge_slot_requires_level_eight(): void
    {
        $userLow = ['universe' => 1, 'astrophysics_tech' => 2];
        $userHigh = ['universe' => 1, 'astrophysics_tech' => 8];
        $this->assertFalse(PlayerUtil::allowPlanetPosition(1, $userLow));
        $this->assertTrue(PlayerUtil::allowPlanetPosition(1, $userHigh));
        $this->assertTrue(PlayerUtil::allowPlanetPosition(15, $userHigh));
    }

    public function test_allowPlanetPosition_penultimate_slots_require_level_six(): void
    {
        $user = ['universe' => 1, 'astrophysics_tech' => 5];
        $this->assertFalse(PlayerUtil::allowPlanetPosition(2, $user));
        $this->assertFalse(PlayerUtil::allowPlanetPosition(14, $user));

        $user['astrophysics_tech'] = 6;
        $this->assertTrue(PlayerUtil::allowPlanetPosition(2, $user));
        $this->assertTrue(PlayerUtil::allowPlanetPosition(14, $user));
    }

    public function test_allowPlanetPosition_inner_edge_slots_require_level_four(): void
    {
        $user = ['universe' => 1, 'astrophysics_tech' => 3];
        $this->assertFalse(PlayerUtil::allowPlanetPosition(3, $user));
        $this->assertFalse(PlayerUtil::allowPlanetPosition(13, $user));

        $user['astrophysics_tech'] = 4;
        $this->assertTrue(PlayerUtil::allowPlanetPosition(3, $user));
        $this->assertTrue(PlayerUtil::allowPlanetPosition(13, $user));
    }
}
