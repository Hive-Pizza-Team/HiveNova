<?php

use HiveNova\Core\Migrator;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Migrator class.
 *
 * All tests use a temporary migrations directory and a PDO mock, so no real
 * database connection is required.
 */
class MigratorTest extends TestCase
{
    private string $migrationsDir;

    protected function setUp(): void
    {
        $this->migrationsDir = sys_get_temp_dir() . '/migrator_test_' . uniqid();
        mkdir($this->migrationsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationsDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->migrationsDir);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeMigrator(PDO $pdo, int $currentVersion = 0, int $required = 5): Migrator
    {
        return new Migrator($pdo, $this->migrationsDir, 'tst_', $required);
    }

    private function mockPdo(): PDO
    {
        return $this->createMock(PDO::class);
    }

    private function addMigration(int $rev, string $sql = "ALTER TABLE foo ADD COLUMN bar INT;\n"): void
    {
        file_put_contents("{$this->migrationsDir}/migration_{$rev}.sql", $sql);
    }

    // -----------------------------------------------------------------------
    // getCurrentVersion()
    // -----------------------------------------------------------------------

    public function testGetCurrentVersionReturnsVersionFromDb(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn('7');

        $pdo = $this->mockPdo();
        $pdo->method('query')->willReturn($stmt);

        $migrator = $this->makeMigrator($pdo);
        $this->assertSame(7, $migrator->getCurrentVersion());
    }

    public function testGetCurrentVersionReturnsZeroOnPdoException(): void
    {
        $pdo = $this->mockPdo();
        $pdo->method('query')->willThrowException(new PDOException('no such table'));

        $migrator = $this->makeMigrator($pdo);
        $this->assertSame(0, $migrator->getCurrentVersion());
    }

    // -----------------------------------------------------------------------
    // getPendingMigrations()
    // -----------------------------------------------------------------------

    public function testGetPendingMigrationsReturnsOnlyUnapplied(): void
    {
        $this->addMigration(1); // already applied
        $this->addMigration(2); // pending
        $this->addMigration(3); // pending

        $migrator = $this->makeMigrator($this->mockPdo(), required: 5);
        $pending  = $migrator->getPendingMigrations(currentVersion: 1);

        $this->assertCount(2, $pending);
        $this->assertSame(2, $pending[0]['rev']);
        $this->assertSame(3, $pending[1]['rev']);
    }

    public function testGetPendingMigrationsExcludesBeyondRequired(): void
    {
        $this->addMigration(3);
        $this->addMigration(4);
        $this->addMigration(5);

        $migrator = $this->makeMigrator($this->mockPdo(), required: 4);
        $pending  = $migrator->getPendingMigrations(currentVersion: 2);

        // rev 5 exceeds required version 4 → excluded
        $this->assertCount(2, $pending);
        $this->assertSame(3, $pending[0]['rev']);
        $this->assertSame(4, $pending[1]['rev']);
    }

    public function testGetPendingMigrationsReturnsEmptyWhenUpToDate(): void
    {
        $this->addMigration(3);

        $migrator = $this->makeMigrator($this->mockPdo(), required: 5);
        $pending  = $migrator->getPendingMigrations(currentVersion: 5);

        $this->assertEmpty($pending);
    }

    public function testGetPendingMigrationsIgnoresNonMigrationFiles(): void
    {
        file_put_contents("{$this->migrationsDir}/README.txt", 'ignore me');
        file_put_contents("{$this->migrationsDir}/migration_bad.sql", '');
        $this->addMigration(2);

        $migrator = $this->makeMigrator($this->mockPdo(), required: 5);
        $pending  = $migrator->getPendingMigrations(currentVersion: 0);

        $this->assertCount(1, $pending);
        $this->assertSame(2, $pending[0]['rev']);
    }

    public function testGetPendingMigrationsSortsByRevision(): void
    {
        // Write files out of order
        $this->addMigration(5);
        $this->addMigration(2);
        $this->addMigration(4);

        $migrator = $this->makeMigrator($this->mockPdo(), required: 5);
        $pending  = $migrator->getPendingMigrations(currentVersion: 0);

        $revisions = array_column($pending, 'rev');
        $this->assertSame([2, 4, 5], $revisions);
    }

    public function testGetPendingMigrationsSetsCorrectMetadata(): void
    {
        $this->addMigration(3);

        $migrator = $this->makeMigrator($this->mockPdo(), required: 5);
        $pending  = $migrator->getPendingMigrations(currentVersion: 0);

        $m = $pending[0];
        $this->assertSame(3, $m['rev']);
        $this->assertSame('migration_3.sql', $m['filename']);
        $this->assertSame('sql', $m['extension']);
        $this->assertStringEndsWith('migration_3.sql', $m['path']);
    }

    // -----------------------------------------------------------------------
    // parseSql()
    // -----------------------------------------------------------------------

    public function testParseSqlReplacesPrefix(): void
    {
        $migrator = new Migrator($this->mockPdo(), $this->migrationsDir, 'pfx_', 1);
        $queries  = $migrator->parseSql("CREATE TABLE %PREFIX%foo (id INT);\n");

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('pfx_foo', $queries[0]);
        $this->assertStringNotContainsString('%PREFIX%', $queries[0]);
    }

    public function testParseSqlSplitsMultipleStatements(): void
    {
        $sql = "INSERT INTO t VALUES (1);\nINSERT INTO t VALUES (2);\n";

        $migrator = new Migrator($this->mockPdo(), $this->migrationsDir, '', 1);
        $queries  = $migrator->parseSql($sql);

        $this->assertCount(2, $queries);
    }

    public function testParseSqlFiltersEmptySegments(): void
    {
        $sql = "\n\nCREATE TABLE t (id INT);\n\n";

        $migrator = new Migrator($this->mockPdo(), $this->migrationsDir, '', 1);
        $queries  = $migrator->parseSql($sql);

        $this->assertCount(1, $queries);
    }

    public function testParseSqlTrimsWhitespace(): void
    {
        $sql = "  ALTER TABLE foo ADD COLUMN bar INT  ;\n";

        $migrator = new Migrator($this->mockPdo(), $this->migrationsDir, '', 1);
        $queries  = $migrator->parseSql($sql);

        $this->assertSame('ALTER TABLE foo ADD COLUMN bar INT', $queries[0]);
    }

    // -----------------------------------------------------------------------
    // applySqlMigration()
    // -----------------------------------------------------------------------

    public function testApplySqlMigrationExecutesQueries(): void
    {
        $sql = "CREATE TABLE tst_foo (id INT);\nALTER TABLE tst_foo ADD col2 VARCHAR(10);\n";
        $this->addMigration(1, $sql);

        $pdo = $this->mockPdo();
        $pdo->expects($this->exactly(2))->method('exec');

        $migrator  = $this->makeMigrator($pdo);
        $migration = $migrator->getPendingMigrations(0)[0];
        $migrator->applySqlMigration($migration);
    }

    public function testApplySqlMigrationContinuesOnQueryError(): void
    {
        $sql = "BAD QUERY;\nGOOD QUERY;\n";
        $this->addMigration(1, $sql);

        $pdo = $this->mockPdo();
        $pdo->expects($this->exactly(2))
            ->method('exec')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new PDOException('syntax error')),
                1
            );

        $migrator  = $this->makeMigrator($pdo);
        $migration = $migrator->getPendingMigrations(0)[0];

        // Must not throw — errors are logged and skipped
        $migrator->applySqlMigration($migration);
        $this->addToAssertionCount(1);
    }

    // -----------------------------------------------------------------------
    // updateVersion()
    // -----------------------------------------------------------------------

    public function testUpdateVersionExecutesCorrectSql(): void
    {
        $pdo = $this->mockPdo();
        $pdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('dbVersion = 5'));

        $migrator = new Migrator($pdo, $this->migrationsDir, 'tst_', 5);
        $migrator->updateVersion();
    }

    // -----------------------------------------------------------------------
    // run()
    // -----------------------------------------------------------------------

    public function testRunReturnsPendingWithoutApplyingOnDryRun(): void
    {
        $this->addMigration(1);
        $this->addMigration(2);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn('0');

        $pdo = $this->mockPdo();
        $pdo->method('query')->willReturn($stmt);
        // exec must never be called in dry-run mode
        $pdo->expects($this->never())->method('exec');

        $migrator = new Migrator($pdo, $this->migrationsDir, 'tst_', 5);
        $applied  = $migrator->run(dryRun: true);

        $this->assertCount(2, $applied);
    }

    public function testRunReturnsEmptyWhenAlreadyUpToDate(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn('5');

        $pdo = $this->mockPdo();
        $pdo->method('query')->willReturn($stmt);
        $pdo->expects($this->never())->method('exec');

        $migrator = new Migrator($pdo, $this->migrationsDir, 'tst_', 5);
        $applied  = $migrator->run();

        $this->assertEmpty($applied);
    }

    public function testRunCallsUpdateVersionAfterMigrations(): void
    {
        $this->addMigration(1, "SELECT 1;\n");

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn('0');

        $pdo = $this->mockPdo();
        $pdo->method('query')->willReturn($stmt);

        $execCalls = [];
        $pdo->method('exec')->willReturnCallback(function (string $sql) use (&$execCalls) {
            $execCalls[] = $sql;
            return 0;
        });

        $migrator = new Migrator($pdo, $this->migrationsDir, 'tst_', 5);
        $applied  = $migrator->run();

        $this->assertCount(1, $applied);
        $lastCall = end($execCalls);
        $this->assertStringContainsString('dbVersion', $lastCall);
    }
}
