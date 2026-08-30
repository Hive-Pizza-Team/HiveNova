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
		$this->assertSame(['gameacct', 'playerone', '1.20', 'PIZZA', 'hn-s2-1-10', '5Ktestwif'], $this->calls[0]);
	}

	public function testAmountBelowFloorDoesNotBroadcast(): void
	{
		HiveEngineTransfer::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 0.004, 'PIZZA', 'm', '5K');
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

	public function testTransferJsonIsValidJsonString(): void
	{
		$json = HiveEngineTransfer::transferJson('alice', '45.00', 'PIZZA', 'LocalMoon season 1 prize');
		$this->assertJson($json);
		$decoded = json_decode($json, true);
		$this->assertSame(
			[
				'contractName' => 'tokens',
				'contractAction' => 'transfer',
				'contractPayload' => [
					'symbol' => 'PIZZA',
					'to' => 'alice',
					'quantity' => '45.00',
					'memo' => 'LocalMoon season 1 prize',
				],
			],
			$decoded
		);

		$params = HiveEngineTransfer::customJsonParams('season.wallet', 'alice', '45.00', 'PIZZA', 'LocalMoon season 1 prize');
		$op = [
			'required_auths' => $params[0],
			'required_posting_auths' => $params[1],
			'id' => $params[2],
			'json' => $params[3],
		];
		$outer = json_encode(['custom_json', $op], JSON_UNESCAPED_SLASHES);
		$this->assertIsString($outer);
		$this->assertJson($outer);
		$roundTrip = json_decode($outer, true);
		$this->assertIsString($roundTrip[1]['json']);
		$this->assertJson($roundTrip[1]['json']);
	}

	public function testDefaultBroadcastPathUsesHiveBroadcast(): void
	{
		require_once dirname(__DIR__, 2) . '/vendor/mahdiyari/hive-php/lib/Hive.php';
		$key = new \Hive\Helpers\PrivateKey(hash('sha256', 'engine-broadcast|active'), true);

		HiveEngineTransfer::setBroadcaster(null);
		HiveNova\Core\HiveBroadcast::setHiveFactory(static function () use ($key) {
			return new class ($key) {
				public string $chainId = 'beeab0de00000000000000000000000000000000000000000000000000000000';

				public function __construct(private \Hive\Helpers\PrivateKey $key)
				{
				}

				public function privateKeyFrom(string $wif): \Hive\Helpers\PrivateKey
				{
					return $this->key;
				}

				public function createTransaction(array $operations): \Hive\Helpers\Transaction
				{
					$trx = new \Hive\Helpers\Transaction();
					$trx->ref_block_num = 1;
					$trx->ref_block_prefix = 1;
					$trx->expiration = '2030-01-01T00:00:00';
					$trx->extensions = [];
					$trx->signatures = [];
					$trx->operations = $operations;

					return $trx;
				}

				public function broadcastTransaction(\Hive\Helpers\Transaction $trx): array
				{
					return ['trx_id' => 'engine-live-path'];
				}
			};
		});

		try {
			$result = (new HiveEngineTransfer())->send('gameacct', 'playerone', 1, 'PIZZA', 'm', $key->stringKey);
			$this->assertTrue($result['ok']);
			$this->assertSame('engine-live-path', $result['trx_id']);
		} finally {
			HiveNova\Core\HiveBroadcast::setHiveFactory(null);
			HiveNova\Core\HiveBroadcast::setTransactionBroadcaster(null);
		}
	}
}
