<?php

use HiveNova\Core\Config;
use HiveNova\Mission\MissionCaseFoundDM;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/MissionFleetFixtures.php';

/**
 * Controllable FoundDM mission for deterministic three-way outcome tests.
 */
class TestableMissionCaseFoundDM extends MissionCaseFoundDM
{
	public int $percentRoll = 50;
	public int $foundAmount = 500;
	public int $messageVariant = 1;

	protected function rollPercent()
	{
		return $this->percentRoll;
	}

	protected function rollFoundAmount()
	{
		return $this->foundAmount;
	}

	protected function rollMessageVariant($min, $max)
	{
		return max($min, min($max, $this->messageVariant));
	}
}

class MissionCaseFoundDMTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);

		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 50, 1)),
		]), 1);

		$this->fake->achievement->users[1] = [
			'id' => 1,
			'lang' => 'en',
			'universe' => 1,
		];
		$this->fake->planetRowsById[10] = [
			'id' => 10,
			'name' => 'Home',
			'id_owner' => 1,
		];

		$GLOBALS['resource'][220] = 'dm_ship';
		$GLOBALS['pricelist'][220] = [
			'cost' => [901 => 8000000, 902 => 8000000, 903 => 4000000],
			'capacity' => 6000000,
			'factor' => 0,
		];
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);

		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collectorFleet(array $overrides = []): array
	{
		return missionFleetFixture(array_merge([
			'fleet_mission' => 11,
			'fleet_array' => '220,2;',
			'fleet_amount' => 2,
			'fleet_resource_darkmatter' => 0,
			'fleet_start_id' => 10,
			'fleet_end_id' => 10,
		], $overrides));
	}

	public function test_end_stay_success_sets_darkmatter(): void
	{
		$mission = new TestableMissionCaseFoundDM($this->collectorFleet());
		$mission->percentRoll = 15;
		$mission->foundAmount = 777;
		$mission->EndStayEvent();

		$this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
		$this->assertSame(777, $mission->_fleet['fleet_resource_darkmatter']);
		$this->assertSame(2, (int) $mission->_fleet['fleet_amount']);
		$this->assertNotEmpty($this->fake->achievement->messages);
	}

	public function test_end_stay_nothing_keeps_ships_and_no_dm(): void
	{
		$mission = new TestableMissionCaseFoundDM($this->collectorFleet());
		$mission->percentRoll = 99;
		$mission->EndStayEvent();

		$this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
		$this->assertSame(0, (int) $mission->_fleet['fleet_resource_darkmatter']);
		$this->assertSame(2, (int) $mission->_fleet['fleet_amount']);
		$this->assertSame('220,2;', $mission->_fleet['fleet_array']);
	}

	public function test_end_stay_whammy_scraps_ships_without_dm(): void
	{
		$mission = new TestableMissionCaseFoundDM($this->collectorFleet());
		$mission->percentRoll = 5;
		$mission->EndStayEvent();

		$this->assertSame(FLEET_RETURN, $mission->_fleet['fleet_mess']);
		$this->assertSame(0, (int) $mission->_fleet['fleet_resource_darkmatter']);
		$this->assertSame(0, (int) $mission->_fleet['fleet_amount']);
		$this->assertSame('220,0;', $mission->_fleet['fleet_array']);
	}

	public function test_return_success_scraps_ships(): void
	{
		$mission = new TestableMissionCaseFoundDM($this->collectorFleet([
			'fleet_resource_darkmatter' => 500,
			'fleet_array' => '220,2;',
			'fleet_amount' => 2,
		]));
		$mission->ReturnEvent();

		$this->assertSame('220,0;', $mission->_fleet['fleet_array']);
		$this->assertSame(0, (int) $mission->_fleet['fleet_amount']);
		$this->assertSame(1, $mission->kill);
		$this->assertTrue($this->hasMessageContaining('scrapped'));
	}

	public function test_return_nothing_keeps_ships(): void
	{
		$mission = new TestableMissionCaseFoundDM($this->collectorFleet([
			'fleet_resource_darkmatter' => 0,
			'fleet_array' => '220,2;',
			'fleet_amount' => 2,
		]));
		$mission->ReturnEvent();

		$this->assertSame('220,2;', $mission->_fleet['fleet_array']);
		$this->assertSame(2, (int) $mission->_fleet['fleet_amount']);
		$this->assertSame(1, $mission->kill);
		$this->assertTrue($this->hasMessageContaining('returned from the expedition'));
		$this->assertFalse($this->hasMessageContaining('destroyed and no Pizzabits'));
	}

	public function test_return_whammy_reports_loss_without_payout(): void
	{
		$mission = new TestableMissionCaseFoundDM($this->collectorFleet([
			'fleet_resource_darkmatter' => 0,
			'fleet_array' => '220,0;',
			'fleet_amount' => 0,
		]));
		$mission->ReturnEvent();

		$this->assertSame(1, $mission->kill);
		$this->assertTrue($this->hasMessageContaining('destroyed and no Pizzabits'));
	}

	private function hasMessageContaining(string $needle): bool
	{
		foreach ($this->fake->achievement->messages as $message) {
			$text = (string) ($message[':text'] ?? $message[':message'] ?? json_encode($message));
			if (str_contains($text, $needle)) {
				return true;
			}
		}

		return false;
	}
}
