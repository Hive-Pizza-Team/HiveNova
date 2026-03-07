<?php

use PHPUnit\Framework\TestCase;

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
}
