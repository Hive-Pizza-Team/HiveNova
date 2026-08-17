<?php

use HiveNova\Core\HiveTransfer;

use PHPUnit\Framework\TestCase;

class HiveTransferTest extends TestCase
{
	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
	private array $calls = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->calls = [];
		HiveTransfer::setBroadcaster(null);
	}

	protected function tearDown(): void
	{
		HiveTransfer::setBroadcaster(null);
		parent::tearDown();
	}

	public function testSendInvokesBroadcasterWithFormattedHiveAmount(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'abc123'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'HIVE', 'You are (I)', '5Ktestwif');

		$this->assertTrue($result['ok']);
		$this->assertSame('abc123', $result['trx_id']);
		$this->assertCount(1, $this->calls);
		$this->assertSame(['gameacct', 'playerone', '0.003 HIVE', 'You are (I)', '5Ktestwif'], $this->calls[0]);
	}

	public function testAmountBelowFloorDoesNotBroadcast(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'abc123'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.001, 'HIVE', 'memo', '5Ktestwif');

		$this->assertFalse($result['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testHbdPathFormatsAssetString(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'hbd1'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'hbd', 'memo', '5Ktestwif');

		$this->assertTrue($result['ok']);
		$this->assertSame('0.003 HBD', $this->calls[0][2]);
	}

	public function testInvalidAccountOrEmptyKeyDoesNotBroadcast(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});

		$helper = new HiveTransfer();
		$this->assertFalse($helper->send('BAD', 'playerone', 0.003, 'HIVE', 'm', '5K')['ok']);
		$this->assertFalse($helper->send('gameacct', 'playerone', 0.003, 'HIVE', 'm', '')['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testBroadcasterThrowReturnsFailure(): void
	{
		HiveTransfer::setBroadcaster(static function () {
			throw new RuntimeException('node down');
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'HIVE', 'memo', '5Ktestwif');
		$this->assertFalse($result['ok']);
	}

	public function testRpcErrorArrayIsFailure(): void
	{
		HiveTransfer::setBroadcaster(static function () {
			return ['code' => -32000, 'message' => 'Internal Error'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'HIVE', 'memo', '5Ktestwif');
		$this->assertFalse($result['ok']);
		$this->assertSame('', $result['trx_id']);
	}

	public function testHashPrefixedMemoIsRejected(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'HIVE', '#secret', '5Ktestwif');
		$this->assertFalse($result['ok']);
		$this->assertSame([], $this->calls);
	}
}
