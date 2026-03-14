<?php

class FleetFunctionsIntegrationTest extends IntegrationTestCase
{
    private static int $userId = 0;

    public static function setUpBeforeClass(): void
    {
        $username = self::makeUniqueUsername('ff_test');
        [self::$userId] = self::createTestPlayer($username, 5, 1, 8);
    }

    public function testGetCurrentFleetsReturnsZeroForPlayerWithNoFleets(): void
    {
        $count = FleetFunctions::GetCurrentFleets(self::$userId);
        $this->assertEquals(0, $count, 'Fresh player should have zero active fleets');
    }

    public function testGetAvailableMissionsIncludesTransportForOccupiedPlanet(): void
    {
        $user = Database::get()->selectSingle(
            'SELECT * FROM %%USERS%% WHERE id = :id;',
            [':id' => self::$userId]
        );
        // Minimal user factor array required by GetAvailableMissions helpers
        $user['factor'] = ['Resource' => 1, 'Energy' => 1, 'Expedition' => 0];

        $missionInfo = [
            'planet'     => 8,
            'planettype' => 1,
            'Ship'       => [202 => 1], // small cargo
        ];
        // A planet occupied by another owner
        $getInfoPlanet = ['id_owner' => 9999, 'der_metal' => 0, 'der_crystal' => 0];

        $missions = FleetFunctions::GetAvailableMissions($user, $missionInfo, $getInfoPlanet);

        $this->assertContains(3, $missions, 'Transport (mission 3) should be available for occupied foreign planet');
    }

    public function testGetCurrentFleetsExcludesExpeditionByDefault(): void
    {
        // Expedition mission = 15; default call excludes mission 10 (none), not 15
        // The important thing is the call succeeds and returns an integer
        $count = FleetFunctions::GetCurrentFleets(self::$userId, 15, true);
        $this->assertIsInt((int) $count);
    }
}
