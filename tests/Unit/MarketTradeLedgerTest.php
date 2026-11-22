<?php

declare(strict_types=1);

use HiveNova\Core\MarketPlaceService;
use HiveNova\Core\MarketTradeLedger;
use HiveNova\Core\PushingAccusationQuery;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class MarketTradeLedgerTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	/** @return array<int, array<string, mixed>> */
	private function pricelist(): array
	{
		return [203 => ['cost' => [901 => 6000, 902 => 6000, 903 => 0]]];
	}

	private function trade(int $seller, int $buyer, float $crystal, float $metalAsked, float $sellerPoints = 100, float $buyerPoints = 9000): array
	{
		return [
			'seller_id' => $seller, 'buyer_id' => $buyer,
			'seller_name' => 'S' . $seller, 'buyer_name' => 'B' . $buyer,
			'seller_points' => $sellerPoints, 'buyer_points' => $buyerPoints,
			'transaction_type' => 0, 'ex_resource_type' => 1, 'ex_resource_amount' => $metalAsked,
			'fleet_array' => '203,5', 'fleet_resource_metal' => 0,
			'fleet_resource_crystal' => $crystal, 'fleet_resource_deuterium' => 0,
		];
	}

	public function testGoodsValueUsesCargoForResourcesAndShipsForFleets(): void
	{
		$row = $this->trade(1, 2, 100000, 1);
		$this->assertSame(50000.0, MarketTradeLedger::goodsValue($row, $this->pricelist(), MarketPlaceService::REFERENCE_RATIO));
		$row['transaction_type'] = 1;
		$this->assertSame(5 * 4500.0, MarketTradeLedger::goodsValue($row, $this->pricelist(), MarketPlaceService::REFERENCE_RATIO));
	}

	public function testSoldToBuyerSumsPerSeller(): void
	{
		$this->fake->soldToBuyerRows = [
			['seller_id' => 7] + $this->trade(7, 2, 100000, 1),
			['seller_id' => 7] + $this->trade(7, 2, 20000, 1),
			['seller_id' => 8] + $this->trade(8, 2, 2000, 1),
		];
		$this->assertSame(
			[7 => 60000.0, 8 => 1000.0],
			MarketTradeLedger::soldToBuyer($this->fake, 2, $this->pricelist(), MarketPlaceService::REFERENCE_RATIO)
		);
	}

	public function testPushesFromRowsKeepsOnlyLopsidedTradesFromTheWeakerSide(): void
	{
		$rows = [
			$this->trade(1, 2, 100000, 100000),              // 50k units for 25k: push 1 -> 2
			$this->trade(1, 2, 50000, 100000),               // fair
			$this->trade(1, 2, 100000, 100000, 9000, 100),   // seller stronger: not a push
		];
		$this->assertSame(
			[['source' => 1, 'dest' => 2, 'source_name' => 'S1', 'dest_name' => 'B2']],
			MarketTradeLedger::pushesFromRows($rows, $this->pricelist(), MarketPlaceService::REFERENCE_RATIO)
		);
	}

	public function testAccusationCountsLopsidedTradesWithoutAnyTransports(): void
	{
		$this->fake->marketTrades = array_fill(0, 5, $this->trade(1, 2, 100000, 100000));
		$this->assertSame([], PushingAccusationQuery::accusedReceiverIds(1, TIMESTAMP));

		$this->fake->marketTrades[] = $this->trade(1, 2, 100000, 100000);
		$this->assertSame([2], PushingAccusationQuery::accusedReceiverIds(1, TIMESTAMP));
		$this->assertTrue(PushingAccusationQuery::isAccusedReceiver(2, 1, TIMESTAMP));
		$this->assertFalse(PushingAccusationQuery::isAccusedReceiver(1, 1, TIMESTAMP));
	}

	public function testAccusationMergesTransportsAndTradesForTheSamePair(): void
	{
		$this->fake->accusedDestIds = [55];
		$pairs = PushingAccusationQuery::pushPairs(1, TIMESTAMP);
		$this->assertCount(1, $pairs);
		$this->assertSame(55, $pairs[0]['dest_id']);
		$this->assertSame(6, $pairs[0]['transports']);
		$this->assertSame(0, $pairs[0]['trades']);
	}
}
