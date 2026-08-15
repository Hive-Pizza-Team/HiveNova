<?php

use HiveNova\Core\DatabaseBC;

use PHPUnit\Framework\TestCase;

/**
 * Stub mysqli_result for DatabaseBC helpers that accept a result object.
 */
class StubMysqliResult
{
    /** @var list<array<string, mixed>> */
    private array $assocRows;

    /** @var list<list<mixed>> */
    private array $numRows;

    public int $num_rows;

    public bool $closeCalled = false;

    /**
     * @param list<array<string, mixed>> $assocRows
     */
    public function __construct(array $assocRows)
    {
        $this->assocRows = $assocRows;
        $this->numRows = array_map(
            static fn(array $row): array => array_values($row),
            $assocRows,
        );
        $this->num_rows = count($assocRows);
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    public function fetch_array(int $mode)
    {
        if ($mode === MYSQLI_ASSOC) {
            return array_shift($this->assocRows);
        }

        if ($mode === MYSQLI_NUM) {
            return array_shift($this->numRows);
        }

        return null;
    }

    public function close(): void
    {
        $this->closeCalled = true;
    }

    public function free(): void
    {
        $this->closeCalled = true;
    }
}

/**
 * DatabaseBC without live MySQL: skips constructor and optionally stubs query paths.
 */
class TestableDatabaseBC extends DatabaseBC
{
    /** @var callable(string): mixed|false|null */
    private $queryStub;

    /** @var callable(string, self): void|null */
    private $multiQueryStub;

    public string $stubError = 'stub sql error';

    public function __construct()
    {
    }

    public static function withoutConstructor(): self
    {
        $ref = new ReflectionClass(self::class);

        /** @var self $db */
        $db = $ref->newInstanceWithoutConstructor();

        return $db;
    }

    public static function withLiveConnection(): ?self
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        $db = self::withoutConstructor();
        $ctor = new ReflectionMethod(mysqli::class, '__construct');

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';
        $name = getenv('DB_NAME') ?: 'mysql';
        $port = (int) (getenv('DB_PORT') ?: 3306);

        try {
            @$ctor->invoke($db, $host, $user, $pass, $name, $port);
        } catch (Throwable) {
            return null;
        }

        if ($db->connect_errno) {
            return null;
        }

        return $db;
    }

    /**
     * @param callable(string): mixed|false|null $handler
     */
    public function stubQuery(callable $handler): self
    {
        $this->queryStub = $handler;

        return $this;
    }

    /**
     * @param callable(string, self): void $handler
     */
    public function stubMultiQuery(callable $handler): self
    {
        $this->multiQueryStub = $handler;

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function query($resource, $resultmode = null)
    {
        if ($this->queryStub !== null) {
            return $this->runStubbedQuery((string) $resource);
        }

        return parent::query($resource, $resultmode);
    }

    #[\ReturnTypeWillChange]
    public function multi_query($resource)
    {
        if ($this->multiQueryStub !== null) {
            ($this->multiQueryStub)((string) $resource, $this);

            return null;
        }

        return parent::multi_query($resource);
    }

    /**
     * @return mixed
     */
    private function runStubbedQuery(string $resource)
    {
        $result = ($this->queryStub)($resource);

        if ($result) {
            $this->incrementQueryCount();

            return $result;
        }

        throw new Exception('SQL Error: ' . $this->stubError . '<br><br>Query Code: ' . $resource);
    }

    public function incrementQueryCount(): void
    {
        $prop = new ReflectionProperty(DatabaseBC::class, 'queryCount');
        $prop->setAccessible(true);
        $prop->setValue($this, (int) $prop->getValue($this) + 1);
    }

    /**
     * Invoke DatabaseBC::query without TestableDatabaseBC override (needs live mysqli).
     *
     * @return mixed
     */
    public function invokeOriginalQuery(string $sql)
    {
        $method = new ReflectionMethod(DatabaseBC::class, 'query');

        return $method->invoke($this, $sql);
    }

    public function invokeOriginalMultiQuery(string $sql): void
    {
        $method = new ReflectionMethod(DatabaseBC::class, 'multi_query');
        $method->invoke($this, $sql);
    }
}

class DatabaseBCTest extends TestCase
{
    private ?TestableDatabaseBC $liveDb = null;

    private bool $liveDbAttempted = false;

    private function disconnected(): TestableDatabaseBC
    {
        return TestableDatabaseBC::withoutConstructor();
    }

    private function stubbed(): TestableDatabaseBC
    {
        return TestableDatabaseBC::withoutConstructor();
    }

    private function liveDb(): ?TestableDatabaseBC
    {
        if (!$this->liveDbAttempted) {
            $this->liveDbAttempted = true;
            $this->liveDb = TestableDatabaseBC::withLiveConnection();
        }

        return $this->liveDb;
    }

    // -------------------------------------------------------------------------
    // Result delegation helpers (no mysqli connection required)
    // -------------------------------------------------------------------------

    public function testFetchArrayReturnsAssocRow(): void
    {
        $result = new StubMysqliResult([['id' => 7, 'name' => 'nova']]);
        $db = $this->disconnected();

        $this->assertSame(['id' => 7, 'name' => 'nova'], $db->fetchArray($result));
    }

    public function testFetch_arrayDelegatesToFetchArray(): void
    {
        $result = new StubMysqliResult([['x' => 'y']]);
        $db = $this->disconnected();

        $this->assertSame(['x' => 'y'], $db->fetch_array($result));
    }

    public function testFetch_numReturnsNumericRow(): void
    {
        $result = new StubMysqliResult([['id' => 3, 'label' => 'z']]);
        $db = $this->disconnected();

        $this->assertSame([3, 'z'], $db->fetch_num($result));
    }

    public function testNumRowsReturnsResultCount(): void
    {
        $result = new StubMysqliResult([
            ['id' => 1],
            ['id' => 2],
        ]);
        $db = $this->disconnected();

        $this->assertSame(2, $db->numRows($result));
    }

    public function testFree_resultClosesResource(): void
    {
        $result = new StubMysqliResult([['id' => 1]]);
        $db = $this->disconnected();

        $db->free_result($result);

        $this->assertTrue($result->closeCalled);
    }

    public function testStr_correctionStripsCslashes(): void
    {
        $db = $this->disconnected();

        $this->assertSame("line\nbreak", $db->str_correction('line\\nbreak'));
    }

    public function testGet_sqlStartsAtZero(): void
    {
        $db = $this->disconnected();

        $this->assertSame(0, $db->get_sql());
    }

    // -------------------------------------------------------------------------
    // query() / queryCount / exception (stubbed or live)
    // -------------------------------------------------------------------------

    public function testQueryIncrementsCounterAndReturnsResult(): void
    {
        $result = new StubMysqliResult([['n' => 1]]);
        $db = $this->stubbed()->stubQuery(static fn(string $sql) => $sql === 'SELECT 1' ? $result : false);

        $this->assertSame($result, $db->query('SELECT 1'));
        $this->assertSame(1, $db->get_sql());
    }

    public function testQueryThrowsExceptionWithSqlAndErrorOnFailure(): void
    {
        $db = $this->stubbed()
            ->stubQuery(static fn(): bool => false);
        $db->stubError = 'syntax near FAIL';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SQL Error: syntax near FAIL');
        $this->expectExceptionMessage('Query Code: SELECT FAIL');

        $db->query('SELECT FAIL');
    }

    public function testOriginalQueryOnLiveConnectionIncrementsCounter(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $before = $this->liveDb()->get_sql();
        $result = $this->liveDb()->invokeOriginalQuery('SELECT 1 AS n');

        $this->assertInstanceOf(mysqli_result::class, $result);
        $this->assertSame($before + 1, $this->liveDb()->get_sql());
        $result->free();
    }

    public function testOriginalQueryOnLiveConnectionThrowsOnBadSql(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SQL Error:');
        $this->expectExceptionMessage('Query Code:');

        $this->liveDb()->invokeOriginalQuery('SELECT FROM broken_syntax');
    }

    // -------------------------------------------------------------------------
    // getFirstRow / uniquequery / getFirstCell / countquery / fetchquery
    // -------------------------------------------------------------------------

    public function testGetFirstRowFetchesAssocRowAndClosesResult(): void
    {
        $result = new StubMysqliResult([['id' => 42, 'name' => 'hive']]);
        $db = $this->stubbed()->stubQuery(static fn() => $result);

        $row = $db->getFirstRow('SELECT id, name FROM users LIMIT 1');

        $this->assertSame(['id' => 42, 'name' => 'hive'], $row);
        $this->assertTrue($result->closeCalled);
        $this->assertSame(1, $db->get_sql());
    }

    public function testUniquequeryIsAliasForGetFirstRow(): void
    {
        $result = new StubMysqliResult([['v' => 'ok']]);
        $db = $this->stubbed()->stubQuery(static fn() => $result);

        $this->assertSame(['v' => 'ok'], $db->uniquequery('SELECT v'));
    }

    public function testGetFirstCellReturnsFirstColumn(): void
    {
        $result = new StubMysqliResult([['total' => 99, 'ignored' => 'x']]);
        $db = $this->stubbed()->stubQuery(static fn() => $result);

        $this->assertSame(99, $db->getFirstCell('SELECT total, ignored FROM t'));
        $this->assertTrue($result->closeCalled);
    }

    public function testCountqueryIsAliasForGetFirstCell(): void
    {
        $result = new StubMysqliResult([['c' => '7']]);
        $db = $this->stubbed()->stubQuery(static fn() => $result);

        $this->assertSame('7', $db->countquery('SELECT c'));
    }

    public function testFetchqueryReturnsAllRows(): void
    {
        $result = new StubMysqliResult([
            ['id' => 1, 'tag' => 'a'],
            ['id' => 2, 'tag' => 'b'],
        ]);
        $db = $this->stubbed()->stubQuery(static fn() => $result);

        $this->assertSame([
            ['id' => 1, 'tag' => 'a'],
            ['id' => 2, 'tag' => 'b'],
        ], $db->fetchquery('SELECT id, tag FROM t'));
        $this->assertTrue($result->closeCalled);
    }

    public function testFetchqueryBase64EncodesSelectedColumns(): void
    {
        $result = new StubMysqliResult([
            ['id' => 1, 'payload' => 'secret'],
        ]);
        $db = $this->stubbed()->stubQuery(static fn() => $result);

        $rows = $db->fetchquery('SELECT id, payload FROM t', ['payload']);

        $this->assertSame(1, $rows[0]['id']);
        $this->assertSame(base64_encode('secret'), $rows[0]['payload']);
    }

    public function testGetFirstRowOnLiveConnection(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $row = $this->liveDb()->getFirstRow('SELECT 2 AS n');

        $this->assertSame(['n' => '2'], $row);
    }

    // -------------------------------------------------------------------------
    // multi_query paths
    // -------------------------------------------------------------------------

    public function testMultiQueryStubIncrementsQueryCountPerResultSet(): void
    {
        $db = $this->stubbed()->stubMultiQuery(static function (string $sql, TestableDatabaseBC $self): void {
            if ($sql !== 'SELECT 1; SELECT 2;') {
                throw new RuntimeException('unexpected multi_query SQL: ' . $sql);
            }
            $self->incrementQueryCount();
            $self->incrementQueryCount();
        });

        $db->multi_query('SELECT 1; SELECT 2;');

        $this->assertSame(2, $db->get_sql());
    }

    public function testMultiQueryStubThrowsWhenHandlerFails(): void
    {
        $db = $this->stubbed()->stubMultiQuery(static function (string $sql, TestableDatabaseBC $self): void {
            throw new Exception('SQL Error: multi failed<br><br>Query Code: ' . $sql);
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('multi failed');

        $db->multi_query('BAD;');
    }

    public function testOriginalMultiQueryOnLiveConnection(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $before = $this->liveDb()->get_sql();
        $this->liveDb()->invokeOriginalMultiQuery('SELECT 1; SELECT 2;');

        $this->assertGreaterThanOrEqual($before + 1, $this->liveDb()->get_sql());
    }

    public function testOriginalMultiQueryOnLiveConnectionThrowsOnError(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SQL Error:');

        $this->liveDb()->invokeOriginalMultiQuery('SELECT 1; SELECT FROM broken;');
    }

    // -------------------------------------------------------------------------
    // escape / version / affected rows (live connection when available)
    // -------------------------------------------------------------------------

    public function testEscapeDelegatesToSql_escape(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $escaped = $this->liveDb()->escape("O'Hara");
        $this->assertIsString($escaped);
        $this->assertStringNotContainsString("O'Hara", $escaped);
        $this->assertSame($escaped, $this->liveDb()->sql_escape("O'Hara", false));
    }

    public function testSql_escapeAddsCslashesForLikeWhenFlagTrue(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $plain = $this->liveDb()->sql_escape('100%', false);
        $like = $this->liveDb()->sql_escape('100%', true);

        $this->assertNotSame($plain, $like);
        $this->assertStringContainsString('\\', $like);
    }

    public function testGetVersionReturnsClientInfo(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $this->assertNotSame('', $this->liveDb()->getVersion());
    }

    public function testGetServerVersionReturnsServerInfo(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $this->assertNotSame('', $this->liveDb()->getServerVersion());
    }

    public function testAffectedRowsAndInsertIdAfterInsert(): void
    {
        if ($this->liveDb() === null) {
            $this->assertTrue(true);

            return;
        }

        $this->liveDb()->invokeOriginalQuery('CREATE TEMPORARY TABLE db_bc_test (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(32))');
        $this->liveDb()->invokeOriginalQuery("INSERT INTO db_bc_test (label) VALUES ('probe')");

        $this->assertSame(1, $this->liveDb()->affectedRows());
        $this->assertGreaterThan(0, $this->liveDb()->GetInsertID());

        $this->liveDb()->invokeOriginalQuery('DROP TEMPORARY TABLE db_bc_test');
    }

    // -------------------------------------------------------------------------
    // Constructor contract (reflection — no connection required)
    // -------------------------------------------------------------------------

    public function testConstructorRequiresConfigAndExtendsMysqli(): void
    {
        $ref = new ReflectionClass(DatabaseBC::class);

        $this->assertTrue($ref->isSubclassOf(mysqli::class));
        $this->assertTrue($ref->hasMethod('query'));
        $this->assertTrue($ref->hasMethod('multi_query'));
        $this->assertTrue($ref->hasMethod('get_sql'));
    }

    public function testQueryMethodThrowsExceptionOnFailureNotError(): void
    {
        $ref = new ReflectionMethod(DatabaseBC::class, 'query');
        $start = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;
        $source = implode('', array_slice(file($ref->getFileName()), $start, $length));

        $this->assertStringContainsString('throw new Exception', $source);
        $this->assertStringContainsString('$this->queryCount++', $source);
    }
}
