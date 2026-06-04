<?php

use HiveNova\Core\Config;
use HiveNova\Core\PlayerUtil;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PlayerUtilCreatePlayerTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        if (!defined('DEFAULT_THEME')) {
            define('DEFAULT_THEME', 'hive');
        }

        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        Config::setInstance($this->makeCreatePlayerConfig(), 1);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function makeCreatePlayerConfig(array $overrides = []): Config
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

    public function testCreatePlayerWithExplicitPositionReturnsUserAndPlanetIds(): void
    {
        $this->fake->planetPositionCount = 0;

        [$userId, $planetId] = PlayerUtil::createPlayer(
            1,
            'testuser',
            'hashed-password',
            'test@example.com',
            'testhive',
            'en',
            2,
            100,
            8,
            'Homeworld',
        );

        $this->assertSame(100, $userId);
        $this->assertSame(200, $planetId);
        $this->assertArrayHasKey(100, $this->fake->achievement->users);
        $this->assertArrayHasKey(200, $this->fake->planetRowsById);
        $this->assertSame(100, $this->fake->planetRowsById[200]['id_owner']);
        $this->assertSame(2, $this->fake->planetRowsById[200]['galaxy']);
        $this->assertSame(100, $this->fake->planetRowsById[200]['system']);
        $this->assertSame(8, $this->fake->planetRowsById[200]['planet']);
    }

    public function testCreatePlayerThrowsWhenCheckPositionInvalid(): void
    {
        $this->fake->planetPositionCount = 0;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Try to create a planet at position: 0:1:1!');

        PlayerUtil::createPlayer(
            1,
            'badpos',
            'pw',
            'bad@example.com',
            'badhive',
            'en',
            0,
            1,
            1,
        );
    }

    public function testCreatePlayerThrowsWhenPositionNotFree(): void
    {
        $this->fake->planetPositionCount = 1;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Position is not empty: 2:100:8!');

        PlayerUtil::createPlayer(
            1,
            'occupied',
            'pw',
            'occ@example.com',
            'occhive',
            'en',
            2,
            100,
            8,
        );
    }
}
