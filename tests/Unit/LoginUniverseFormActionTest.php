<?php

declare(strict_types=1);

use HiveNova\Core\LoginUniverseFormAction;
use PHPUnit\Framework\TestCase;

class LoginUniverseFormActionTest extends TestCase
{
	public function test_register_does_not_use_universe_path(): void
	{
		$this->assertFalse(LoginUniverseFormAction::usesUniversePath('register'));
		$this->assertFalse(LoginUniverseFormAction::usesUniversePath('Register'));
		$this->assertSame('0', LoginUniverseFormAction::universePathAttribute('register'));
	}

	public function test_login_pages_use_universe_path(): void
	{
		$this->assertTrue(LoginUniverseFormAction::usesUniversePath('index'));
		$this->assertTrue(LoginUniverseFormAction::usesUniversePath('lostPassword'));
		$this->assertSame('1', LoginUniverseFormAction::universePathAttribute('index'));
	}

	public function test_register_build_keeps_lobby_action_even_for_internal_uni_id_6(): void
	{
		// novadev labels id 6 as "Universe 3"; register must never POST to /uni6/.
		$url = LoginUniverseFormAction::build(
			'https://novadev.hive.pizza/',
			'index.php?page=register',
			6,
			true,
			false,
			LoginUniverseFormAction::usesUniversePath('register')
		);

		$this->assertSame('index.php?page=register', $url);
		$this->assertStringNotContainsString('/uni6/', $url);
	}

	public function test_login_build_rewrites_to_universe_path(): void
	{
		$url = LoginUniverseFormAction::build(
			'https://novadev.hive.pizza/',
			'index.php?page=index',
			6,
			true,
			false,
			true
		);

		$this->assertSame('https://novadev.hive.pizza/uni6/index.php?page=index', $url);
	}

	public function test_wildcast_rewrites_host_prefix(): void
	{
		$url = LoginUniverseFormAction::build(
			'https://novadev.hive.pizza/',
			'index.php?page=index',
			1,
			true,
			true,
			true
		);

		$this->assertSame('https://uni1.novadev.hive.pizza/index.php?page=index', $url);
	}

	public function test_single_universe_keeps_relative_action(): void
	{
		$url = LoginUniverseFormAction::build(
			'https://example.test/',
			'index.php?page=index',
			1,
			false,
			false,
			true
		);

		$this->assertSame('index.php?page=index', $url);
	}
}
