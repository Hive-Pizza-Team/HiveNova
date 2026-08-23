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
		HiveTransfer::setErrorLogger(null);
	}

	protected function tearDown(): void
	{
		HiveTransfer::setBroadcaster(null);
		HiveTransfer::setErrorLogger(null);
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

	public function testEncryptedMemoIsBroadcastWhenFlagSet(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'enc1'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'HIVE', '#7w8Nh1ibx', '5Ktestwif', true);

		$this->assertTrue($result['ok']);
		$this->assertSame('#7w8Nh1ibx', $this->calls[0][3]);
	}

	public function testEncryptedFlagRejectsPlaintextMemo(): void
	{
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});

		$result = (new HiveTransfer())->send('gameacct', 'playerone', 0.003, 'HIVE', 'hello', '5Ktestwif', true);
		$this->assertFalse($result['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testResourceCreditRpcErrorIsLoggedWithoutWif(): void
	{
		$logs = [];
		HiveTransfer::setErrorLogger(static function (string $line) use (&$logs): void {
			$logs[] = $line;
		});
		HiveTransfer::setBroadcaster(static function () {
			return [
				'code' => -32003,
				'message' => 'Assert Exception:account.has_mana',
				'data' => [
					'message' => 'Account: moon.notify has 5044277700 RC, needs 9000000000 RC. Please wait to transact, or power up HIVE.',
				],
			];
		});

		$result = (new HiveTransfer())->send('moon.notify', 'alkalineyo', 0.003, 'HIVE', 'memo', '5Ktestwifxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

		$this->assertFalse($result['ok']);
		$this->assertCount(1, $logs);
		$this->assertStringContainsString('resource credits exhausted', $logs[0]);
		$this->assertStringContainsString('moon.notify -> alkalineyo', $logs[0]);
		$this->assertStringContainsString('5044277700 RC', $logs[0]);
		$this->assertStringNotContainsString('5Ktestwif', $logs[0]);
	}

	public function testNonRcRpcErrorIsNotLogged(): void
	{
		$logs = [];
		HiveTransfer::setErrorLogger(static function (string $line) use (&$logs): void {
			$logs[] = $line;
		});
		HiveTransfer::setBroadcaster(static function () {
			return ['code' => -32000, 'message' => 'Internal Error'];
		});

		$result = (new HiveTransfer())->send('moon.notify', 'alkalineyo', 0.003, 'HIVE', 'memo', '5Ktestwif');

		$this->assertFalse($result['ok']);
		$this->assertSame([], $logs);
	}

	public function testResourceCreditExceptionIsLogged(): void
	{
		$logs = [];
		HiveTransfer::setErrorLogger(static function (string $line) use (&$logs): void {
			$logs[] = $line;
		});
		HiveTransfer::setBroadcaster(static function () {
			throw new RuntimeException('Account: moon.notify has 1 RC, needs 2 RC. Please wait to transact, or power up HIVE.');
		});

		$result = (new HiveTransfer())->send('moon.notify', 'hivetrending', 0.003, 'HIVE', 'memo', '5Ktestwif');

		$this->assertFalse($result['ok']);
		$this->assertCount(1, $logs);
		$this->assertStringContainsString('moon.notify -> hivetrending', $logs[0]);
	}
}
