<?php

use HiveNova\Core\ApiCsrf;
use PHPUnit\Framework\TestCase;

class ApiCsrfTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		$_SESSION = [];
		$_SERVER['HTTP_X_CSRF_TOKEN'] = '';
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
	}

	public function testTokenIsStableWithinSession(): void
	{
		$first = ApiCsrf::token();
		$second = ApiCsrf::token();
		$this->assertNotSame('', $first);
		$this->assertSame($first, $second);
		$this->assertTrue(ApiCsrf::isValid($first));
	}

	public function testRejectsMissingAndWrongTokens(): void
	{
		ApiCsrf::token();
		$this->assertFalse(ApiCsrf::isValid(null));
		$this->assertFalse(ApiCsrf::isValid(''));
		$this->assertFalse(ApiCsrf::isValid('nope'));
		$this->assertFalse(ApiCsrf::isValid(session_id()));
	}

	public function testHeaderToken(): void
	{
		$token = ApiCsrf::token();
		$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
		$this->assertTrue(ApiCsrf::isValidHeader());
		$_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
		$this->assertFalse(ApiCsrf::isValidHeader());
	}

	public function testRejectMutateCrossSiteAndBadToken(): void
	{
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
		$fail = ApiCsrf::rejectMutate();
		$this->assertSame('csrf', $fail['error']);
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
		$_SERVER['HTTP_X_CSRF_TOKEN'] = 'nope';
		$fail = ApiCsrf::rejectMutate();
		$this->assertSame('csrf', $fail['error']);
		$_SERVER['HTTP_X_CSRF_TOKEN'] = ApiCsrf::token();
		$this->assertNull(ApiCsrf::rejectMutate());
	}
}
