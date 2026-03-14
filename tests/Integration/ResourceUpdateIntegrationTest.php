<?php

class ResourceUpdateIntegrationTest extends IntegrationTestCase
{
    private static int $userId   = 0;
    private static int $planetId = 0;

    public static function setUpBeforeClass(): void
    {
        $username = self::makeUniqueUsername('ru_test');
        [self::$userId, self::$planetId] = self::createTestPlayer($username, 4, 1, 8);
    }

    private function fetchPlanet(): array
    {
        $db  = Database::get();
        $sql = 'SELECT * FROM %%PLANETS%% WHERE id = :id;';
        return $db->selectSingle($sql, [':id' => self::$planetId]);
    }

    private function fetchUser(): array
    {
        $db   = Database::get();
        $sql  = 'SELECT * FROM %%USERS%% WHERE id = :id;';
        $user = $db->selectSingle($sql, [':id' => self::$userId]);
        // ResourceUpdate needs computed 'factor' key; use neutral defaults
        $user['factor'] = ['Resource' => 0, 'Energy' => 0, 'ResourceStorage' => 0, 'Expedition' => 0];
        return $user;
    }

    private function makeResourceUpdate(): ResourceUpdate
    {
        // Get a fresh reslist/resource/pricelist/ProdGrid from the DB cache.
        // We cannot rely on the global $reslist from bootstrap because PHPUnit
        // may have re-scoped it or vars.php's extract() may have been lost.
        $cache = Cache::get();
        $cache->add('vars', 'VarsBuildCache');
        $vars = $cache->getData('vars');

        // ReBuildCache uses global $ProdGrid; ensure it is populated
        $GLOBALS['ProdGrid'] = $vars['ProdGrid'] ?? [];

        $rl = $vars['reslist'] ?? [];
        $rl['resstype'][1] = [901, 902, 903];
        $rl['resstype'][2] = [911];
        $rl['resstype'][3] = [921];

        $ru = new ResourceUpdate();
        $ru->setResourceData($vars['resource'] ?? [], $rl);
        return $ru;
    }

    public function testUpdateResourceWithNoPlanetTimeDoesNotChange(): void
    {
        $db     = Database::get();
        $planet = $this->fetchPlanet();
        $user   = $this->fetchUser();

        // Set last_update to now so no production time elapses
        $now = TIMESTAMP;
        $db->update('UPDATE %%PLANETS%% SET last_update = :t WHERE id = :id;', [':t' => $now, ':id' => self::$planetId]);
        $planet['last_update'] = $now;

        $metalBefore = $planet['metal'];

        $ru = $this->makeResourceUpdate();
        $ru->setData($user, $planet);
        $ru->UpdateResource($now, false);
        [, $updatedPlanet] = $ru->getData();

        $this->assertEquals($metalBefore, $updatedPlanet['metal'], 'No time elapsed — metal should not change');
    }

    public function testUpdateResourceAccumulatesMetal(): void
    {
        $db     = Database::get();
        $planet = $this->fetchPlanet();
        $user   = $this->fetchUser();

        // Set metal mine level 5 + solar plant level 10 (provides enough energy),
        // and give a large storage cap so ReBuildCache computes non-zero metal_perhour.
        $storageMax = 500000;
        $oneHourAgo = TIMESTAMP - 3600;
        $db->update(
            'UPDATE %%PLANETS%% SET metal = 0, metal_mine = 5, solar_plant = 10, metal_max = :mx, last_update = :lu WHERE id = :id;',
            [':mx' => $storageMax, ':lu' => $oneHourAgo, ':id' => self::$planetId]
        );
        $planet = $this->fetchPlanet(); // re-fetch after update

        $ru = $this->makeResourceUpdate();
        $ru->setData($user, $planet);
        $ru->UpdateResource(TIMESTAMP, false);
        [, $updatedPlanet] = $ru->getData();

        $this->assertGreaterThan(0, $updatedPlanet['metal'], 'Metal should increase after 1 hour with level-5 metal mine');
    }

    public function testUpdateResourceCapsAtStorage(): void
    {
        $db   = Database::get();
        $user = $this->fetchUser();

        // First, run UpdateResource to get the formula-computed metal_max.
        // Then set metal to that cap and verify it doesn't grow further.
        $config = Config::get($user['universe']);
        $db->update(
            'UPDATE %%PLANETS%% SET metal = 0, metal_mine = 20, solar_plant = 20, last_update = :lu WHERE id = :id;',
            [':lu' => TIMESTAMP - 60, ':id' => self::$planetId]
        );
        $planet = $this->fetchPlanet();

        // First pass: let ReBuildCache compute the real metal_max
        $ru = $this->makeResourceUpdate();
        $ru->setData($user, $planet);
        $ru->UpdateResource(TIMESTAMP, false);
        [, $firstPass] = $ru->getData();
        $computedMax = $firstPass['metal_max'];

        // If metal_max is 0 (no storage building), skip this test
        if ($computedMax <= 0) {
            $this->markTestSkipped('Planet has no metal storage — cannot test storage cap');
        }

        $cap = $computedMax * $config->max_overflow;

        // Set metal to exactly the cap and re-run with high production
        $db->update(
            'UPDATE %%PLANETS%% SET metal = :m, metal_mine = 20, solar_plant = 20, last_update = :lu WHERE id = :id;',
            [':m' => $cap, ':lu' => TIMESTAMP - 3600, ':id' => self::$planetId]
        );
        $planet = $this->fetchPlanet();

        $ru2 = $this->makeResourceUpdate();
        $ru2->setData($user, $planet);
        $ru2->UpdateResource(TIMESTAMP, false);
        [, $updatedPlanet] = $ru2->getData();

        $this->assertLessThanOrEqual(
            $cap + 1,
            $updatedPlanet['metal'],
            'Metal must not exceed storage cap after one hour'
        );
    }
}
