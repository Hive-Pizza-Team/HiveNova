<?php

use HiveNova\Core\Config;
use HiveNova\Core\Uni3AccountHooks;
use HiveNova\Core\Uni3PilotLog;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class Uni3AccountHooksTest extends TestCase
{
	use SwapDatabaseInstance;

	/** @var array<int|string, Config> */
	private array $saved = [];

	protected function setUp(): void
	{
		parent::setUp();
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$value = $ref->getValue(null);
		$this->saved = is_array($value) ? $value : [];
		$ref->setValue(null, []);
		$this->swapDatabaseInstance(new RecordingDatabase());
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, $this->saved);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testEmailRegisterOnSeasonalUniverseIsLogged(): void
	{
		Config::setInstance(new Config([
			'uni' => 3,
			'season_mode' => 1,
			'season_id' => 4,
		]), 3);

		Uni3AccountHooks::onPlayerCreated(3, 15, '');
		$this->assertContains(Uni3PilotLog::REGISTER_UNI3_EMAIL, $this->pilotInserts());
		$sql = implode("\n", array_map(static fn (array $row): string => $row[0], $this->database()->inserts));
		$this->assertStringNotContainsString('%%SEASON_HIVE_LINKS%%', $sql);
	}

	public function testKeychainRegisterBindsTheSeasonSeat(): void
	{
		Config::setInstance(new Config([
			'uni' => 3,
			'season_mode' => 1,
			'season_id' => 4,
		]), 3);
		Uni3AccountHooks::onPlayerCreated(3, 16, 'aliceaaa');
		$events = $this->pilotInserts();
		$this->assertContains(Uni3PilotLog::HIVE_LINK, $events);
		$sql = implode("\n", array_map(static fn (array $row): string => $row[0], $this->database()->inserts));
		$this->assertStringContainsString('%%SEASON_HIVE_LINKS%%', $sql);
	}

	public function testNonSeasonalRegisterIsIgnored(): void
	{
		Config::setInstance(new Config([
			'uni' => 1,
			'season_mode' => 0,
			'season_id' => 0,
		]), 1);
		Uni3AccountHooks::onPlayerCreated(1, 16, '');
		$this->assertSame([], $this->database()->inserts);
	}

	private function database(): RecordingDatabase
	{
		$ref = new ReflectionProperty(\HiveNova\Core\Database::class, 'instance');
		$ref->setAccessible(true);
		$db = $ref->getValue(null);
		$this->assertInstanceOf(RecordingDatabase::class, $db);

		return $db;
	}

	/**
	 * @return list<string>
	 */
	private function pilotInserts(): array
	{
		$events = [];
		foreach ($this->database()->inserts as $insert) {
			if (($insert[1][':event'] ?? '') !== '') {
				$events[] = (string) $insert[1][':event'];
			}
		}

		return $events;
	}
}
