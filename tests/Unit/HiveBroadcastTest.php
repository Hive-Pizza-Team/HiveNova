<?php

use HiveNova\Core\HiveBroadcast;
use HiveNova\Core\HiveEcdsaSignature;
use Hive\Helpers\PrivateKey;
use Hive\Helpers\Transaction;

use PHPUnit\Framework\TestCase;

class HiveBroadcastTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		HiveBroadcast::setHiveFactory(null);
		HiveBroadcast::setTransactionBroadcaster(null);
		require_once dirname(__DIR__, 2) . '/vendor/mahdiyari/hive-php/lib/Hive.php';
	}

	protected function tearDown(): void
	{
		HiveBroadcast::setHiveFactory(null);
		HiveBroadcast::setTransactionBroadcaster(null);
		parent::tearDown();
	}

	public function testBuildOperationMapsCommentFields(): void
	{
		[$name, $op] = HiveBroadcast::buildOperation('comment', [
			'',
			'moon',
			'moon.records',
			'hivenova-u3-season-1',
			'Title',
			'Body',
			'{"tags":["moon"]}',
		]);

		$this->assertSame('comment', $name);
		$this->assertSame('', $op->parent_author);
		$this->assertSame('moon', $op->parent_permlink);
		$this->assertSame('moon.records', $op->author);
		$this->assertSame('hivenova-u3-season-1', $op->permlink);
		$this->assertSame('Title', $op->title);
		$this->assertSame('Body', $op->body);
		$this->assertSame('{"tags":["moon"]}', $op->json_metadata);
	}

	public function testBuildOperationRejectsUnknownOp(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('not-an-op is not a valid operation.');
		HiveBroadcast::buildOperation('not-an-op', []);
	}

	public function testBuildOperationRejectsWrongParamCount(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Expected 7 params but got 1');
		HiveBroadcast::buildOperation('comment', ['only-one']);
	}

	public function testSignTransactionSetsPaddedSignatureAndTrxId(): void
	{
		$key = (new Hive\Hive(['rpcNodes' => ['https://api.hive.blog'], 'timeout' => 5]))
			->privateKeyFromLogin('hivenova-broadcast-test', 'unit-pass', 'posting');

		$hive = new class {
			public string $chainId = 'beeab0de00000000000000000000000000000000000000000000000000000000';
		};

		$trx = new Transaction();
		$trx->ref_block_num = 1;
		$trx->ref_block_prefix = 1;
		$trx->expiration = '2030-01-01T00:00:00';
		$trx->extensions = [];
		$trx->signatures = [];
		$trx->operations = [HiveBroadcast::buildOperation('vote', ['voter', 'author', 'permlink', 10000])];

		HiveBroadcast::signTransaction($hive, $trx, $key);

		$this->assertNotSame('', $trx->getTrxId());
		$this->assertCount(1, $trx->signatures);
		$this->assertSame(HiveEcdsaSignature::LENGTH, strlen($trx->signatures[0]));
		$this->assertTrue(HiveEcdsaSignature::isCanonical(substr($trx->signatures[0], 2)));
	}

	public function testOperationUsesInjectedHiveAndBroadcaster(): void
	{
		$key = (new Hive\Hive(['rpcNodes' => ['https://api.hive.blog'], 'timeout' => 5]))
			->privateKeyFromLogin('hivenova-broadcast-test', 'unit-pass', 'posting');
		$wif = $key->stringKey;
		$seen = [];

		HiveBroadcast::setHiveFactory(static function () use ($key) {
			return new class ($key) {
				public string $chainId = 'beeab0de00000000000000000000000000000000000000000000000000000000';

				public function __construct(private PrivateKey $key)
				{
				}

				public function privateKeyFrom(string $wif): PrivateKey
				{
					return $this->key;
				}

				public function createTransaction(array $operations): Transaction
				{
					$trx = new Transaction();
					$trx->ref_block_num = 42;
					$trx->ref_block_prefix = 99;
					$trx->expiration = '2030-01-01T00:00:00';
					$trx->extensions = [];
					$trx->signatures = [];
					$trx->operations = $operations;

					return $trx;
				}

				public function broadcastTransaction(Transaction $trx): array
				{
					return ['trx_id' => 'should-not-use-hive-broadcast'];
				}
			};
		});

		HiveBroadcast::setTransactionBroadcaster(static function (Transaction $trx) use (&$seen) {
			$seen[] = $trx;

			return ['trx_id' => $trx->getTrxId()];
		});

		$result = HiveBroadcast::operation($wif, 'vote', ['alice', 'bob', 'a-post', 10000]);

		$this->assertCount(1, $seen);
		$this->assertSame($seen[0]->getTrxId(), $result['trx_id']);
		$this->assertSame(HiveEcdsaSignature::LENGTH, strlen($seen[0]->signatures[0]));
		$this->assertSame('vote', $seen[0]->operations[0][0]);
		$this->assertSame('alice', $seen[0]->operations[0][1]->voter);
	}

	public function testCreateSignedTransactionSignsBuiltOp(): void
	{
		$key = (new Hive\Hive(['rpcNodes' => ['https://api.hive.blog'], 'timeout' => 5]))
			->privateKeyFromLogin('hivenova-broadcast-test', 'unit-pass', 'posting');

		$hive = new class ($key) {
			public string $chainId = 'beeab0de00000000000000000000000000000000000000000000000000000000';

			public function __construct(private PrivateKey $key)
			{
			}

			public function createTransaction(array $operations): Transaction
			{
				$trx = new Transaction();
				$trx->ref_block_num = 7;
				$trx->ref_block_prefix = 8;
				$trx->expiration = '2030-06-01T12:00:00';
				$trx->extensions = [];
				$trx->signatures = [];
				$trx->operations = $operations;

				return $trx;
			}
		};

		$trx = HiveBroadcast::createSignedTransaction($hive, $key, 'transfer', [
			'fromacct',
			'toacct',
			'1.000 HIVE',
			'memo',
		]);

		$this->assertSame('transfer', $trx->operations[0][0]);
		$this->assertSame('fromacct', $trx->operations[0][1]->from);
		$this->assertSame(HiveEcdsaSignature::LENGTH, strlen($trx->signatures[0]));
	}
}
