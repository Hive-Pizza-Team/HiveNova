<?php

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton between tests
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    // -----------------------------------------------------------------------
    // __construct + __get
    // -----------------------------------------------------------------------

    public function testConstructAndGetReturnsValue(): void
    {
        $config = new Config(['game_speed' => 3, 'uni' => 1]);
        $this->assertSame(3, $config->game_speed);
        $this->assertSame(1, $config->uni);
    }

    public function testGetThrowsOnUnknownKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $_ = $config->no_such_key;
    }

    public function testGetReturnsStringValue(): void
    {
        $config = new Config(['game_name' => 'HiveNova', 'uni' => 1]);
        $this->assertSame('HiveNova', $config->game_name);
    }

    // -----------------------------------------------------------------------
    // __set + __isset
    // -----------------------------------------------------------------------

    public function testSetUpdatesExistingKey(): void
    {
        $config = new Config(['game_speed' => 1, 'uni' => 1]);
        $config->game_speed = 5;
        $this->assertSame(5, $config->game_speed);
    }

    public function testSetThrowsOnUnknownKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $config->no_such_key = 99;
    }

    public function testIssetReturnsTrueForExistingKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->assertTrue(isset($config->game_speed));
    }

    public function testIssetReturnsFalseForMissingKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->assertFalse(isset($config->no_such_key));
    }

    // -----------------------------------------------------------------------
    // getGlobalConfigKeys
    // -----------------------------------------------------------------------

    public function testGetGlobalConfigKeysReturnsNonEmptyArray(): void
    {
        $keys = Config::getGlobalConfigKeys();
        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);
    }

    public function testGetGlobalConfigKeysContainsKnownEntries(): void
    {
        $keys = Config::getGlobalConfigKeys();
        $this->assertContains('game_name', $keys);
        $this->assertContains('VERSION', $keys);
        $this->assertContains('stat', $keys);
        $this->assertContains('mail_active', $keys);
    }

    // -----------------------------------------------------------------------
    // setInstance + get
    // -----------------------------------------------------------------------

    public function testSetInstanceAndGetReturnsCorrectInstance(): void
    {
        $config = new Config(['fleet_speed' => 7500, 'uni' => 1]);
        Config::setInstance($config, 1);

        $retrieved = Config::get(1);
        $this->assertSame(7500, $retrieved->fleet_speed);
    }

    public function testSetInstanceWithNullUniverseDefaultsToOne(): void
    {
        $config = new Config(['game_speed' => 42, 'uni' => 1]);
        Config::setInstance($config);  // null → key 1

        $retrieved = Config::get(1);
        $this->assertSame(42, $retrieved->game_speed);
    }

    public function testGetThrowsForUnknownUniverse(): void
    {
        $config = new Config(['uni' => 1]);
        Config::setInstance($config, 1);

        $this->expectException(Exception::class);
        Config::get(999);
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        Config::setInstance(new Config(['game_speed' => 1, 'uni' => 1]), 1);
        Config::setInstance(new Config(['game_speed' => 2, 'uni' => 2]), 2);

        $this->assertSame(1, Config::get(1)->game_speed);
        $this->assertSame(2, Config::get(2)->game_speed);
    }

    // -----------------------------------------------------------------------
    // save — no updateRecords path (no DB call)
    // -----------------------------------------------------------------------

    public function testSaveReturnsTrueWhenNothingToUpdate(): void
    {
        $config = new Config(['uni' => 1, 'game_speed' => 1]);
        // No __set calls → updateRecords is empty → save() short-circuits
        $this->assertTrue($config->save());
    }

    // -----------------------------------------------------------------------
    // getAll — always throws
    // -----------------------------------------------------------------------

    public function testGetAllThrowsDeprecatedException(): void
    {
        $this->expectException(Exception::class);
        Config::getAll();
    }

    // -----------------------------------------------------------------------
    // __set records change (still no DB if save not called)
    // -----------------------------------------------------------------------

    public function testSetUpdatesValueAndIsVisibleViaGet(): void
    {
        $config = new Config(['score' => 100, 'uni' => 1]);
        $config->score = 200;
        $this->assertSame(200, $config->score);
    }

    public function testMultipleSetCallsAllRecorded(): void
    {
        $config = new Config(['a' => 1, 'b' => 2, 'uni' => 1]);
        $config->a = 10;
        $config->b = 20;
        $this->assertSame(10, $config->a);
        $this->assertSame(20, $config->b);
    }

    // -----------------------------------------------------------------------
    // Universe integration: setInstance + get uses Universe::current()
    // -----------------------------------------------------------------------

    public function testGetWithNoArgUsesUniverseCurrent(): void
    {
        // MODE='INSTALL' → Universe::current() returns ROOT_UNI=1
        $config = new Config(['game_speed' => 9, 'uni' => 1]);
        Config::setInstance($config, 1);

        // Config::get() with no arg → universe=0 → Universe::current()=1
        $retrieved = Config::get();
        $this->assertSame(9, $retrieved->game_speed);
    }
}
