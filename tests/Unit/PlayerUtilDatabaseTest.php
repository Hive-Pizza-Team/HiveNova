<?php

use HiveNova\Core\Config;
use HiveNova\Core\PlayerUtil;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PlayerUtilDatabaseTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        if (!defined('DEFAULT_THEME')) {
            define('DEFAULT_THEME', 'hive');
        }
        if (!defined('ROOT_USER')) {
            define('ROOT_USER', 1);
        }

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function makePlanetConfig(array $overrides = []): Config
    {
        return new Config(array_merge([
            'uni'                  => 1,
            'timezone'             => 'UTC',
            'darkmatter_start'     => 0,
            'metal_start'          => 500,
            'crystal_start'        => 500,
            'deuterium_start'      => 0,
            'initial_fields'       => 163,
            'planet_factor'        => 1.0,
            'max_galaxy'           => 5,
            'max_system'           => 499,
            'max_planets'          => 15,
            'LastSettedGalaxyPos'  => 1,
            'LastSettedSystemPos'  => 1,
            'LastSettedPlanetPos'  => 1,
            'users_amount'         => 0,
        ], $overrides));
    }

    public function testIsPositionFreeWhenNoPlanetAtCoords(): void
    {
        $this->fake->planetPositionCount = 0;
        $this->assertTrue(PlayerUtil::isPositionFree(1, 2, 100, 5, 1));
    }

    public function testIsPositionFreeReturnsFalseWhenOccupied(): void
    {
        $this->fake->planetPositionCount = 1;
        $this->assertFalse(PlayerUtil::isPositionFree(1, 2, 100, 5, 1));
    }

    public function testSendMessageInsertsRow(): void
    {
        $universe = defined('ROOT_UNI') ? ROOT_UNI : 1;
        PlayerUtil::sendMessage(7, 0, 'Tower', 5, 'Subject', 'Body text', TIMESTAMP, null, 1, $universe);

        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame(7, $this->fake->achievement->messages[0][':userId']);
        $this->assertSame('Body text', $this->fake->achievement->messages[0][':text']);
    }

    public function testDeletePlayerThrowsForRootUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Superuser #1 can't be deleted!");

        PlayerUtil::deletePlayer(ROOT_USER);
    }

    public function testDeletePlayerReturnsFalseWhenUserMissing(): void
    {
        $this->assertFalse(PlayerUtil::deletePlayer(9999));
    }

    public function testDeletePlayerReturnsTrueAndDeletesRelatedRows(): void
    {
        $this->fake->achievement->users[42] = [
            'id'       => 42,
            'universe' => 1,
            'ally_id'  => 0,
        ];

        $this->assertTrue(PlayerUtil::deletePlayer(42));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('%%MESSAGES%%', $log);
        $this->assertStringContainsString('%%PLANETS%%', $log);
        $this->assertStringContainsString('%%USERS%%', $log);
        $this->assertStringContainsString('%%STATPOINTS%%', $log);
    }

    public function testDeletePlayerWithAllianceTriggersAllianceCleanup(): void
    {
        $this->fake->achievement->users[50] = [
            'id'       => 50,
            'universe' => 1,
            'ally_id'  => 3,
        ];

        $this->assertTrue(PlayerUtil::deletePlayer(50));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('%%ALLIANCE%%', $log);
    }

    public function testCreatePlayerAutoPlacementWhenCoordsOmitted(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 0;

        [$userId, $planetId] = PlayerUtil::createPlayer(
            1,
            'autoplace',
            'hashed',
            'auto@example.com',
            'autohive',
            'en',
        );

        $this->assertSame(100, $userId);
        $this->assertSame(200, $planetId);
        $this->assertArrayHasKey(100, $this->fake->achievement->users);
    }

    public function testCreatePlanetInsertsColonyAtFreePosition(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 0;

        $planetId = PlayerUtil::createPlanet(2, 100, 8, 1, 77, 'Outpost', false, 0);

        $this->assertSame(200, $planetId);
        $this->assertArrayHasKey(200, $this->fake->planetRowsById);
        $this->assertSame(77, $this->fake->planetRowsById[200]['id_owner']);
        $this->assertSame('Outpost', $this->fake->planetInserts[0][':name']);
    }

    public function testCreatePlanetInsertsHomePlanetWithInitialFields(): void
    {
        Config::setInstance($this->makePlanetConfig(['initial_fields' => 163]), 1);
        $this->fake->planetPositionCount = 0;

        $planetId = PlayerUtil::createPlanet(1, 50, 5, 1, 88, 'Homeworld', true, 0);

        $this->assertSame(200, $planetId);
        $this->assertSame(88, $this->fake->planetRowsById[200]['id_owner']);
        $this->assertSame('Homeworld', $this->fake->planetInserts[0][':name']);
        $this->assertSame(163, $this->fake->planetInserts[0][':maxFields']);
    }

    public function testCreatePlanetThrowsWhenPositionInvalid(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 0;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Try to create a planet at position: 99:1:1!');

        PlayerUtil::createPlanet(99, 1, 1, 1, 1);
    }

    public function testCreatePlanetThrowsWhenPositionOccupied(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 1;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Position is not empty: 2:100:8!');

        PlayerUtil::createPlanet(2, 100, 8, 1, 1);
    }

    public function testDeletePlanetThrowsWhenPlanetMissing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not found planet #404!');

        PlayerUtil::deletePlanet(404);
    }

    public function testDeletePlanetRemovesPlanetRow(): void
    {
        $this->fake->planetRowsById[300] = [
            'id'          => 300,
            'id_owner'    => 7,
            'planet_type' => 1,
            'id_luna'     => 0,
        ];

        $this->assertTrue(PlayerUtil::deletePlanet(300));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('%%PLANETS%%', $log);
        $this->assertStringContainsString('id_luna', $log);
    }

    public function testDeletePlanetMoonTypeUsesMoonDeletionPath(): void
    {
        $this->fake->planetRowsById[301] = [
            'id'          => 301,
            'id_owner'    => 7,
            'planet_type' => 3,
            'id_luna'     => 0,
        ];

        $this->assertTrue(PlayerUtil::deletePlanet(301));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('DELETE FROM %%PLANETS%% WHERE id = :planetId', $log);
        $this->assertStringNotContainsString('OR id_luna = :planetId', $log);
    }
}
