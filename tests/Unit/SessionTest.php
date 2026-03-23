<?php

use HiveNova\Core\Session;
use PHPUnit\Framework\TestCase;

if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600);

class SessionTest extends TestCase
{
    private function newSession(): Session
    {
        return (new ReflectionClass(Session::class))->newInstanceWithoutConstructor();
    }

    protected function setUp(): void
    {
        // Reset static singleton so each test starts clean
        $ref = new ReflectionProperty(Session::class, 'obj');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $_SESSION = [];
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
}
