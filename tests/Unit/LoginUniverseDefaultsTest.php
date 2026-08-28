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

	public function test_for_email_login_prefers_newest_seasonal(): void
	{
		Config::setInstance($this->uniConfig(1, 'Classic', open: true, seasonal: false, players: 200), 1);
		Config::setInstance($this->uniConfig(2, 'Old Season', open: true, seasonal: true, players: 10), 2);
		Config::setInstance($this->uniConfig(3, 'New Season', open: true, seasonal: true, players: 17), 3);

		$this->assertSame(3, LoginUniverseDefaults::forEmail(false));
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
		int $players
	): Config {
		return new Config([
			'uni' => $id,
			'uni_name' => $name,
			'game_disable' => $open ? 1 : 0,
			'reg_closed' => 0,
			'season_mode' => $seasonal ? 1 : 0,
			'users_amount' => $players,
		]);
	}
}
