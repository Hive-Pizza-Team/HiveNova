<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\StatBuilder;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class StatBuilderTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function setUp(): void
    {
        $fake = new FakeDatabase();
        $fake->achievement->configUniverses = [['uni' => 1], ['uni' => 2]];
        $this->swapDatabaseInstance($fake);

        Config::setInstance(new Config(['uni' => 1, 'stat' => 0, 'stat_level' => 2]), 1);
        Config::setInstance(new Config(['uni' => 2, 'stat' => 0, 'stat_level' => 2]), 2);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_constructor_loads_universes_from_config(): void
    {
        $builder = new StatBuilder();

        $ref = new ReflectionProperty(StatBuilder::class, 'Unis');
        $ref->setAccessible(true);
        $unis = $ref->getValue($builder);

        $this->assertSame([1, 2], $unis);
    }
}
