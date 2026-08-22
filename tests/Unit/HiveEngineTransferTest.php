<?php

use HiveNova\Core\HiveEngineTransfer;

use PHPUnit\Framework\TestCase;

class HiveEngineTransferTest extends TestCase
{
	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> */
	private array $calls = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->calls = [];
		HiveEngineTransfer::setBroadcaster(null);
	}

	protected function tearDown(): void
	{
		HiveEngineTransfer::setBroadcaster(null);
		parent::tearDown();
	}

	public function testSendFormatsQuantityAndSymbol(): void
	{
		HiveEngineTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'eng1'];
		});

		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 1.2, 'pizza', 'hn-s2-1-10', '5Ktestwif');

		$this->assertTrue($result['ok']);
		$this->assertSame('eng1', $result['trx_id']);
		$this->assertSame(['gameacct', 'playerone', '1.200', 'PIZZA', 'hn-s2-1-10', '5Ktestwif'], $this->calls[0]);
	}

	public function testAmountBelowFloorDoesNotBroadcast(): void
	{
		HiveEngineTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 0.0004, 'PIZZA', 'm', '5K');
		$this->assertFalse($result['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testInvalidAccountOrEmptyKeyDoesNotBroadcast(): void
	{
		HiveEngineTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$helper = new HiveEngineTransfer();
		$this->assertFalse($helper->send('BAD', 'playerone', 1, 'PIZZA', 'm', '5K')['ok']);
		$this->assertFalse($helper->send('gameacct', 'playerone', 1, 'PIZZA', 'm', '')['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testEncryptedMemoIsRejected(): void
	{
		HiveEngineTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 1, 'PIZZA', '#secret', '5K');
		$this->assertFalse($result['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testBroadcasterThrowReturnsFailure(): void
	{
		HiveEngineTransfer::setBroadcaster(static function () {
			throw new RuntimeException('boom');
		});
		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 1, 'PIZZA', 'm', '5Ktest');
		$this->assertFalse($result['ok']);
	}

	public function testMissingTrxIdIsFailure(): void
	{
		HiveEngineTransfer::setBroadcaster(static fn () => ['error' => 'nope']);
		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 1, 'PIZZA', 'm', '5Ktest');
		$this->assertFalse($result['ok']);
	}

	public function testInvalidSymbolDoesNotBroadcast(): void
	{
		HiveEngineTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 1, 'P!ZZA', 'm', '5Ktest');
		$this->assertFalse($result['ok']);
		$this->assertSame([], $this->calls);
	}
}
