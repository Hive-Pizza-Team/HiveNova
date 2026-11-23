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

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function dueCronjobRow(array $overrides = []): array
    {
        return array_merge([
            'cronjobID' => 12,
            'isActive' => 1,
            'nextTime' => TIMESTAMP - 10,
            'lock' => null,
            'min' => '0',
            'hours' => '0',
            'dom' => '*',
            'month' => '*',
            'dow' => '*',
        ], $overrides);
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

    public function test_execute_clears_lock_when_class_cannot_be_instantiated(): void
    {
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $fake->achievement->cronjobClass = 'CronjobClassThatDoesNotExist';

        try {
            Cronjob::execute(12);
            $this->fail('Expected error from missing cronjob class');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('CronjobClassThatDoesNotExist', $e->getMessage());
        }

        $this->assertTrue($fake->achievement->cronjobLockCleared);
    }

    public function test_execute_takes_over_stale_lock(): void
    {
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $fake->achievement->cronjobLock = (string) (TIMESTAMP - Cronjob::LOCK_STALE_SECONDS - 1);

        Cronjob::execute(12);

        $this->assertTrue(ReferralCronjobExecuteStub::$ran);
        $this->assertTrue($fake->achievement->cronjobLockCleared);
    }

    public function test_execute_rejects_fresh_lock(): void
    {
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $fake->achievement->cronjobLock = (string) TIMESTAMP;

        try {
            Cronjob::execute(12);
            $this->fail('Expected already-locked exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('already locked', $e->getMessage());
        }

        $this->assertFalse(ReferralCronjobExecuteStub::$ran);
        $this->assertFalse($fake->achievement->cronjobLockCleared);
        $this->assertSame((string) TIMESTAMP, $fake->achievement->cronjobLock);
    }

    public function test_execute_rejects_manual_lock(): void
    {
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $fake->achievement->cronjobLock = Cronjob::LOCK_MANUAL;

        try {
            Cronjob::execute(12);
            $this->fail('Expected already-locked exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('already locked', $e->getMessage());
        }

        $this->assertFalse(ReferralCronjobExecuteStub::$ran);
        $this->assertSame(Cronjob::LOCK_MANUAL, $fake->achievement->cronjobLock);
    }

    public function test_get_need_todo_includes_unlocked_and_stale_locks(): void
    {
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $fake->achievement->cronjobRows = [
            $this->dueCronjobRow(['cronjobID' => 1, 'lock' => null]),
            $this->dueCronjobRow([
                'cronjobID' => 2,
                'lock' => (string) (TIMESTAMP - Cronjob::LOCK_STALE_SECONDS - 1),
            ]),
            $this->dueCronjobRow([
                'cronjobID' => 3,
                'lock' => md5('legacy-lock'),
            ]),
        ];

        $this->assertSame([1, 2, 3], Cronjob::getNeedTodoExecutedJobs());
    }

    public function test_get_need_todo_skips_fresh_and_manual_locks(): void
    {
        $fake = Database::get();
        $this->assertInstanceOf(FakeDatabase::class, $fake);
        $fake->achievement->cronjobRows = [
            $this->dueCronjobRow(['cronjobID' => 1, 'lock' => (string) TIMESTAMP]),
            $this->dueCronjobRow([
                'cronjobID' => 2,
                'lock' => (string) (TIMESTAMP - Cronjob::LOCK_STALE_SECONDS + 1),
            ]),
            $this->dueCronjobRow(['cronjobID' => 3, 'lock' => Cronjob::LOCK_MANUAL]),
            $this->dueCronjobRow(['cronjobID' => 4, 'nextTime' => TIMESTAMP + 60, 'lock' => null]),
        ];

        $this->assertSame([], Cronjob::getNeedTodoExecutedJobs());
    }

    public function test_lock_stale_helpers(): void
    {
        $now = 1_700_000_000;

        $this->assertFalse(Cronjob::isLockStale(null, $now));
        $this->assertFalse(Cronjob::isLockStale('', $now));
        $this->assertTrue(Cronjob::isLockAcquirable(null, $now));
        $this->assertTrue(Cronjob::isLockAcquirable('', $now));

        $this->assertFalse(Cronjob::isLockStale((string) $now, $now));
        $this->assertFalse(Cronjob::isLockStale((string) ($now - Cronjob::LOCK_STALE_SECONDS + 1), $now));
        $this->assertTrue(Cronjob::isLockStale((string) ($now - Cronjob::LOCK_STALE_SECONDS), $now));
        $this->assertTrue(Cronjob::isLockStale((string) ($now - Cronjob::LOCK_STALE_SECONDS - 1), $now));

        $this->assertFalse(Cronjob::isLockStale(Cronjob::LOCK_MANUAL, $now));
        $this->assertFalse(Cronjob::isLockAcquirable(Cronjob::LOCK_MANUAL, $now));

        $this->assertTrue(Cronjob::isLockStale(md5('legacy'), $now));
        $this->assertTrue(Cronjob::isLockAcquirable(md5('legacy'), $now));
    }

    public function test_admin_class_list_and_validation(): void
    {
        $dir = sys_get_temp_dir() . '/hivenova-cron-classes-' . uniqid('', true);
        $this->assertTrue(mkdir($dir, 0777, true));

        try {
            file_put_contents($dir . '/PushingDetectionCronjob.php', "<?php\n");
            file_put_contents($dir . '/DailyCronjob.php', "<?php\n");
            file_put_contents($dir . '/CronjobTask.php', "<?php\n");
            file_put_contents($dir . '/notes.txt', 'ignore');
            file_put_contents($dir . '/Legacy.class.php', "<?php\n");
            file_put_contents($dir . '/NotAJob.php', "<?php\n");

            $this->assertSame(
                ['DailyCronjob', 'PushingDetectionCronjob'],
                Cronjob::listAvailableClasses($dir)
            );

            $this->assertTrue(Cronjob::isValidClassName('PushingDetectionCronjob'));
            $this->assertTrue(Cronjob::isCronjobClassName('PushingDetectionCronjob'));
            $this->assertTrue(Cronjob::classFileExists('PushingDetectionCronjob', $dir));

            $this->assertFalse(Cronjob::isValidClassName('../Evil'));
            $this->assertFalse(Cronjob::isValidClassName('Foo/Bar'));
            $this->assertFalse(Cronjob::isValidClassName('Foo.php'));
            $this->assertFalse(Cronjob::isValidClassName('HiveNova\\Cronjob\\DailyCronjob'));
            $this->assertFalse(Cronjob::isValidClassName(''));
            $this->assertFalse(Cronjob::classFileExists('../Evil', $dir));
            $this->assertFalse(Cronjob::classFileExists('CronjobTask', $dir));
            $this->assertFalse(Cronjob::isCronjobClassName('CronjobTask'));
            $this->assertFalse(Cronjob::classFileExists('MissingCronjob', $dir));

            $this->assertSame(
                $dir . '/PushingDetectionCronjob.php',
                Cronjob::classFilePath('PushingDetectionCronjob', $dir)
            );
            $this->assertStringNotContainsString('cronjobs/', Cronjob::classFilePath('DailyCronjob'));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
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
