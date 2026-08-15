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
            $table = $match[1];

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
            $table = $match[1];

            return $this->createTables[$table] ?? ['Create Table' => 'CREATE TABLE `' . $table . '` (id int)'];
        }

        if (preg_match('/SELECT COUNT\(\*\) as state FROM (\S+)/', $qry, $match)) {
            $table = rtrim($match[1], ';');

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

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollback(): void {}
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
}
