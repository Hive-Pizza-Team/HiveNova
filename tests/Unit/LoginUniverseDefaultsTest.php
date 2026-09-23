<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\LoginUniverseDefaults;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

class LoginUniverseDefaultsTest extends TestCase
{
	/** @var array<int|string, Config> */
	private array $savedConfigCache = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->resetUniverseList([1, 2, 3]);
		$this->savedConfigCache = $this->getConfigCache();
		$this->clearConfigCache();
	}

	protected function tearDown(): void
	{
		$this->setConfigCache($this->savedConfigCache);
		$this->resetUniverseList([]);
		parent::tearDown();
	}

	public function test_for_email_prefers_universe_1_when_newer_universes_are_not_seasonal(): void
	{
		// novadev shape (#627): ids 1/5/6 are Universe 1/2/3 and season_mode
		// is off on every row. Password login and registration still land on Uni 1.
		$this->resetUniverseList([1, 5, 6]);
		Config::setInstance($this->uniConfig(1, 'Universe 1', open: true, seasonal: false, players: 10), 1);
		Config::setInstance($this->uniConfig(5, 'Universe 2', open: true, seasonal: false, players: 20), 5);
		Config::setInstance($this->uniConfig(6, 'Universe 3', open: true, seasonal: false, players: 30), 6);

		$this->assertSame(1, LoginUniverseDefaults::forEmail(false));
		$this->assertSame(1, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_email_uses_newest_non_seasonal_when_universe_1_is_closed(): void
	{
		$this->resetUniverseList([1, 5, 6]);
		Config::setInstance($this->uniConfig(1, 'Universe 1', open: false, seasonal: false, players: 0), 1);
		Config::setInstance($this->uniConfig(5, 'Universe 2', open: true, seasonal: false, players: 20), 5);
		Config::setInstance($this->uniConfig(6, 'Universe 3', open: true, seasonal: false, players: 30), 6);

		$this->assertSame(6, LoginUniverseDefaults::forEmail(false));
		$this->assertSame(6, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_email_registration_skips_universe_1_when_registration_is_closed(): void
	{
		$this->resetUniverseList([1, 6]);
		Config::setInstance($this->uniConfig(1, 'Universe 1', open: true, seasonal: false, players: 10, regClosed: true), 1);
		Config::setInstance($this->uniConfig(6, 'Universe 3', open: true, seasonal: false, players: 30), 6);

		$this->assertSame(1, LoginUniverseDefaults::forEmail(false));
		$this->assertSame(6, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_email_skips_seasonal_universe_1_for_a_non_seasonal_alternative(): void
	{
		$this->resetUniverseList([1, 5, 6]);
		Config::setInstance($this->uniConfig(1, 'Universe 1', open: true, seasonal: true, players: 10), 1);
		Config::setInstance($this->uniConfig(5, 'Universe 2', open: true, seasonal: false, players: 20), 5);
		Config::setInstance($this->uniConfig(6, 'Universe 3', open: true, seasonal: false, players: 30), 6);

		$this->assertSame(6, LoginUniverseDefaults::forEmail(false));
		$this->assertSame(6, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_email_login_prefers_non_seasonal(): void
	{
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 200), 1);
		Config::setInstance($this->uniConfig(2, 'Old Season', open: true, seasonal: true, players: 10), 2);
		Config::setInstance($this->uniConfig(3, 'New Season', open: true, seasonal: true, players: 17), 3);

		$this->assertSame(1, LoginUniverseDefaults::forEmail(false));
	}

	public function test_for_email_registration_skips_seasonal(): void
	{
		$this->resetUniverseList([1, 3]);
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 200), 1);
		Config::setInstance($this->uniConfig(3, 'New Season', open: true, seasonal: true, players: 17), 3);

		$this->assertSame(1, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_hive_prefers_most_players(): void
	{
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 227), 1);
		Config::setInstance($this->uniConfig(3, 'Season', open: true, seasonal: true, players: 17), 3);
		$this->resetUniverseList([1, 3]);

		$this->assertSame(1, LoginUniverseDefaults::forHive());
	}

	public function test_newest_open_skips_closed(): void
	{
		$this->resetUniverseList([1, 3]);
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 10), 1);
		Config::setInstance($this->uniConfig(3, 'Season', open: false, seasonal: true, players: 0), 3);

		$this->assertSame(1, LoginUniverseDefaults::newestOpen());
	}

	public function test_newest_open_skips_reg_closed_when_registering(): void
	{
		$this->resetUniverseList([1, 2]);
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 10, regClosed: false), 1);
		Config::setInstance($this->uniConfig(2, 'Busy', open: true, seasonal: false, players: 50, regClosed: true), 2);

		$this->assertSame(1, LoginUniverseDefaults::newestOpen(true));
	}

	public function test_newest_open_falls_back_when_all_disabled(): void
	{
		$this->resetUniverseList([1, 2]);
		Config::setInstance($this->uniConfig(1, 'A', open: false, seasonal: false, players: 0), 1);
		Config::setInstance($this->uniConfig(2, 'B', open: false, seasonal: false, players: 0), 2);

		$this->assertSame(2, LoginUniverseDefaults::newestOpen());
	}

	public function test_newest_open_falls_back_to_root_when_no_universes(): void
	{
		$this->resetUniverseList([]);
		$this->assertSame(ROOT_UNI, LoginUniverseDefaults::newestOpen());
	}

	public function test_for_email_registration_falls_back_when_only_seasonal(): void
	{
		$this->resetUniverseList([3]);
		Config::setInstance($this->uniConfig(3, 'Season', open: true, seasonal: true, players: 17), 3);

		$this->assertSame(3, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_email_login_falls_back_to_only_open_seasonal(): void
	{
		$this->resetUniverseList([3]);
		Config::setInstance($this->uniConfig(3, 'Season', open: true, seasonal: true, players: 17), 3);

		$this->assertSame(3, LoginUniverseDefaults::forEmail(false));
	}

	public function test_select_option_label_names_keychain_and_pizza_for_seasonal(): void
	{
		$suffix = 'Needs Hive Keychain + PIZZA entry';

		$this->assertSame(
			'Universe 3 — '.$suffix,
			LoginUniverseDefaults::selectOptionLabel('Universe 3', true, '', $suffix)
		);
		$this->assertSame(
			'Universe 3 — '.$suffix.' (closed)',
			LoginUniverseDefaults::selectOptionLabel('Universe 3', true, ' (closed)', $suffix)
		);
		$this->assertSame(
			'Universe 1',
			LoginUniverseDefaults::selectOptionLabel('Universe 1', false, '', $suffix)
		);
		$this->assertSame(
			'Universe 2 (closed)',
			LoginUniverseDefaults::selectOptionLabel('Universe 2', false, ' (closed)', $suffix)
		);
		$this->assertFalse(LoginUniverseDefaults::isSeasonal($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 1)));
		$this->assertTrue(LoginUniverseDefaults::isSeasonal($this->uniConfig(3, 'Season', open: true, seasonal: true, players: 1)));
	}

	public function test_for_email_login_falls_back_when_no_seasonal(): void
	{
		$this->resetUniverseList([1, 2]);
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 10), 1);
		Config::setInstance($this->uniConfig(2, 'Also Classic', open: false, seasonal: false, players: 0), 2);

		$this->assertSame(1, LoginUniverseDefaults::forEmail(false));
	}

	public function test_for_email_registration_skips_disabled_and_reg_closed(): void
	{
		$this->resetUniverseList([1, 2, 3]);
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 10), 1);
		Config::setInstance($this->uniConfig(2, 'Closed', open: true, seasonal: false, players: 20, regClosed: true), 2);
		Config::setInstance($this->uniConfig(3, 'Down', open: false, seasonal: false, players: 0), 3);

		$this->assertSame(1, LoginUniverseDefaults::forEmail(true));
	}

	public function test_for_hive_skips_disabled_and_reg_closed(): void
	{
		$this->resetUniverseList([1, 2, 3]);
		Config::setInstance($this->uniConfig(1, 'Quiet', open: true, seasonal: false, players: 5), 1);
		Config::setInstance($this->uniConfig(2, 'Busy closed', open: true, seasonal: false, players: 500, regClosed: true), 2);
		Config::setInstance($this->uniConfig(3, 'Down', open: false, seasonal: true, players: 999), 3);

		$this->assertSame(1, LoginUniverseDefaults::forHive(true));
	}

	public function test_is_open_for_registration_matches_send_gate(): void
	{
		$this->resetUniverseList([1, 2, 3, 4]);
		Config::setInstance($this->uniConfig(1, 'Open', open: true, seasonal: false, players: 10), 1);
		Config::setInstance($this->uniConfig(2, 'Reg closed', open: true, seasonal: false, players: 10, regClosed: true), 2);
		Config::setInstance($this->uniConfig(3, 'Disabled', open: false, seasonal: false, players: 0), 3);
		Config::setInstance($this->uniConfig(4, 'Seasonal open', open: true, seasonal: true, players: 17), 4);

		$this->assertTrue(LoginUniverseDefaults::isOpenForRegistration(1));
		$this->assertTrue(LoginUniverseDefaults::isOpenForRegistration(4));
		$this->assertFalse(LoginUniverseDefaults::isOpenForRegistration(2));
		$this->assertFalse(LoginUniverseDefaults::isOpenForRegistration(3));
		$this->assertFalse(LoginUniverseDefaults::isOpenForRegistration(99));
		$this->assertFalse(LoginUniverseDefaults::isOpenForRegistration(0));
	}

	public function test_is_open_for_registration_fails_closed_without_config(): void
	{
		$this->resetUniverseList([7]);
		Config::setInstance($this->uniConfig(1, 'Other', open: true, seasonal: false, players: 1), 1);

		$this->assertFalse(LoginUniverseDefaults::isOpenForRegistration(7));
	}

	/**
	 * @param list<int> $ids
	 */
	private function resetUniverseList(array $ids): void
	{
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, $ids);

		$cur = new ReflectionProperty(Universe::class, 'currentUniverse');
		$cur->setAccessible(true);
		$cur->setValue(null, null);
	}

	/**
	 * @return array<int|string, Config>
	 */
	private function getConfigCache(): array
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$value = $ref->getValue(null);

		return is_array($value) ? $value : [];
	}

	private function clearConfigCache(): void
	{
		$this->setConfigCache([]);
	}

	/**
	 * @param array<int|string, Config> $cache
	 */
	private function setConfigCache(array $cache): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, $cache);
	}

	private function uniConfig(
		int $id,
		string $name,
		bool $open,
		bool $seasonal,
		int $players,
		bool $regClosed = false
	): Config {
		return new Config([
			'uni' => $id,
			'uni_name' => $name,
			'game_disable' => $open ? 1 : 0,
			'reg_closed' => $regClosed ? 1 : 0,
			'season_mode' => $seasonal ? 1 : 0,
			'users_amount' => $players,
		]);
	}
}
