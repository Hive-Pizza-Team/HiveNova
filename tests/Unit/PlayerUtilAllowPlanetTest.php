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
    }
}
