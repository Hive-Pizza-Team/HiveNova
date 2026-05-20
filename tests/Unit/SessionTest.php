<?php

use HiveNova\Core\Database;
use HiveNova\Core\Session;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/SessionDatabaseStub.php';

if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600);
if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', sys_get_temp_dir() . '/hivenova-phpunit-sessions/');
}
if (!defined('HTTPS')) define('HTTPS', false);
if (!defined('HTTP_ROOT')) define('HTTP_ROOT', '/');
if (!defined('MODE')) define('MODE', 'INSTALL');

class SessionTest extends TestCase
{
    private SessionDatabaseStub $dbStub;

    private function newSession(): Session
    {
        return (new ReflectionClass(Session::class))->newInstanceWithoutConstructor();
    }

    private function invokeRestoreFromDatabase(string $sessionId): ?Session
    {
        $ref = new ReflectionMethod(Session::class, 'restoreFromDatabase');
        $ref->setAccessible(true);

        return $ref->invoke(null, $sessionId);
    }

    private function setSessionData(Session $session, array $data): void
    {
        $ref = new ReflectionProperty(Session::class, 'data');
        $ref->setAccessible(true);
        $ref->setValue($session, $data);
    }

    protected function setUp(): void
    {
        // Reset static singleton so each test starts clean
        $ref = new ReflectionProperty(Session::class, 'obj');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, null);

        $this->dbStub = new SessionDatabaseStub();
        Database::setInstance($this->dbStub);

        if (!is_dir(CACHE_PATH . 'sessions')) {
            mkdir(CACHE_PATH . 'sessions', 0777, true);
        }

        $_SESSION = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, null);
    }

    // -----------------------------------------------------------------------
    // isValidSession — null data (post-namespace-migration stale session)
    // -----------------------------------------------------------------------

    public function testIsValidSessionReturnsFalseWhenDataIsNull(): void
    {
        $session = $this->newSession();
        // Simulate a stale serialized session present so the early-return
        // guard on line 318 doesn't fire before we reach the null-data check.
        $_SESSION['obj'] = 'stale';

        $this->assertFalse($session->isValidSession());
    }

    // -----------------------------------------------------------------------
    // load() — stale class name falls back to fresh session
    // -----------------------------------------------------------------------

    public function testLoadFallsBackToCreateWhenUnserializedValueIsNotASession(): void
    {
        // Serialize an object of a completely different class to simulate what
        // happens when the stored session was serialized before the namespace
        // migration (old class name "Session", now "HiveNova\Core\Session").
        $stale = serialize(new stdClass());

        // Manually prime $_SESSION as if session_start() already ran and
        // read the stale data — then call load() via reflection so we can
        // skip the real session_start() call.
        $_SESSION['obj'] = $stale;

        // Directly exercise the instanceof guard introduced in the fix.
        $obj = safe_unserialize($stale);
        $this->assertNotInstanceOf(Session::class, $obj,
            'stdClass must not pass the Session instanceof check');

        // A fresh Session created by the fallback must have null data
        // (nothing has been stored yet).
        $fresh = $this->newSession();
        $ref   = new ReflectionProperty(Session::class, 'data');
        $ref->setAccessible(true);
        $this->assertNull($ref->getValue($fresh));
    }

    // -----------------------------------------------------------------------
    // restoreFromDatabase — iOS PWA session file loss
    // -----------------------------------------------------------------------

    public function testRestoreFromDatabaseReturnsNullForEmptySessionId(): void
    {
        $this->assertNull($this->invokeRestoreFromDatabase(''));
    }

    public function testRestoreFromDatabaseReturnsNullWhenSessionRowMissing(): void
    {
        $this->assertNull($this->invokeRestoreFromDatabase('missing-session-id'));
    }

    public function testRestoreFromDatabaseReturnsNullWhenSessionExpired(): void
    {
        $this->dbStub->sessionRows['expired'] = [
            'userID'     => 42,
            'lastonline' => TIMESTAMP - SESSION_LIFETIME - 1,
        ];
        $this->dbStub->userRows[42] = [
            'id'        => 42,
            'id_planet' => 7,
            'bana'      => 0,
        ];

        $this->assertNull($this->invokeRestoreFromDatabase('expired'));
    }

    public function testRestoreFromDatabaseReturnsNullWhenUserBanned(): void
    {
        $this->dbStub->sessionRows['banned-user'] = [
            'userID'     => 99,
            'lastonline' => TIMESTAMP,
        ];
        $this->dbStub->userRows[99] = [
            'id'        => 99,
            'id_planet' => 3,
            'bana'      => 1,
        ];

        $this->assertNull($this->invokeRestoreFromDatabase('banned-user'));
    }

    public function testRestoreFromDatabaseRebuildsSessionWithAdminAccessZero(): void
    {
        $this->dbStub->sessionRows['valid-restore'] = [
            'userID'     => 42,
            'lastonline' => TIMESTAMP,
        ];
        $this->dbStub->userRows[42] = [
            'id'        => 42,
            'id_planet' => 7,
            'bana'      => 0,
        ];

        $restored = $this->invokeRestoreFromDatabase('valid-restore');

        $this->assertInstanceOf(Session::class, $restored);

        $ref = new ReflectionProperty(Session::class, 'data');
        $ref->setAccessible(true);
        $data = $ref->getValue($restored);

        $this->assertSame(42, $data['userId']);
        $this->assertSame(7, $data['planetId']);
        $this->assertSame(0, $data['adminAccess']);
        $this->assertSame('valid-restore', $data['sessionId']);
        $this->assertSame(TIMESTAMP, $data['lastActivity']);
    }

    // -----------------------------------------------------------------------
    // isValidSession — restored session without $_SESSION['obj']
    // -----------------------------------------------------------------------

    public function testIsValidSessionReturnsFalseWhenObjMissingAndNoUserId(): void
    {
        $session = $this->newSession();
        $this->setSessionData($session, [
            'lastActivity' => TIMESTAMP,
        ]);

        $this->assertFalse($session->isValidSession());
    }

    public function testIsValidSessionReturnsTrueWhenObjMissingButUserIdSetAndDbRowExists(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('restored-session');
        session_start();

        $this->dbStub->sessionCount = 1;

        $session = $this->newSession();
        $this->setSessionData($session, [
            'userId'       => 42,
            'lastActivity' => TIMESTAMP,
        ]);

        $this->assertTrue($session->isValidSession());
    }
}
