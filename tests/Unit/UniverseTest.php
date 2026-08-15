<?php

use HiveNova\Core\Database;
use HiveNova\Core\Session;
use HiveNova\Core\Universe;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';

class UniverseTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedCookie = [];

    /** @var array<string, mixed> */
    private array $savedRequest = [];

    /** @var array<string, mixed>|null */
    private ?array $savedSession = null;

    protected function setUp(): void
    {
        if (!defined('SESSION_LIFETIME')) {
            define('SESSION_LIFETIME', 3600);
        }
        if (!defined('HTTPS')) {
            define('HTTPS', false);
        }
        if (!defined('HTTP_ROOT')) {
            define('HTTP_ROOT', '/');
        }
        if (!defined('HTTP_BASE')) {
            define('HTTP_BASE', '/');
        }
        if (!defined('HTTP_FILE')) {
            define('HTTP_FILE', 'index.php');
        }
        if (!defined('HTTP_HOST')) {
            define('HTTP_HOST', '127.0.0.1');
        }
        if (!defined('PROTOCOL')) {
            define('PROTOCOL', 'http://');
        }
        if (!defined('HTTP_PATH')) {
            define('HTTP_PATH', PROTOCOL . HTTP_HOST . HTTP_ROOT);
        }
        if (!defined('UNIS_WILDCAST')) {
            define('UNIS_WILDCAST', false);
        }
        if (!defined('PREVENT_MULTISESSIONS')) {
            define('PREVENT_MULTISESSIONS', false);
        }

        $this->resetUniverseStatics();
        $this->resetSessionSingleton();

        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, null);
        Database::setInstance(new FakeDatabase());

        if (!is_dir(CACHE_PATH . 'sessions')) {
            mkdir(CACHE_PATH . 'sessions', 0777, true);
        }

        $this->savedServer = $_SERVER;
        $this->savedCookie = $_COOKIE;
        $this->savedRequest = $_REQUEST;
        $this->savedSession = $_SESSION ?? [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');

        $_SERVER = $this->savedServer;
        $_COOKIE = $this->savedCookie;
        $_REQUEST = $this->savedRequest;
        $_SESSION = $this->savedSession ?? [];

        $this->resetUniverseStatics();
        $this->resetSessionSingleton();

        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, null);

        parent::tearDown();
    }

    private function resetUniverseStatics(): void
    {
        foreach (['currentUniverse', 'emulatedUniverse', 'availableUniverses'] as $property) {
            $ref = new ReflectionProperty(Universe::class, $property);
            $ref->setAccessible(true);
            $ref->setValue(null, $property === 'availableUniverses' ? [] : null);
        }
    }

    private function resetSessionSingleton(): void
    {
        $ref = new ReflectionProperty(Session::class, 'obj');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $iniRef = new ReflectionProperty(Session::class, 'iniSet');
        $iniRef->setAccessible(true);
        $iniRef->setValue(null, false);
    }

    private function newSessionWithoutConstructor(): Session
    {
        return (new ReflectionClass(Session::class))->newInstanceWithoutConstructor();
    }

    /** @param array<string, mixed>|null $data */
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

    public function testAddAndAvailableUniverses(): void
    {
        Universe::add(1);
        Universe::add(2);

        $this->assertSame([1, 2], Universe::availableUniverses());
    }

    public function testExistsReturnsTrueForRegisteredUniverse(): void
    {
        Universe::add(1);
        Universe::add(3);

        $this->assertTrue(Universe::exists(1));
        $this->assertTrue(Universe::exists(3));
    }

    public function testExistsReturnsFalseForUnknownUniverse(): void
    {
        Universe::add(1);

        $this->assertFalse(Universe::exists(99));
    }

    public function testCurrentReturnsRootUniInInstallMode(): void
    {
        $this->assertSame(ROOT_UNI, Universe::current());
    }

    public function testCurrentCachesResult(): void
    {
        $first = Universe::current();

        Universe::add(99);

        $this->assertSame($first, Universe::current());
    }

    public function testSetEmulatedThrowsForUnknownUniverse(): void
    {
        Universe::add(1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown universe ID: 5');
        Universe::setEmulated(5);
    }

    public function testSetEmulatedPersistsToSession(): void
    {
        Universe::add(1);
        Universe::add(2);

        $session = $this->newSessionWithoutConstructor();
        $this->setSessionData($session, ['userId' => 42]);
        $this->setSessionSingleton($session);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->assertTrue(Universe::setEmulated(2));
        $this->assertSame(2, Universe::getEmulated());
        $this->assertSame(2, Session::load()->emulatedUniverse);
    }

    public function testGetEmulatedUsesSessionValue(): void
    {
        Universe::add(1);
        Universe::add(2);

        $session = $this->newSessionWithoutConstructor();
        $this->setSessionData($session, [
            'userId' => 7,
            'emulatedUniverse' => 2,
        ]);
        $this->setSessionSingleton($session);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->assertSame(2, Universe::getEmulated());
    }

    public function testGetEmulatedDefaultsToCurrentWhenSessionUnset(): void
    {
        Universe::add(1);

        $session = $this->newSessionWithoutConstructor();
        $this->setSessionData($session, ['userId' => 7]);
        $this->setSessionSingleton($session);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->assertSame(ROOT_UNI, Universe::getEmulated());
    }
}
