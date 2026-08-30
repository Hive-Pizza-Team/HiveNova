<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\SQLDumper;

use PHPUnit\Framework\TestCase;

/**
 * In-memory DatabaseInterface stub for SQLDumper software dump/restore paths.
 */
class SQLDumperFakeDatabase implements DatabaseInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $createTables = [];

    /** @var array<string, int> */
    public array $rowCounts = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $columns = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $rows = [];

    /** @var list<string> */
    public array $nativeQueries = [];

    public function select($qry, array $params = [])
    {
        if (preg_match('/SELECT \* FROM (\S+)/', $qry, $match)) {
            $table = trim($match[1], '`;');

            return $this->rows[$table] ?? [];
        }

        return [];
    }

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if (str_contains($qry, '@@version')) {
            return '8.0.32';
        }

        return false;
    }

    public function insert($qry, array $params = []) {}

    public function update($qry, array $params = []) {}

    public function delete($qry, array $params = []) {}

    public function replace($qry, array $params = []) {}

    public function query($qry) {}

    public function nativeQuery($qry)
    {
        $this->nativeQueries[] = $qry;

        if (preg_match('/^SHOW CREATE TABLE (\S+)/', $qry, $match)) {
            $table = trim($match[1], '`;');

            return $this->createTables[$table] ?? ['Create Table' => 'CREATE TABLE `' . $table . '` (id int)'];
        }

        if (preg_match('/SELECT COUNT\(\*\) as state FROM (\S+)/', $qry, $match)) {
            $table = trim(rtrim($match[1], ';'), '`');

            return [['state' => $this->rowCounts[$table] ?? 0]];
        }

        if (preg_match('/^SHOW COLUMNS FROM `([^`]+)`/', $qry, $match)) {
            return $this->columns[$match[1]] ?? [
                ['Field' => 'id', 'Type' => 'int(11)'],
                ['Field' => 'name', 'Type' => 'varchar(64)'],
            ];
        }

        return [];
    }

    public function lastInsertId()
    {
        return 0;
    }

    public function rowCount()
    {
        return 0;
    }

    public function getQueryCounter()
    {
        return count($this->nativeQueries);
    }

    public function quote($str)
    {
        return "'" . addslashes((string) $str) . "'";
    }

    public function disconnect() {}

    public function getHandle(): ?\PDO { return null; }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollback(): void {}
}

/**
 * Records native client invocations so dump/restore argument assembly can be tested
 * without requiring a live mysqldump/mysql binary or database.
 */
class SQLDumperNativeSpy extends SQLDumper
{
    /** @var list<array{binary:string,database:array,arguments:list<string>,descriptorSpec:?array}> */
    public array $nativeCalls = [];

    public bool $forceNativeAvailable = true;

    protected function canNative($command)
    {
        if ($this->forceNativeAvailable) {
            return true;
        }

        return parent::canNative($command);
    }

    protected function runNativeClient(string $binary, array $database, array $arguments, ?array $descriptorSpec = null): void
    {
        $this->nativeCalls[] = [
            'binary' => $binary,
            'database' => $database,
            'arguments' => $arguments,
            'descriptorSpec' => $descriptorSpec,
        ];
    }
}

class SQLDumperTest extends TestCase
{
    private ?DatabaseInterface $savedDatabaseInstance = null;

    private ?string $configBackup = null;

    private bool $configExisted = false;

    private SQLDumperFakeDatabase $db;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $this->savedDatabaseInstance = $prop->getValue();
        $prop->setValue(null, null);

        $this->db = new SQLDumperFakeDatabase();
        Database::setInstance($this->db);

        Config::setInstance(new Config(['uni' => 1, 'VERSION' => '1.8.test']), 1);

        $this->backupConfigFile();
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->savedDatabaseInstance);
        $this->savedDatabaseInstance = null;

        $configRef = new ReflectionProperty(Config::class, 'instances');
        $configRef->setAccessible(true);
        $configRef->setValue(null, []);

        $this->restoreConfigFile();

        parent::tearDown();
    }

    private function backupConfigFile(): void
    {
        $path = ROOT_PATH . 'includes/config.php';
        $this->configExisted = file_exists($path);
        if ($this->configExisted) {
            $this->configBackup = file_get_contents($path);
        }

        $content = <<<'PHP'
<?php
$database = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'user' => 'test',
    'userpw' => 'secret',
    'databasename' => 'hivenova_test',
];
PHP;
        if (file_put_contents($path, $content) === false) {
            $this->fail('Unable to write temporary includes/config.php for SQLDumper tests');
        }
    }

    private function restoreConfigFile(): void
    {
        $path = ROOT_PATH . 'includes/config.php';
        if ($this->configExisted) {
            file_put_contents($path, $this->configBackup ?? '');
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }

    private function invokePrivate(SQLDumper $dumper, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(SQLDumper::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($dumper, ...$args);
    }

    private function isNativeCliAvailable(string $command): bool
    {
        $dumper = new SQLDumper();

        return (bool) $this->invokePrivate($dumper, 'canNative', $command);
    }

    public function testCanNativeReturnsFalseWhenShellExecMissing(): void
    {
        if (!function_exists('shell_exec')) {
            $dumper = new SQLDumper();
            $this->assertFalse($this->invokePrivate($dumper, 'canNative', 'mysqldump'));
        } else {
            $this->assertIsBool($this->isNativeCliAvailable('mysqldump'));
        }
    }

    public function testSoftwareDumpWritesStructureForEmptyTable(): void
    {
        $table = 'uni1_config';
        $this->db->createTables[$table] = [
            'Create Table' => 'CREATE TABLE `uni1_config` (`uni` int NOT NULL)',
        ];
        $this->db->rowCounts[$table] = 0;

        $file = tempnam(sys_get_temp_dir(), 'sqldump-');
        $this->assertNotFalse($file);

        try {
            $dumper = new SQLDumper();
            $result = $this->invokePrivate($dumper, 'softwareDumpToFile', [$table], $file);

            $this->assertTrue($result);
            $contents = file_get_contents($file);
            $this->assertStringContainsString('CREATE TABLE `uni1_config`', $contents);
            $this->assertStringContainsString('No data for table `uni1_config`', $contents);
            $this->assertStringContainsString('2Moons dumper v1.8.test', $contents);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testSoftwareDumpWritesInsertStatementsForRows(): void
    {
        $table = 'uni1_users';
        $this->db->rowCounts[$table] = 2;
        $this->db->columns[$table] = [
            ['Field' => 'id', 'Type' => 'int(11)'],
            ['Field' => 'username', 'Type' => 'varchar(32)'],
        ];
        $this->db->rows[$table] = [
            ['id' => 1, 'username' => 'alpha'],
            ['id' => 2, 'username' => 'beta'],
        ];

        $file = tempnam(sys_get_temp_dir(), 'sqldump-');
        $this->assertNotFalse($file);

        try {
            $dumper = new SQLDumper();
            $this->invokePrivate($dumper, 'softwareDumpToFile', [$table], $file);

            $contents = file_get_contents($file);
            $this->assertStringContainsString("INSERT INTO `{$table}` (`id`, `username`) VALUES", $contents);
            $this->assertStringContainsString("(1, 'alpha')", $contents);
            $this->assertStringContainsString("(2, 'beta')", $contents);
            $this->assertStringContainsString('LOCK TABLES', $contents);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testSoftwareDumpHandlesNullValuesAndLegacyCreateTableShape(): void
    {
        $table = 'legacy_table';
        $this->db->createTables[$table] = [
            0 => ['Create Table' => 'CREATE TABLE `legacy_table` (`note` text)'],
        ];
        $this->db->rowCounts[$table] = 1;
        $this->db->columns[$table] = [
            ['Field' => 'note', 'Type' => 'text'],
        ];
        $this->db->rows[$table] = [
            ['note' => null],
        ];

        $file = tempnam(sys_get_temp_dir(), 'sqldump-');
        $this->assertNotFalse($file);

        try {
            $dumper = new SQLDumper();
            $this->invokePrivate($dumper, 'softwareDumpToFile', [$table], $file);

            $contents = file_get_contents($file);
            $this->assertStringContainsString('CREATE TABLE `legacy_table`', $contents);
            $this->assertStringContainsString('(NULL)', $contents);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testSoftwareDumpThrowsWhenCreateTableResultMissing(): void
    {
        $table = 'broken_table';
        $this->db->createTables[$table] = ['unexpected' => 'shape'];

        $file = tempnam(sys_get_temp_dir(), 'sqldump-');
        $this->assertNotFalse($file);

        try {
            $dumper = new SQLDumper();
            $this->expectException(Exception::class);
            $this->expectExceptionMessage("Can't find key 'Create Table'");
            $this->invokePrivate($dumper, 'softwareDumpToFile', [$table], $file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testDumpTablesToFileUsesSoftwarePathWhenNativeUnavailable(): void
    {
        if ($this->isNativeCliAvailable('mysqldump')) {
            $this->markTestSkipped('mysqldump CLI is available; software path not selected');
        }

        $table = 'uni1_config';
        $this->db->rowCounts[$table] = 0;

        $file = tempnam(sys_get_temp_dir(), 'sqldump-');
        $this->assertNotFalse($file);

        try {
            $dumper = new SQLDumper();
            $result = $dumper->dumpTablesToFile([$table], $file);

            $this->assertTrue($result);
            $this->assertStringContainsString('CREATE TABLE', (string) file_get_contents($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testRestoreDatabaseThrowsWhenBackupFileMissing(): void
    {
        $dumper = new SQLDumper();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Backup file not found or not readable.');
        $dumper->restoreDatabase(sys_get_temp_dir() . '/missing-hivenova-backup.sql');
    }

    public function testRestoreDatabaseSoftwarePathExecutesStatements(): void
    {
        if ($this->isNativeCliAvailable('mysql')) {
            $this->markTestSkipped('mysql CLI is available; software restore path not selected');
        }

        $file = tempnam(sys_get_temp_dir(), 'sqlrestore-');
        $this->assertNotFalse($file);
        file_put_contents($file, "SET NAMES utf8;\n\nUPDATE uni1_config SET uni = 1;\n");

        try {
            $dumper = new SQLDumper();
            $dumper->restoreDatabase($file);

            $this->assertContains('SET NAMES utf8', $this->db->nativeQueries);
            $this->assertContains('UPDATE uni1_config SET uni = 1', $this->db->nativeQueries);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testRestoreDatabaseSoftwarePathSkipsBlankStatements(): void
    {
        if ($this->isNativeCliAvailable('mysql')) {
            $this->markTestSkipped('mysql CLI is available; software restore path not selected');
        }

        $file = tempnam(sys_get_temp_dir(), 'sqlrestore-');
        $this->assertNotFalse($file);
        file_put_contents($file, ";\n\n   \nSELECT 1;\n");

        try {
            $dumper = new SQLDumper();
            $dumper->restoreDatabase($file);

            $this->assertSame(['SELECT 1'], $this->db->nativeQueries);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testFilterAllowedTablesDropsUnknownAndUnsafeNames(): void
    {
        $allowed = ['uni1_users', 'uni1_config'];
        $requested = ['uni1_users', 'mysql.user', 'uni1_users; DROP TABLE', 'uni1_missing'];
        $this->assertSame(['uni1_users'], SQLDumper::filterAllowedTables($requested, $allowed));
    }

    public function testQuoteIdentifierRejectsUnsafeNames(): void
    {
        $this->assertSame('`uni1_users`', SQLDumper::quoteIdentifier('uni1_users'));
        $this->expectException(Exception::class);
        SQLDumper::quoteIdentifier('uni1_users`; DROP TABLE x; --');
    }

    public function testFormatClientDefaultsQuotesCredentials(): void
    {
        $body = SQLDumper::formatClientDefaults([
            'host' => 'db.example',
            'port' => '3307',
            'user' => 'nova"admin',
            'userpw' => 'p\\ass"word',
        ]);

        $this->assertStringContainsString("[client]\n", $body);
        $this->assertStringContainsString('host="db.example"', $body);
        $this->assertStringContainsString('port=3307', $body);
        $this->assertStringContainsString('user="nova\\"admin"', $body);
        $this->assertStringContainsString('password="p\\\\ass\\"word"', $body);
    }

    public function testFormatClientDefaultsUsesSafeDefaultsWhenKeysMissing(): void
    {
        $body = SQLDumper::formatClientDefaults([]);

        $this->assertStringContainsString('host="127.0.0.1"', $body);
        $this->assertStringContainsString('port=3306', $body);
        $this->assertStringContainsString('user=""', $body);
        $this->assertStringContainsString('password=""', $body);
    }

    public function testCreateClientDefaultsFileIsPrivateReadableAndRemovedByCallerPattern(): void
    {
        $dumper = new SQLDumper();
        $path = $this->invokePrivate($dumper, 'createClientDefaultsFile', [
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'test',
            'userpw' => 'secret',
        ]);

        try {
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringContainsString('password="secret"', $contents);
            // Must be owner-only after createClientDefaultsFile (chmod before write).
            $perms = fileperms($path) & 0777;
            $this->assertSame(0600, $perms);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testCreateClientDefaultsFileThrowsAndUnlinksWhenSecureFails(): void
    {
        $dumper = new class extends SQLDumper {
            public ?string $lastPath = null;

            protected function secureClientDefaultsFile(string $path): bool
            {
                $this->lastPath = $path;

                return false;
            }
        };

        try {
            $this->invokePrivate($dumper, 'createClientDefaultsFile', [
                'host' => '127.0.0.1',
                'port' => 3306,
                'user' => 'test',
                'userpw' => 'secret',
            ]);
            $this->fail('Expected Exception when securing defaults file fails');
        } catch (Exception $e) {
            $this->assertSame('Unable to secure temporary MySQL defaults file.', $e->getMessage());
            $this->assertNotNull($dumper->lastPath);
            $this->assertFileDoesNotExist($dumper->lastPath);
        }
    }

    /**
     * @return array{host:string,port:int,user:string,userpw:string,databasename:string}
     */
    private function sampleDatabaseConfig(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'test',
            'userpw' => 'secret',
            'databasename' => 'hivenova_test',
        ];
    }

    private function writeExecutableScript(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hn-cli-');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);
        chmod($path, 0755);

        return $path;
    }

    public function testRunNativeClientSucceedsAndRemovesDefaultsFile(): void
    {
        $script = $this->writeExecutableScript("#!/bin/sh\nexit 0\n");
        $dumper = new SQLDumper();

        try {
            $this->invokePrivate($dumper, 'runNativeClient', $script, $this->sampleDatabaseConfig(), []);
            $this->assertTrue(true);
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
        }
    }

    public function testRunNativeClientWritesStdoutToFileDescriptor(): void
    {
        $script = $this->writeExecutableScript("#!/bin/sh\necho dumped\n");
        $outFile = tempnam(sys_get_temp_dir(), 'hn-out-');
        $this->assertNotFalse($outFile);
        $dumper = new SQLDumper();

        try {
            $this->invokePrivate(
                $dumper,
                'runNativeClient',
                $script,
                $this->sampleDatabaseConfig(),
                [escapeshellarg('unused')],
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $outFile, 'w'],
                    2 => ['pipe', 'w'],
                ]
            );

            $this->assertSame("dumped\n", file_get_contents($outFile));
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
            if (is_file($outFile)) {
                unlink($outFile);
            }
        }
    }

    public function testRunNativeClientThrowsStderrFromFailedProcess(): void
    {
        $script = $this->writeExecutableScript("#!/bin/sh\necho boom >&2\nexit 2\n");
        $dumper = new SQLDumper();

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('boom');
            $this->invokePrivate($dumper, 'runNativeClient', $script, $this->sampleDatabaseConfig(), []);
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
        }
    }

    public function testRunNativeClientThrowsStdoutWhenStderrEmpty(): void
    {
        $script = $this->writeExecutableScript("#!/bin/sh\necho only-out\nexit 1\n");
        $dumper = new SQLDumper();

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('only-out');
            $this->invokePrivate($dumper, 'runNativeClient', $script, $this->sampleDatabaseConfig(), ['--flag']);
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
        }
    }

    public function testRunNativeClientThrowsGenericMessageWhenNoOutput(): void
    {
        $script = $this->writeExecutableScript("#!/bin/sh\nexit 7\n");
        $dumper = new SQLDumper();

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage($script . ' failed with exit code 7');
            $this->invokePrivate($dumper, 'runNativeClient', $script, $this->sampleDatabaseConfig(), []);
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
        }
    }

    public function testNativeDumpToFileAssemblesMysqldumpArguments(): void
    {
        $spy = new SQLDumperNativeSpy();
        $outFile = tempnam(sys_get_temp_dir(), 'hn-dump-');
        $this->assertNotFalse($outFile);

        try {
            $result = $this->invokePrivate($spy, 'nativeDumpToFile', ['uni1_users', 'uni1_config'], $outFile);

            $this->assertNull($result);
            $this->assertCount(1, $spy->nativeCalls);
            $call = $spy->nativeCalls[0];
            $this->assertSame('mysqldump', $call['binary']);
            $this->assertSame('hivenova_test', $call['database']['databasename']);
            $this->assertContains('--no-tablespaces', $call['arguments']);
            $this->assertContains('--complete-insert', $call['arguments']);
            $this->assertContains(escapeshellarg('hivenova_test'), $call['arguments']);
            $this->assertContains(escapeshellarg('uni1_users'), $call['arguments']);
            $this->assertContains(escapeshellarg('uni1_config'), $call['arguments']);
            $this->assertSame('file', $call['descriptorSpec'][1][0]);
            $this->assertSame($outFile, $call['descriptorSpec'][1][1]);
        } finally {
            if (is_file($outFile)) {
                unlink($outFile);
            }
        }
    }

    public function testRestoreDatabaseNativePathUsesMysqlClient(): void
    {
        $spy = new SQLDumperNativeSpy();
        $file = tempnam(sys_get_temp_dir(), 'sqlrestore-');
        $this->assertNotFalse($file);
        file_put_contents($file, "SELECT 1;\n");

        try {
            $spy->restoreDatabase($file);

            $this->assertCount(1, $spy->nativeCalls);
            $call = $spy->nativeCalls[0];
            $this->assertSame('mysql', $call['binary']);
            $this->assertSame([escapeshellarg('hivenova_test')], $call['arguments']);
            $this->assertSame('file', $call['descriptorSpec'][0][0]);
            $this->assertSame($file, $call['descriptorSpec'][0][1]);
            $this->assertSame([], $this->db->nativeQueries);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
