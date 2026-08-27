<?php

use HiveNova\Core\Config;
use HiveNova\Core\Cronjob;
use HiveNova\Core\Database;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class CronjobExecuteTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function setUp(): void
    {
        ReferralCronjobExecuteStub::$ran = false;
        ReferralCronjobExecuteStub::$throwOnRun = false;

        $fake = new FakeDatabase();
        $fake->achievement->cronjobClass = ReferralCronjobExecuteStub::class;
        $fake->achievement->cronjobActive = true;
        $this->swapDatabaseInstance($fake);

        Config::setInstance(new Config(['uni' => 1, 'ref_active' => 0]), 1);

        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_execute_runs_active_cronjob_class(): void
    {
        ReferralCronjobExecuteStub::$ran = false;

        Cronjob::execute(12);

        $this->assertTrue(ReferralCronjobExecuteStub::$ran);
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $this->assertTrue($fake->achievement->cronjobLockCleared);
    }

    public function test_execute_clears_lock_when_task_throws(): void
    {
        ReferralCronjobExecuteStub::$ran = false;
        ReferralCronjobExecuteStub::$throwOnRun = true;

        try {
            Cronjob::execute(12);
            $this->fail('Expected exception from cronjob task');
        } catch (\RuntimeException $e) {
            $this->assertSame('cron task failed', $e->getMessage());
        }

        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $this->assertTrue($fake->achievement->cronjobLockCleared);
        $this->assertFalse(ReferralCronjobExecuteStub::$ran);
    }
}

/**
 * Stub invoked by Cronjob::execute() during unit tests.
 */
class ReferralCronjobExecuteStub implements \HiveNova\Cronjob\CronjobTask
{
    public static bool $ran = false;
    public static bool $throwOnRun = false;

    public function run()
    {
        if (self::$throwOnRun) {
            throw new \RuntimeException('cron task failed');
        }
        self::$ran = true;
    }
}
