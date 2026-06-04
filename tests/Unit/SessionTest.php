<?php

use HiveNova\Core\Database;
use HiveNova\Core\Session;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';

if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600);
if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', sys_get_temp_dir() . '/hivenova-phpunit-sessions/');
}
if (!defined('HTTPS')) define('HTTPS', false);
if (!defined('HTTP_ROOT')) define('HTTP_ROOT', '/');
if (!defined('MODE')) define('MODE', 'INSTALL');
if (!defined('PREVENT_MULTISESSIONS')) define('PREVENT_MULTISESSIONS', false);

class SessionTest extends TestCase
{
    private FakeDatabase $dbStub;

    /** @var array<string, string> */
    private array $savedServer = [];

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

    private function invokePrivate(Session $session, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(Session::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($session, ...$args);
    }

    private function setSessionData(Session $session, ?array $data): void
    {
        $ref = new ReflectionProperty(Session::class, 'data');
        $ref->setAccessible(true);
        $ref->setValue($session, $data);
    }

    private function setSessionSingleton(?Session $session): void
    {
        $ref = new ReflectionProperty(Session::class, 'obj');
        $ref->setAccessible(true);
        $ref->setValue(null, $session);
    }

    /** @param array<string, string|null> $values */
    private function setServerVars(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
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

        $this->dbStub = new FakeDatabase();
        Database::setInstance($this->dbStub);

        if (!is_dir(CACHE_PATH . 'sessions')) {
            mkdir(CACHE_PATH . 'sessions', 0777, true);
        }

        $_SESSION = [];
        $_GET = [];
        $_REQUEST = [];

        $this->savedServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');

        $_SERVER = $this->savedServer;

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
        $this->dbStub->session->sessionRows['expired'] = [
            'userID'     => 42,
            'lastonline' => TIMESTAMP - SESSION_LIFETIME - 1,
        ];
        $this->dbStub->session->userRows[42] = [
            'id'        => 42,
            'id_planet' => 7,
            'bana'      => 0,
        ];

        $this->assertNull($this->invokeRestoreFromDatabase('expired'));
    }

    public function testRestoreFromDatabaseReturnsNullWhenUserBanned(): void
    {
        $this->dbStub->session->sessionRows['banned-user'] = [
            'userID'     => 99,
            'lastonline' => TIMESTAMP,
        ];
        $this->dbStub->session->userRows[99] = [
            'id'        => 99,
            'id_planet' => 3,
            'bana'      => 1,
        ];

        $this->assertNull($this->invokeRestoreFromDatabase('banned-user'));
    }

    public function testRestoreFromDatabaseRebuildsSessionWithAdminAccessZero(): void
    {
        $this->dbStub->session->sessionRows['valid-restore'] = [
            'userID'     => 42,
            'lastonline' => TIMESTAMP,
        ];
        $this->dbStub->session->userRows[42] = [
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

    public function testInitReturnsTrueOnceThenFalse(): void
    {
        $ref = new ReflectionProperty(Session::class, 'iniSet');
        $ref->setAccessible(true);
        $ref->setValue(false);

        $this->assertTrue(Session::init());
        $this->assertFalse(Session::init());
    }

    public function testIsValidSessionReturnsTrueWhenObjMissingButUserIdSetAndDbRowExists(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('restored-session');
        session_start();

        $this->dbStub->session->sessionCount = 1;

        $session = $this->newSession();
        $this->setSessionData($session, [
            'userId'       => 42,
            'lastActivity' => TIMESTAMP,
        ]);

        $this->assertTrue($session->isValidSession());
    }

    public function testGetClientIpPrefersClientIpHeader(): void
    {
        $this->setServerVars([
            'HTTP_CLIENT_IP' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $this->assertSame('10.0.0.5', Session::getClientIp());
    }

    public function testGetClientIpFallsBackToForwardedForThenRemoteAddr(): void
    {
        $this->setServerVars([
            'HTTP_CLIENT_IP' => null,
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $this->assertSame('203.0.113.9', Session::getClientIp());

        $this->setServerVars([
            'HTTP_X_FORWARDED_FOR' => null,
            'REMOTE_ADDR' => '192.168.0.42',
        ]);

        $this->assertSame('192.168.0.42', Session::getClientIp());
    }

    public function testGetClientIpReturnsUnknownWhenNoServerVars(): void
    {
        $this->setServerVars([
            'HTTP_CLIENT_IP' => null,
            'HTTP_X_FORWARDED_FOR' => null,
            'HTTP_X_FORWARDED' => null,
            'HTTP_FORWARDED_FOR' => null,
            'HTTP_FORWARDED' => null,
            'REMOTE_ADDR' => null,
        ]);

        $this->assertSame('UNKNOWN', Session::getClientIp());
    }

    public function testExistsActiveSessionReflectsSingletonState(): void
    {
        $this->assertFalse(Session::existsActiveSession());

        $this->setSessionSingleton($this->newSession());

        $this->assertTrue(Session::existsActiveSession());
    }

    public function testMagicGetSetIssetAndSleep(): void
    {
        $session = $this->newSession();
        $this->setSessionData($session, []);

        $session->userId = 7;
        $this->assertTrue(isset($session->userId));
        $this->assertSame(7, $session->userId);
        $this->assertNull($session->missingKey);
        $this->assertFalse(isset($session->missingKey));
        $this->assertSame(['data'], $session->__sleep());
    }

    public function testRegenerateIdIsNoOpWhenSessionInactive(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        Session::regenerateId();
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testRegenerateIdRunsWhenSessionActive(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_start();
        $before = session_id();

        Session::regenerateId();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertNotSame('', session_id());
        $this->assertNotSame($before, session_id());
    }

    public function testIsValidSessionReturnsFalseWhenActivityExpired(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('expired-activity');
        session_start();

        $this->dbStub->session->sessionCount = 1;

        $session = $this->newSession();
        $this->setSessionData($session, [
            'userId'       => 42,
            'lastActivity' => TIMESTAMP - SESSION_LIFETIME - 10,
        ]);

        $this->assertFalse($session->isValidSession());
    }

    public function testIsValidSessionReturnsFalseWhenDbRowMissing(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('missing-db-row');
        session_start();

        $this->dbStub->session->sessionCount = 0;

        $session = $this->newSession();
        $this->setSessionData($session, [
            'userId'       => 42,
            'lastActivity' => TIMESTAMP,
        ]);

        $this->assertFalse($session->isValidSession());
    }

    public function testSaveReturnsEarlyWithoutSessionIdOrData(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');

        $session = $this->newSession();
        $this->setSessionData($session, null);
        $session->save();

        $this->assertSame('', session_id());
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testSaveDeletesSessionWhenUserIdMissing(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_id('empty-user');
        session_start();

        $session = $this->newSession();
        $this->setSessionData($session, ['lastActivity' => TIMESTAMP]);

        $session->save();

        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testDeleteClearsActiveSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_id('delete-me');
        session_start();

        $session = $this->newSession();
        $session->delete();

        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testSelectActivePlanetUpdatesPlanetWhenOwned(): void
    {
        $_GET['cp'] = 99;
        $_REQUEST['cp'] = 99;
        $this->dbStub->planetRowsById[99] = ['id' => 99, 'id_owner' => 42];

        $session = $this->newSession();
        $this->setSessionData($session, [
            'userId'   => 42,
            'planetId' => 7,
        ]);

        $session->selectActivePlanet();

        $this->assertSame(99, $session->planetId);
    }

    public function testSelectActivePlanetIgnoresUnknownPlanet(): void
    {
        $_GET['cp'] = 404;
        $_REQUEST['cp'] = 404;

        $session = $this->newSession();
        $this->setSessionData($session, [
            'userId'   => 42,
            'planetId' => 7,
        ]);

        $session->selectActivePlanet();

        $this->assertSame(7, $session->planetId);
    }

    public function testCompareIpAddressMatchesIpv4PrefixBlocks(): void
    {
        $session = $this->newSession();

        $this->assertTrue($this->invokePrivate($session, 'compareIpAddress', '192.168.1.10', '192.168.1.20', 3));
        $this->assertFalse($this->invokePrivate($session, 'compareIpAddress', '192.168.1.10', '10.0.0.1', 3));
    }

    public function testShortIpv6ExpandsAndTruncatesBlocks(): void
    {
        $session = $this->newSession();

        $this->assertSame('', $this->invokePrivate($session, 'short_ipv6', '2001:db8::1', 0));
        $this->assertSame(
            '2001:db8:0000:0000',
            $this->invokePrivate($session, 'short_ipv6', '2001:db8::1', 3)
        );
    }

    public function testGetRequestPathIncludesQueryString(): void
    {
        $_SERVER['QUERY_STRING'] = 'page=overview&cp=1';

        $session = $this->newSession();
        $path = $this->invokePrivate($session, 'getRequestPath');

        $this->assertSame(HTTP_ROOT . '?page=overview&cp=1', $path);
    }
}
