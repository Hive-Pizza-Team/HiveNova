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

	public function testGetTransactionUsesJsonRpc(): void
	{
		HiveEngineClient::setFetcher(function (string $url, ?string $body = null) {
			$this->assertSame(HiveEngineClient::RPC_URL, $url);
			$this->assertIsString($body);
			$this->assertStringContainsString('"getTransactionInfo"', $body);
			$this->assertStringContainsString('deadbeef', $body);
			return json_encode([
				'jsonrpc' => '2.0',
				'id' => 1,
				'result' => [
					'sender' => 'alice',
					'action' => 'transfer',
					'payload' => '{"symbol":"PIZZA","to":"bob","quantity":"1.000","memo":"x"}',
					'transactionId' => 'deadbeef',
				],
			]);
		});
		$row = (new HiveEngineClient())->getTransaction('deadbeef');
		$this->assertSame('alice', $row['sender']);
		$parsed = HiveEngineClient::parseTransfer($row);
		$this->assertSame('alice', $parsed['from']);
		$this->assertSame('bob', $parsed['to']);
		$this->assertSame('deadbeef', $parsed['trx_id']);
	}

	public function testGetTransactionNullResultIsMissing(): void
	{
		HiveEngineClient::setFetcher(static fn () => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => null]));
		$this->assertNull((new HiveEngineClient())->getTransaction('deadbeef'));
	}

	public function testParseTransferFromHistoryTokensTransfer(): void
	{
		$parsed = HiveEngineClient::parseTransfer([
			'operation' => 'tokens_transfer',
			'from' => 'playerone',
			'to' => 'season.wallet',
			'symbol' => 'PIZZA',
			'quantity' => '1.000',
			'memo' => 'hn-s2-1-10',
			'transactionId' => 'hist1',
			'timestamp' => 1700000010,
		]);
		$this->assertNotNull($parsed);
		$this->assertSame('playerone', $parsed['from']);
		$this->assertSame('season.wallet', $parsed['to']);
		$this->assertSame('hist1', $parsed['trx_id']);
	}

	public function testParseTransferFromStringPayload(): void
	{
		$parsed = HiveEngineClient::parseTransfer([
			'action' => 'transfer',
			'sender' => 'xanuri',
			'payload' => '{"symbol":"PIZZA","to":"moon.deposit","quantity":"2.000","memo":"hn-s2-1-10"}',
			'transactionId' => '0e7b5e0b95c137e8b3887f7d8da3a486cdb001e0',
			'timestamp' => '2026-08-22T23:18:09',
		]);
		$this->assertNotNull($parsed);
		$this->assertSame('xanuri', $parsed['from']);
		$this->assertSame('moon.deposit', $parsed['to']);
		$this->assertSame(2.0, $parsed['quantity']);
		$this->assertSame('hn-s2-1-10', $parsed['memo']);
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

	public function testAccountHistoryPaginatesUntilShortPage(): void
	{
		$calls = [];
		HiveEngineClient::setFetcher(function (string $url) use (&$calls) {
			$calls[] = $url;
			if (str_contains($url, 'offset=0')) {
				return json_encode([
					['from' => 'alice', 'quantity' => 1],
					['from' => 'bob', 'quantity' => 1],
				]);
			}
			if (str_contains($url, 'offset=2')) {
				return json_encode([
					['from' => 'carol', 'quantity' => 1],
				]);
			}
			return json_encode([]);
		});
		$rows = (new HiveEngineClient())->accountHistory('season.wallet', 'PIZZA', 2);
		$this->assertCount(3, $rows);
		$this->assertCount(2, $calls);
		$this->assertStringContainsString('offset=0', $calls[0]);
		$this->assertStringContainsString('offset=2', $calls[1]);
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
