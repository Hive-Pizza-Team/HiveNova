<?php

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\InstallerUpgrade;
use HiveNova\Core\Migrator;
use HiveNova\Core\SQLDumper;

use PHPUnit\Framework\TestCase;

class RecordingSqlDumper extends SQLDumper
{
    /** @var list<string> */
    public array $dumpedTables = [];

    public string $dumpedPath = '';

    public int $dumpCalls = 0;

    public int $restoreCalls = 0;

    public ?Exception $restoreException = null;

    public function dumpTablesToFile($dbTables, $filePath)
    {
        $this->dumpCalls++;
        $this->dumpedTables = array_values($dbTables);
        $this->dumpedPath   = $filePath;

        return true;
    }

    public function restoreDatabase($filePath)
    {
        $this->restoreCalls++;
        if ($this->restoreException instanceof Exception) {
            throw $this->restoreException;
        }
    }
}

class RecordingMigrator extends Migrator
{
    public int $runCalls = 0;

    public int $updateCalls = 0;

    public ?Exception $runException = null;

    /** @var list<array<string, mixed>> */
    public array $toApply = [];

    public function run(bool $dryRun = false): array
    {
        $this->runCalls++;
        if ($this->runException instanceof Exception) {
            throw $this->runException;
        }

        return $this->toApply;
    }

    public function updateVersion(): void
    {
        $this->updateCalls++;
    }
}

class InstallerUpgradeTest extends TestCase
{
    private string $migrationsDir;

    protected function setUp(): void
    {
        $this->migrationsDir = sys_get_temp_dir() . '/installer_upgrade_' . uniqid();
        mkdir($this->migrationsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationsDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->migrationsDir);
    }

    private function addMigration(int $rev, string $ext, string $body): void
    {
        file_put_contents("{$this->migrationsDir}/migration_{$rev}.{$ext}", $body);
    }

    private function makeUpgrade(
        DatabaseInterface $db,
        SQLDumper $dumper,
        ?Migrator $migrator = null,
        int $required = 5,
        string $backupDir = '',
    ): InstallerUpgrade {
        return new InstallerUpgrade(
            $db,
            $dumper,
            $this->migrationsDir,
            'uni1_',
            $required,
            $backupDir !== '' ? $backupDir : sys_get_temp_dir(),
            $migrator,
        );
    }

    private function recordingMigrator(): RecordingMigrator
    {
        return new RecordingMigrator($this->createMock(PDO::class), $this->migrationsDir, 'uni1_', 5);
    }

    public function testPreviewListsPendingSqlAndPhpInOrderIgnoringJunk(): void
    {
        file_put_contents("{$this->migrationsDir}/README.txt", 'ignore');
        $this->addMigration(1, 'sql', "ALTER TABLE %PREFIX%users ADD x INT;\n");
        $this->addMigration(3, 'php', "<?php\n");
        $this->addMigration(2, 'sql', "ALTER TABLE %PREFIX%planets ADD y INT;\n");
        $this->addMigration(9, 'sql', "ALTER TABLE too_new INT;\n");

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn('1');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getHandle')->willReturn($pdo);

        $upgrade = $this->makeUpgrade($db, new RecordingSqlDumper(), required: 5);
        $preview = $upgrade->preview();

        $this->assertSame(
            ['migration_2.sql', 'migration_3.php'],
            array_keys($preview)
        );
        $this->assertStringContainsString('uni1_planets', $preview['migration_2.sql']);
        $this->assertSame('(PHP migration)', $preview['migration_3.php']);
    }

    public function testMigratorFailsFastWhenHandleIsNull(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getHandle')->willReturn(null);

        $upgrade = $this->makeUpgrade($db, new RecordingSqlDumper());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database PDO handle is not available.');
        $upgrade->migrator();
    }

    public function testListPrefixedTablesFiltersByPrefix(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('nativeQuery')->willReturn([
            ['Name' => 'uni1_users'],
            ['Name' => 'other_users'],
            ['Name' => 'uni1_planets'],
        ]);

        $upgrade = $this->makeUpgrade($db, new RecordingSqlDumper(), $this->recordingMigrator());
        $this->assertSame(['uni1_users', 'uni1_planets'], $upgrade->listPrefixedTables('gamedb'));
    }

    public function testApplyThrowsWhenNoPrefixedTables(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('nativeQuery')->willReturn([['Name' => 'other_users']]);

        $dumper   = new RecordingSqlDumper();
        $migrator = $this->recordingMigrator();
        $upgrade  = $this->makeUpgrade($db, $dumper, $migrator);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No tables found for dump.');
        try {
            $upgrade->apply('gamedb');
        } finally {
            $this->assertSame(0, $dumper->dumpCalls);
            $this->assertSame(0, $migrator->runCalls);
        }
    }

    public function testApplyDumpsThenRunsWithoutRestore(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('nativeQuery')->willReturn([['Name' => 'uni1_users']]);

        $dumper   = new RecordingSqlDumper();
        $migrator = $this->recordingMigrator();
        $migrator->toApply = [
            ['rev' => 4, 'filename' => 'migration_4.sql', 'path' => '', 'extension' => 'sql'],
        ];

        $upgrade = $this->makeUpgrade($db, $dumper, $migrator, backupDir: '/tmp/backups');
        $result  = $upgrade->apply('gamedb');

        $this->assertSame(1, $dumper->dumpCalls);
        $this->assertSame(['uni1_users'], $dumper->dumpedTables);
        $this->assertStringContainsString('2MoonsBackup_', $dumper->dumpedPath);
        $this->assertSame(1, $migrator->runCalls);
        $this->assertSame(1, $migrator->updateCalls);
        $this->assertSame(0, $dumper->restoreCalls);
        $this->assertSame(4, $result['revision']);
        $this->assertCount(1, $result['applied']);
    }

    public function testApplyRestoresBackupWhenRunThrows(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('nativeQuery')->willReturn([['Name' => 'uni1_users']]);

        $dumper   = new RecordingSqlDumper();
        $migrator = $this->recordingMigrator();
        $migrator->runException = new Exception('boom');

        $upgrade = $this->makeUpgrade($db, $dumper, $migrator);

        try {
            $upgrade->apply('gamedb');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
            $this->assertStringContainsString('Backup restored.', $e->getMessage());
        }

        $this->assertSame(1, $dumper->dumpCalls);
        $this->assertSame(1, $dumper->restoreCalls);
        $this->assertSame(0, $migrator->updateCalls);
    }

    public function testApplyReportsRestoreFailure(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('nativeQuery')->willReturn([['Name' => 'uni1_users']]);

        $dumper = new RecordingSqlDumper();
        $dumper->restoreException = new Exception('disk full');
        $migrator = $this->recordingMigrator();
        $migrator->runException = new Exception('boom');

        $upgrade = $this->makeUpgrade($db, $dumper, $migrator);

        try {
            $upgrade->apply('gamedb');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertStringContainsString('Can not restore backup', $e->getMessage());
            $this->assertStringContainsString('disk full', $e->getMessage());
        }
    }
}
