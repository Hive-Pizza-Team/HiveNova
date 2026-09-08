<?php

use HiveNova\Core\ApiRouteTable;
use HiveNova\Core\ApiTickClass;
use HiveNova\Core\Session;
use PHPUnit\Framework\TestCase;

class ApiRouteTableTest extends TestCase
{
	public function testPollEndpointsSkipEconomy(): void
	{
		$this->assertSame(ApiTickClass::Poll, ApiRouteTable::tickClass('alerts', 'show', 'GET'));
		$this->assertSame(ApiTickClass::Poll, ApiRouteTable::tickClass('events', 'show', 'GET'));
		$this->assertSame(ApiTickClass::Poll, ApiRouteTable::tickClass('i18n', 'show', 'GET'));
		$this->assertFalse(ApiTickClass::Poll->runsEconomy());
	}

	public function testBootstrapAndOverviewAreReads(): void
	{
		$this->assertSame(ApiTickClass::Read, ApiRouteTable::tickClass('bootstrap', 'show', 'GET'));
		$this->assertSame(ApiTickClass::Read, ApiRouteTable::tickClass('overview', 'show', 'GET'));
		$this->assertTrue(ApiTickClass::Read->runsEconomy());
	}

	public function testRenameIsMutate(): void
	{
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('overview', 'rename', 'POST'));
		$this->assertTrue(ApiTickClass::Mutate->runsEconomy());
	}

	public function testI18nResourceKeepsDigits(): void
	{
		$this->assertSame('i18n', ApiRouteTable::sanitizeResource('i18n'));
		$this->assertSame('i18n', ApiRouteTable::sanitizeResource('I18N'));
		$this->assertTrue(ApiRouteTable::isKnown(ApiRouteTable::sanitizeResource('i18n')));
	}

	public function testOverviewDeleteIsUnknownAction(): void
	{
		$this->assertFalse(ApiRouteTable::isKnownAction('overview', 'delete'));
		$this->assertSame(ApiTickClass::Read, ApiRouteTable::tickClass('overview', 'delete', 'POST'));
		$this->assertTrue(ApiRouteTable::isKnownAction('overview', 'rename'));
		$this->assertTrue(ApiRouteTable::isKnownAction('overview', 'show'));
	}

	public function testUnknownResourceIsNotKnown(): void
	{
		$this->assertFalse(ApiRouteTable::isKnown('alliance'));
		$this->assertTrue(ApiRouteTable::isKnown('bootstrap'));
	}

	public function testSeasonAliasMapsChromeToOverview(): void
	{
		$this->assertSame('overview', ApiRouteTable::seasonPageAlias('bootstrap'));
		$this->assertSame('overview', ApiRouteTable::seasonPageAlias('alerts'));
	}

	public function testSessionCookiePathIsRoot(): void
	{
		$this->assertSame('/', Session::cookiePath());
	}
}
