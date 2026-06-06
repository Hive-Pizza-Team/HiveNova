<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;

/**
 * Saves and restores Database::setInstance() for PHPUnit tests.
 */
trait SwapDatabaseInstance
{
    private ?DatabaseInterface $savedDatabaseInstance = null;

    protected function swapDatabaseInstance(DatabaseInterface $fake): void
    {
        if ($this->savedDatabaseInstance === null) {
            $ref = new ReflectionClass(Database::class);
            $prop = $ref->getProperty('instance');
            $prop->setAccessible(true);
            $this->savedDatabaseInstance = $prop->getValue();
        }
        Database::setInstance($fake);
    }

    protected function restoreDatabaseInstance(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->savedDatabaseInstance);
        $this->savedDatabaseInstance = null;
    }
}
