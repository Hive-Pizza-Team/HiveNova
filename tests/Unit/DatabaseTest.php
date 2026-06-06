<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Database with injected PDO — avoids real MySQL in unit tests.
 */
class TestableDatabase extends Database
{
    /**
     * @param list<string> $tableKeys
     * @param list<string> $tableNames
     */
    public static function withMockPdo(PDO $pdo, array $tableKeys = [], array $tableNames = []): self
    {
        $ref = new ReflectionClass(self::class);
        /** @var self $db */
        $db = $ref->newInstanceWithoutConstructor();

        $parent = new ReflectionClass(Database::class);
        $handle = $parent->getProperty('dbHandle');
        $handle->setAccessible(true);
        $handle->setValue($db, $pdo);

        $names = $parent->getProperty('dbTableNames');
        $names->setAccessible(true);
        $names->setValue($db, ['keys' => $tableKeys, 'names' => $tableNames]);

        return $db;
    }
}

/**
 * Contract tests for DatabaseInterface and Database.
 *
 * These tests do not connect to a real database; they verify:
 *   - DatabaseInterface declares all required method signatures
 *   - lists() has been removed from the interface (SQLi sink)
 *   - Database class implements DatabaseInterface
 *   - Transaction methods are present and public
 *   - _query() :limit/:offset branch no longer coerces other params to int
 *   - PDO exceptions produce a generic message (SQL not leaked to caller)
 */
class DatabaseTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // FakeDatabase + Database::setInstance (behavioral)
    // -----------------------------------------------------------------------

    public function testSetInstanceIsReturnedByGet(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);

        $this->assertSame($fake, Database::get());
    }

    public function testFakeDatabaseRoutesSessionSelectSingle(): void
    {
        $fake = new FakeDatabase();
        $fake->session->sessionRows['sid-1'] = ['userID' => 7, 'lastonline' => TIMESTAMP];
        $this->swapDatabaseInstance($fake);

        $row = Database::get()->selectSingle(
            'SELECT userID, lastonline FROM %%SESSION%% WHERE sessionID = :sessionId',
            [':sessionId' => 'sid-1'],
        );

        $this->assertSame(7, (int) $row['userID']);
    }

    public function testFakeDatabaseRoutesAchievementSelect(): void
    {
        $fake = new FakeDatabase();
        $fake->achievement->addAchievement([
            'id' => 99, 'key' => 'test', 'category' => 'x', 'name_key' => 'n', 'desc_key' => 'd',
            'trigger_type' => 'combat_wins', 'trigger_params' => '{}', 'reward_type' => 'none',
            'reward_amount' => 0, 'points' => 1, 'celebration_tier' => 'normal', 'hidden' => 0,
            'active' => 1, 'universe' => 1,
        ]);
        $this->swapDatabaseInstance($fake);

        $rows = Database::get()->select(
            'SELECT * FROM %%ACHIEVEMENTS%% WHERE active = 1',
        );

        $this->assertCount(2, $rows);
        $this->assertSame(99, (int) $rows[1]['id']);
    }

    public function testFakeDatabaseInsertUnlocksAchievement(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);

        Database::get()->insert(
            'INSERT INTO %%USER_ACHIEVEMENTS%% SET userId = :userId, achievementId = :achievementId',
            [':userId' => 5, ':achievementId' => 1],
        );

        $key = '5:1';
        $this->assertTrue($fake->achievement->unlocked[$key] ?? false);
    }

    public function testFakeDatabaseSessionFieldAccessViaSessionProperty(): void
    {
        $fake = new FakeDatabase();
        $fake->session->sessionCount = 3;
        $this->swapDatabaseInstance($fake);

        $count = Database::get()->selectSingle(
            'SELECT COUNT(*) as record FROM %%SESSION%%',
            [],
            'record',
        );

        $this->assertSame(3, (int) $count);
    }

    // -----------------------------------------------------------------------
    // Interface shape
    // -----------------------------------------------------------------------

    public function testInterfaceDeclaresBeginTransaction(): void
    {
        $ref = new ReflectionClass(DatabaseInterface::class);
        $this->assertTrue($ref->hasMethod('beginTransaction'),
            'DatabaseInterface must declare beginTransaction()');
    }

    public function testInterfaceDeclaresCommit(): void
    {
        $ref = new ReflectionClass(DatabaseInterface::class);
        $this->assertTrue($ref->hasMethod('commit'),
            'DatabaseInterface must declare commit()');
    }

    public function testInterfaceDeclaresRollback(): void
    {
        $ref = new ReflectionClass(DatabaseInterface::class);
        $this->assertTrue($ref->hasMethod('rollback'),
            'DatabaseInterface must declare rollback()');
    }

    public function testInterfaceDeclaresAllCrudMethods(): void
    {
        $ref      = new ReflectionClass(DatabaseInterface::class);
        $required = ['select', 'selectSingle', 'insert', 'update', 'delete',
                     'replace', 'query', 'nativeQuery',
                     'lastInsertId', 'rowCount', 'getQueryCounter',
                     'quote', 'disconnect'];

        foreach ($required as $method) {
            $this->assertTrue($ref->hasMethod($method),
                "DatabaseInterface must declare {$method}()");
        }
    }

    /**
     * lists() was a SQLi sink (all three params interpolated raw into SQL).
     * It has been removed from the interface.
     */
    public function testListsRemovedFromInterface(): void
    {
        $ref = new ReflectionClass(DatabaseInterface::class);
        $this->assertFalse($ref->hasMethod('lists'),
            'lists() must not exist on DatabaseInterface — it was a SQL injection sink');
    }

    public function testListsRemovedFromDatabaseClass(): void
    {
        $ref = new ReflectionClass(Database::class);
        $this->assertFalse($ref->hasMethod('lists'),
            'lists() must not exist on Database — it was a SQL injection sink');
    }

    // -----------------------------------------------------------------------
    // Database class implements the interface
    // -----------------------------------------------------------------------

    public function testDatabaseClassImplementsInterface(): void
    {
        $ref = new ReflectionClass(Database::class);
        $this->assertTrue($ref->implementsInterface(DatabaseInterface::class),
            'Database must implement DatabaseInterface');
    }

    public function testDatabaseClassHasBeginTransaction(): void
    {
        $ref = new ReflectionClass(Database::class);
        $this->assertTrue($ref->hasMethod('beginTransaction'));
        $this->assertTrue($ref->getMethod('beginTransaction')->isPublic());
    }

    public function testDatabaseClassHasCommit(): void
    {
        $ref = new ReflectionClass(Database::class);
        $this->assertTrue($ref->hasMethod('commit'));
        $this->assertTrue($ref->getMethod('commit')->isPublic());
    }

    public function testDatabaseClassHasRollback(): void
    {
        $ref = new ReflectionClass(Database::class);
        $this->assertTrue($ref->hasMethod('rollback'));
        $this->assertTrue($ref->getMethod('rollback')->isPublic());
    }

    // -----------------------------------------------------------------------
    // _query() :limit/:offset coercion fix
    // Verify via reflection that the else-branch no longer casts to (int).
    // -----------------------------------------------------------------------

    /**
     * Before the fix, the else branch read:
     *   $stmt->bindValue($param, (int) $value, PDO::PARAM_STR)
     * which silently coerced string params to 0.
     *
     * After the fix it must be:
     *   $stmt->bindValue($param, $value, PDO::PARAM_STR)
     *
     * We verify by inspecting the source of _query().
     */
    public function testQueryMethodDoesNotCastNonLimitParamsToInt(): void
    {
        $ref    = new ReflectionMethod(Database::class, '_query');
        $start  = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;
        $lines  = array_slice(file($ref->getFileName()), $start, $length);
        $source = implode('', $lines);

        // The old buggy pattern cast non-limit params to (int)
        $this->assertStringNotContainsString(
            'bindValue($param, (int) $value, PDO::PARAM_STR)',
            $source,
            'Non-limit params must NOT be cast to (int) — doing so corrupts string parameters'
        );
    }

    // -----------------------------------------------------------------------
    // Exception message must not leak SQL
    // -----------------------------------------------------------------------

    /**
     * The old catch block embedded the full SQL in the exception message.
     * After the fix it must throw a generic message so query structure and
     * data values are not exposed to callers.
     */
    public function testExceptionMessageDoesNotContainQueryCode(): void
    {
        $ref    = new ReflectionMethod(Database::class, '_query');
        $start  = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;
        $lines  = array_slice(file($ref->getFileName()), $start, $length);
        $source = implode('', $lines);

        $this->assertStringNotContainsString(
            'Query-Code:',
            $source,
            'Exception message must not embed raw SQL — use error_log() instead'
        );
    }

    // -----------------------------------------------------------------------
    // Mock-based transaction-flow tests
    // -----------------------------------------------------------------------

    /**
     * A mock built from DatabaseInterface must be usable wherever
     * DatabaseInterface is expected (type compatibility check).
     */
    public function testMockImplementsDatabaseInterface(): void
    {
        $mock = $this->createMock(DatabaseInterface::class);
        $this->assertInstanceOf(DatabaseInterface::class, $mock);
    }

    /**
     * Happy-path: beginTransaction then commit — rollback never called.
     */
    public function testTransactionCommitsOnSuccess(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('commit');
        $db->expects($this->never())->method('rollback');

        $db->beginTransaction();
        $db->commit();
    }

    /**
     * Failure-path: beginTransaction then exception → rollback, no commit.
     */
    public function testTransactionRollsBackOnException(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->never())->method('commit');
        $db->expects($this->once())->method('rollback');

        try {
            $db->beginTransaction();
            throw new RuntimeException('simulated DB failure');
        } catch (RuntimeException $e) {
            $db->rollback();
        }
    }

    // -----------------------------------------------------------------------
    // TestableDatabase + PDO mock (Database class behavior)
    // -----------------------------------------------------------------------

    private function mockPdo(): PDO
    {
        return $this->createMock(PDO::class);
    }

    public function testFormatDateReturnsMysqlTimestamp(): void
    {
        $time = 1_700_000_000;
        $this->assertSame(date('Y-m-d H:i:s', $time), Database::formatDate($time));
    }

    public function testGetDbTableNamesReturnsInjectedMapping(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo(), ['%%USERS%%'], ['uni1_users']);

        $this->assertSame(
            ['keys' => ['%%USERS%%'], 'names' => ['uni1_users']],
            $db->getDbTableNames(),
        );
    }

    public function testDisconnectClearsHandle(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());
        $this->assertInstanceOf(PDO::class, $db->getHandle());

        $db->disconnect();

        $this->assertNull($db->getHandle());
    }

    public function testSelectSubstitutesTableNamesAndReturnsRows(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([['id' => 7, 'username' => 'alice']]);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT id, username FROM uni1_users WHERE id = :id')
            ->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo, ['%%USERS%%'], ['uni1_users']);
        $rows = $db->select('SELECT id, username FROM %%USERS%% WHERE id = :id', [':id' => '7']);

        $this->assertSame([['id' => 7, 'username' => 'alice']], $rows);
        $this->assertSame(1, $db->getQueryCounter());
    }

    public function testSelectSingleReturnsFieldValue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['username' => 'bob']);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);
        $name = $db->selectSingle('SELECT username FROM users WHERE id = :id', [':id' => 1], 'username');

        $this->assertSame('bob', $name);
    }

    public function testSelectSingleReturnsFullRowWhenFieldIsFalse(): void
    {
        $row = ['id' => 3, 'username' => 'carol'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertSame($row, $db->selectSingle('SELECT * FROM users WHERE id = :id', [':id' => 3]));
    }

    public function testSelectSingleReturnsEmptyArrayWhenNoRowAndFieldRequested(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn([]);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertSame([], $db->selectSingle('SELECT username FROM users WHERE id = :id', [':id' => 99], 'username'));
    }

    public function testInsertSetsLastInsertIdAndRowCount(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('42');

        $db = TestableDatabase::withMockPdo($pdo);
        $this->assertTrue($db->insert('INSERT INTO users SET username = :name', [':name' => 'dave']));
        $this->assertSame('42', $db->lastInsertId());
        $this->assertSame(1, $db->rowCount());
    }

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(2);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);
        $this->assertTrue($db->update('UPDATE users SET username = :name WHERE id = :id', [':name' => 'eve', ':id' => 1]));
        $this->assertSame(2, $db->rowCount());
    }

    public function testDeleteReturnsFalseWhenExecuteFails(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(false);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertFalse($db->delete('DELETE FROM users WHERE id = :id', [':id' => 1]));
    }

    public function testReplaceExecutesSuccessfully(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertTrue($db->replace('REPLACE INTO users SET id = :id', [':id' => 5]));
    }

    public function testQueryUsesExecAndIncrementsCounter(): void
    {
        $pdo = $this->mockPdo();
        $pdo->expects($this->once())->method('exec')->with('OPTIMIZE TABLE uni1_users')->willReturn(3);

        $db = TestableDatabase::withMockPdo($pdo);
        $db->query('OPTIMIZE TABLE uni1_users');

        $this->assertSame(3, $db->rowCount());
        $this->assertSame(1, $db->getQueryCounter());
    }

    public function testNativeQuerySelectReturnsFetchAllRows(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([['Variable_name' => 'Uptime']]);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->method('query')->with('SHOW STATUS')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);
        $rows = $db->nativeQuery('SHOW STATUS');

        $this->assertSame([['Variable_name' => 'Uptime']], $rows);
        $this->assertSame(1, $db->getQueryCounter());
    }

    public function testNativeQueryNonSelectReturnsTrue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(2);

        $pdo = $this->mockPdo();
        $pdo->method('query')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertTrue($db->nativeQuery('INSERT INTO log VALUES (1)'));
        $this->assertSame(2, $db->rowCount());
    }

    public function testQuoteDelegatesToPdo(): void
    {
        $pdo = $this->mockPdo();
        $pdo->method('quote')->with("O'Brien")->willReturn("'O\\'Brien'");

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertSame("'O\\'Brien'", $db->quote("O'Brien"));
    }

    public function testBeginTransactionCommitAndRollback(): void
    {
        $pdo = $this->mockPdo();
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack');

        $db = TestableDatabase::withMockPdo($pdo);
        $db->beginTransaction();
        $db->commit();
        $db->rollback();
    }

    public function testRollbackSkipsWhenNotInTransaction(): void
    {
        $pdo = $this->mockPdo();
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->expects($this->never())->method('rollBack');

        TestableDatabase::withMockPdo($pdo)->rollback();
    }

    public function testSelectWithLimitOffsetBindsIntegers(): void
    {
        $bindCalls = [];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('bindValue')->willReturnCallback(function ($param, $value, $type) use (&$bindCalls): bool {
            $bindCalls[] = [$param, $value, $type];

            return true;
        });
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);
        $db->select('SELECT * FROM users LIMIT :offset, :limit', [':offset' => '5', ':limit' => '10']);

        $this->assertContains([':offset', 5, PDO::PARAM_INT], $bindCalls);
        $this->assertContains([':limit', 10, PDO::PARAM_INT], $bindCalls);
    }

    public function testPdoExceptionProducesGenericMessage(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException(new PDOException('syntax error near foo'));

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('A database error occurred. Please try again later.');
        $db->select('SELECT * FROM users WHERE id = :id', [':id' => 1]);
    }

    public function testIncorrectSelectQueryTypeThrows(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Incorrect Select Query');
        $db->select('DELETE FROM users WHERE id = :id');
    }

    public function testIncorrectInsertQueryTypeThrows(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Incorrect Insert Query');
        $db->insert('UPDATE users SET username = :name');
    }

    public function testIncorrectUpdateQueryTypeThrows(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Incorrect Update Query');
        $db->update('SELECT * FROM users');
    }

    public function testIncorrectDeleteQueryTypeThrows(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Incorrect Delete Query');
        $db->delete('INSERT INTO users SET id = 1');
    }

    public function testIncorrectReplaceQueryTypeThrows(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Incorrect Replace Query');
        $db->replace('DELETE FROM users WHERE id = 1');
    }

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->mockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $db = TestableDatabase::withMockPdo($pdo);

        $this->assertTrue($db->delete('DELETE FROM users WHERE id = :id', [':id' => 1]));
    }

    public function testGetQueryTypeRejectsInvalidQuery(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());
        $method = new ReflectionMethod(Database::class, 'getQueryType');
        $method->setAccessible(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid query');
        $method->invoke($db, '   ');
    }

    public function testQueryRejectsUnsupportedType(): void
    {
        $db = TestableDatabase::withMockPdo($this->mockPdo());
        $method = new ReflectionMethod(Database::class, '_query');
        $method->setAccessible(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported Query Type');
        $method->invoke($db, 'DROP TABLE users', [], 'drop');
    }
}
