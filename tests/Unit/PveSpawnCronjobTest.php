<?php

use HiveNova\Core\Config;
use HiveNova\Cronjob\PveSpawnCronjob;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PveSpawnCronjobTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => implode(';', array_fill(0, 50, 1)),
            'max_galaxy' => 1,
            'max_system' => 1,
            'max_planets' => 1,
        ]), 1);

        $available = new ReflectionProperty(\HiveNova\Core\Universe::class, 'availableUniverses');
        $available->setAccessible(true);
        $available->setValue([1]);
        $current = new ReflectionProperty(\HiveNova\Core\Universe::class, 'currentUniverse');
        $current->setAccessible(true);
        $current->setValue(1);
    }

    protected function tearDown(): void
    {
        foreach (['availableUniverses', 'currentUniverse', 'emulatedUniverse'] as $prop) {
            $ref = new ReflectionProperty(\HiveNova\Core\Universe::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null);
        }
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testRunSpawnsPackagesForAvailableUniverses(): void
    {
        $job = new PveSpawnCronjob();
        $this->assertTrue($job->run());
        $this->assertNotEmpty($this->fake->salvagePackages);
        $this->assertSame(1, (int) $this->fake->salvagePackages[0]['universe']);
    }
}
