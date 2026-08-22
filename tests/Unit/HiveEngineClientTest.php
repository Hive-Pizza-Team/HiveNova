<?php

use HiveNova\Core\HiveEngineClient;

use PHPUnit\Framework\TestCase;

class HiveEngineClientTest extends TestCase
{
	protected function tearDown(): void
	{
		HiveEngineClient::setFetcher(null);
		parent::tearDown();
	}

	public function testParseTransferFromPayload(): void
	{
		$parsed = HiveEngineClient::parseTransfer([
			'action' => 'transfer',
			'sender' => 'PlayerOne',
			'payload' => [
				'to' => 'season.wallet',
				'symbol' => 'PIZZA',
				'quantity' => '1.500',
				'memo' => 'hn-s2-1-10',
			],
			'transactionId' => 'abc',
			'timestamp' => '2026-08-01T00:00:00Z',
		]);
		$this->assertNotNull($parsed);
		$this->assertSame('playerone', $parsed['from']);
		$this->assertSame('season.wallet', $parsed['to']);
		$this->assertSame(1.5, $parsed['quantity']);
		$this->assertSame('PIZZA', $parsed['symbol']);
		$this->assertSame('abc', $parsed['trx_id']);
		$this->assertGreaterThan(0, $parsed['timestamp']);
	}

	public function testParseTransferRejectsNonTransfer(): void
	{
		$this->assertNull(HiveEngineClient::parseTransfer([
			'action' => 'stake',
			'from' => 'alice',
			'to' => 'bob',
			'symbol' => 'PIZZA',
			'quantity' => 1,
		]));
	}

	public function testGetTransactionUsesFetcher(): void
	{
		HiveEngineClient::setFetcher(function (string $url) {
			$this->assertStringContainsString('txid=deadbeef', $url);
			return json_encode(['from' => 'alice', 'to' => 'bob', 'symbol' => 'PIZZA', 'quantity' => 1, 'memo' => 'x', 'transactionId' => 'deadbeef']);
		});
		$row = (new HiveEngineClient())->getTransaction('deadbeef');
		$this->assertSame('alice', $row['from']);
	}

	public function testGetTransactionRejectsEmptyId(): void
	{
		HiveEngineClient::setFetcher(static fn () => '{}');
		$this->assertNull((new HiveEngineClient())->getTransaction('!!!'));
	}

	public function testAccountHistoryInvalidAccountIsEmpty(): void
	{
		$this->assertSame([], (new HiveEngineClient())->accountHistory('BAD'));
	}

	public function testAccountHistoryDecodesList(): void
	{
		HiveEngineClient::setFetcher(static fn () => json_encode([
			['from' => 'alice', 'to' => 'season.wallet', 'symbol' => 'PIZZA', 'quantity' => 1, 'memo' => 'hn-s1-1-1'],
		]));
		$rows = (new HiveEngineClient())->accountHistory('season.wallet');
		$this->assertCount(1, $rows);
	}

	public function testMillisTimestampIsConverted(): void
	{
		$parsed = HiveEngineClient::parseTransfer([
			'from' => 'alice',
			'to' => 'bob',
			'symbol' => 'PIZZA',
			'quantity' => 1,
			'memo' => '',
			'timestamp' => 1700000000000,
		]);
		$this->assertSame(1700000000, $parsed['timestamp']);
	}
}
