<?php

use HiveNova\Core\Uni3ClaimGate;
use HiveNova\Core\Uni3HiveLinkService;
use HiveNova\Core\Uni3PilotLog;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/InMemoryUni3ClaimStore.php';

class Uni3HiveLinkServiceTest extends TestCase
{
	private InMemoryUni3ClaimStore $store;

	/** @var list<array{0: string, 1: int, 2: int, 3: int, 4: string}> */
	private array $events = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->store = new InMemoryUni3ClaimStore();
		$this->events = [];
		Uni3PilotLog::setRecorder(function (string $event, int $universe, int $seasonId, int $userId, string $detail): void {
			$this->events[] = [$event, $universe, $seasonId, $userId, $detail];
		});
	}

	protected function tearDown(): void
	{
		Uni3PilotLog::setRecorder(null);
		parent::tearDown();
	}

	public function testBindReservesOneHivePerSeasonSeat(): void
	{
		$service = new Uni3HiveLinkService($this->store);
		$ok = $service->bind(3, 4, 10, 'AliceAAA', Uni3ClaimGate::ORIGIN_EMAIL, 50);
		$this->assertTrue($ok['ok']);
		$this->assertSame('', $ok['reason']);
		$this->assertSame('aliceaaa', $this->store->findHiveLinkByUser(3, 4, 10)['hive_account']);
		$this->assertSame('email', $this->store->findHiveLinkByUser(3, 4, 10)['origin']);
		$this->assertSame([[3, 10, 'aliceaaa']], $this->store->hiveWrites);

		$again = $service->bind(3, 4, 10, 'aliceaaa', Uni3ClaimGate::ORIGIN_EMAIL, 60);
		$this->assertTrue($again['ok']);
		$this->assertSame('already', $again['reason']);

		$taken = $service->bind(3, 4, 11, 'aliceaaa', Uni3ClaimGate::ORIGIN_EMAIL, 70);
		$this->assertFalse($taken['ok']);
		$this->assertSame('hive_taken', $taken['reason']);
		$this->assertNull($this->store->findHiveLinkByUser(3, 4, 11));

		$otherSeason = $service->bind(3, 5, 11, 'carolccc', Uni3ClaimGate::ORIGIN_KEYCHAIN, 80);
		$this->assertTrue($otherSeason['ok']);
		$this->assertSame('keychain', $this->store->findHiveLinkByUser(3, 5, 11)['origin']);

		$events = array_column($this->events, 0);
		$this->assertContains(Uni3PilotLog::HIVE_LINK, $events);
		$this->assertContains(Uni3PilotLog::HIVE_LINK_REJECTED, $events);
	}

	public function testUniverseOwnerBlocksASecondAccount(): void
	{
		$this->store->owners['3:daveeeee'] = 8;
		$result = (new Uni3HiveLinkService($this->store))->bind(3, 4, 10, 'daveeeee', Uni3ClaimGate::ORIGIN_EMAIL, 1);
		$this->assertSame('hive_taken', $result['reason']);
		$this->assertNull($this->store->findHiveLinkByAccount(3, 4, 'daveeeee'));
	}

	public function testInvalidHiveIsRejectedWithoutASeat(): void
	{
		$result = (new Uni3HiveLinkService($this->store))->bind(3, 4, 10, 'no', Uni3ClaimGate::ORIGIN_EMAIL, 1);
		$this->assertSame('invalid', $result['reason']);
		$this->assertSame([], $this->events);
	}
}
