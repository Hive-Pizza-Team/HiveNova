<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\CleanerCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class CleanerCronjobRunTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function setUp(): void
    {
        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        if (!defined('SESSION_LIFETIME')) {
            define('SESSION_LIFETIME', 3600);
        }

        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);

        Config::setInstance(new Config([
            'uni' => 1,
            'del_oldstuff' => 7,
            'del_user_automatic' => 90,
            'del_user_manually' => 7,
            'message_delete_days' => 30,
        ]), 1);

        $ref = new ReflectionProperty(Universe::class, 'availableUniverses');
        $ref->setAccessible(true);
        $ref->setValue([1]);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_run_executes_cleanup_deletes(): void
    {
        $cron = new CleanerCronjob();
        $cron->run();

        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $this->assertGreaterThanOrEqual(5, count($fake->achievement->deleteLog));
        $this->assertTrue(
            (bool) array_filter(
                $fake->achievement->deleteLog,
                static fn (string $sql): bool => str_contains($sql, '%%MESSAGES%%')
            )
        );
    }
}
