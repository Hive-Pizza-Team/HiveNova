<?php

use HiveNova\Core\AcsJoinService;
use HiveNova\Core\Config;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class AcsJoinServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		if (!defined('MODULE_MISSION_ACS')) {
			define('MODULE_MISSION_ACS', 42);
		}

		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);

		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 50, 1)),
			'max_fleets_per_acs' => 2,
		]), 1);
	}

	protected function tearDown(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);

		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testInviteMessageLinksTheExistingGroup(): void
	{
		$message = AcsJoinService::inviteMessage('Player ', 'Reef', ' invited to ACS.', 'Join the attack', 42);

		$this->assertStringContainsString('Player Reef invited to ACS.', $message);
		$this->assertStringContainsString('href="game.php?page=fleetTable&amp;joinAcs=42"', $message);
		$this->assertStringContainsString('Join the attack', $message);
	}

	public function testExplicitGroupForcesAcsMission(): void
	{
		$this->assertSame(FLEET_MISSION_ACS, AcsJoinService::missionAfterGroup(9, 9, FLEET_MISSION_ATTACK));
		$this->assertSame(FLEET_MISSION_ACS, AcsJoinService::missionAfterGroup(0, 9, 0));
		$this->assertSame(FLEET_MISSION_ATTACK, AcsJoinService::missionAfterGroup(0, 9, FLEET_MISSION_ATTACK));
		$this->assertSame(9, AcsJoinService::resolveGroup(0, 9));
		$this->assertSame(4, AcsJoinService::resolveGroup(4, 9));
	}

	public function testListAndLockFollowMembershipAndFleetCap(): void
	{
		$this->fake->acsInvites = [
			$this->invite(2, 9, 50),
			$this->invite(7, 9, 50),
		];
		$this->fake->fleetRowsById = [
			1 => ['fleet_group' => 9],
		];

		$list = AcsJoinService::listForUser(7);
		$this->assertCount(1, $list);
		$this->assertSame(9, (int) $list[0]['id']);
		$this->assertSame(1, (int) $list[0]['galaxy']);
		$this->assertSame(50, (int) $list[0]['system']);

		$this->assertSame(9, AcsJoinService::invitedGroupAtTarget(7, 50));
		$this->assertSame(0, AcsJoinService::invitedGroupAtTarget(7, 99));
		$this->assertSame(0, AcsJoinService::invitedGroupAtTarget(3, 50));

		$locked = AcsJoinService::lockJoin(7, 9, 50);
		$this->assertNotNull($locked);
		$this->assertSame(9, $locked['id']);
		$this->assertSame(1700000000, $locked['ankunft']);

		$this->assertNull(AcsJoinService::lockJoin(3, 9, 50));
		$this->assertNull(AcsJoinService::lockJoin(7, 9, 99));

		$target = AcsJoinService::targetForMember(7, 9);
		$this->assertNotNull($target);
		$this->assertSame(9, $target['planet']);
		$this->assertNull(AcsJoinService::targetForMember(3, 9));
	}

	public function testFullAcsIsNotJoinable(): void
	{
		$this->fake->acsInvites = [$this->invite(7, 9, 50)];
		$this->fake->fleetRowsById = [
			1 => ['fleet_group' => 9],
			2 => ['fleet_group' => 9],
		];

		$this->assertSame([], AcsJoinService::listForUser(7));
		$this->assertSame(0, AcsJoinService::invitedGroupAtTarget(7, 50));
		$this->assertNull(AcsJoinService::lockJoin(7, 9, 50));
		$this->assertNull(AcsJoinService::targetForMember(7, 9));
	}

	public function testDisabledModuleHidesJoins(): void
	{
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 50, 0)),
			'max_fleets_per_acs' => 16,
		]), 1);

		$this->fake->acsInvites = [$this->invite(7, 9, 50)];

		$this->assertSame([], AcsJoinService::listForUser(7));
		$this->assertSame(0, AcsJoinService::invitedGroupAtTarget(7, 50));
		$this->assertNull(AcsJoinService::lockJoin(7, 9, 50));
		$this->assertNull(AcsJoinService::targetForMember(7, 9));
	}

	/**
	 * @return array<string, int|string>
	 */
	private function invite(int $userId, int $acsId, int $targetPlanetId): array
	{
		return [
			'userId' => $userId,
			'acsId' => $acsId,
			'name' => 'AG1',
			'target' => $targetPlanetId,
			'ankunft' => 1700000000,
			'galaxy' => 1,
			'system' => 50,
			'planet' => 9,
			'planet_type' => 1,
		];
	}
}
