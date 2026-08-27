<?php

use HiveNova\Core\AdminCsrf;
use PHPUnit\Framework\TestCase;

class AdminCsrfTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		$_SESSION = [];
		$_GET = [];
		$_POST = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	protected function tearDown(): void
	{
		$_SESSION = [];
		$_GET = [];
		$_POST = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		parent::tearDown();
	}

	public function testTokenIsStableWithinSession(): void
	{
		$first = AdminCsrf::token();
		$second = AdminCsrf::token();

		$this->assertNotSame('', $first);
		$this->assertSame($first, $second);
		$this->assertTrue(AdminCsrf::isValid($first));
	}

	public function testRejectsMissingAndWrongTokens(): void
	{
		AdminCsrf::token();
		$this->assertFalse(AdminCsrf::isValid(null));
		$this->assertFalse(AdminCsrf::isValid(''));
		$this->assertFalse(AdminCsrf::isValid('not-the-token'));
	}

	public function testAcceptsOnlyDedicatedToken(): void
	{
		$token = AdminCsrf::token();
		$this->assertTrue(AdminCsrf::isValid($token));
		$sid = session_id();
		$this->assertNotSame('', $sid);
		$this->assertFalse(AdminCsrf::isValid($sid));
	}

	public function testMutatingDetectionForPostAndKnownGetPages(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertTrue(AdminCsrf::isMutatingRequest('config'));

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertFalse(AdminCsrf::isMutatingRequest('clearcache'));
		$this->assertFalse(AdminCsrf::isMutatingRequest('statsupdate'));
		$this->assertFalse(AdminCsrf::isMutatingRequest('config'));
		$this->assertFalse(AdminCsrf::isMutatingRequest('module'));
	}

	public function testNonGetNonPostIsNotMutating(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$this->assertFalse(AdminCsrf::isMutatingRequest('config'));
	}

	public function testRequestTokenReadsPostField(): void
	{
		$token = AdminCsrf::token();
		$_POST['admin_csrf'] = $token;
		$this->assertTrue(AdminCsrf::isValidRequest());

		$_POST = [];
		$_GET['sid'] = session_id();
		$this->assertFalse(AdminCsrf::isValidRequest());
	}

	public function testRequestTokenReadsGetField(): void
	{
		$token = AdminCsrf::token();
		$_GET['admin_csrf'] = $token;
		$this->assertSame($token, AdminCsrf::requestToken());
		$this->assertTrue(AdminCsrf::isValidRequest());
	}

	public function testEnforceAllowsSafeGetWithoutToken(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		AdminCsrf::enforce('overview');
		$this->assertTrue(true);
	}

	public function testEnforceAllowsPostWithValidToken(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['admin_csrf'] = AdminCsrf::token();
		AdminCsrf::enforce('config');
		$this->assertTrue(true);
	}

	public function testTokenStartsSessionWhenInactive(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}

		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
		$token = AdminCsrf::token();
		$this->assertNotSame('', $token);
		$this->assertSame(PHP_SESSION_ACTIVE, session_status());
	}
}
