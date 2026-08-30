<?php

use HiveNova\Core\HiveBroadcast;
use HiveNova\Core\HiveEcdsaSignature;
use HiveNova\Core\HiveUtil;
use Hive\Helpers\PrivateKey;
use Hive\Helpers\Transaction;

use PHPUnit\Framework\TestCase;

class HiveBroadcastTest extends TestCase
{
	private PrivateKey $key;

	protected function setUp(): void
	{
		parent::setUp();
		HiveBroadcast::setHiveFactory(null);
		HiveBroadcast::setTransactionBroadcaster(null);
		HiveBroadcast::setNodeBroadcaster(null);
		HiveBroadcast::setRpcNodes(null);
		HiveBroadcast::setHiveNodeFactory(null);
		require_once dirname(__DIR__, 2) . '/vendor/mahdiyari/hive-php/lib/Hive.php';
		if (!defined('HIVE_RPC_TIMEOUT')) {
			define('HIVE_RPC_TIMEOUT', 5);
		}
		if (!defined('HIVE_RPC_NODES')) {
			define('HIVE_RPC_NODES', [
				'https://api.hive.blog',
				'https://api.deathwing.me',
				'https://rpc.mahdiyari.info',
				'https://hapi.ecency.com',
			]);
		}
		// Avoid Hive::__construct — it installs a process-wide throwing error handler.
		$this->key = new PrivateKey(hash('sha256', 'hivenova-broadcast-test|unit-pass|posting'), true);
	}

	protected function tearDown(): void
	{
		HiveBroadcast::setHiveFactory(null);
		HiveBroadcast::setTransactionBroadcaster(null);
		HiveBroadcast::setNodeBroadcaster(null);
		HiveBroadcast::setRpcNodes(null);
		HiveBroadcast::setHiveNodeFactory(null);
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

		HiveBroadcast::signTransaction($hive, $trx, $this->key);

		$this->assertNotSame('', $trx->getTrxId());
		$this->assertCount(1, $trx->signatures);
		$this->assertSame(HiveEcdsaSignature::LENGTH, strlen($trx->signatures[0]));
		$this->assertTrue(HiveEcdsaSignature::isCanonical(substr($trx->signatures[0], 2)));
	}

	public function testOperationUsesInjectedHiveAndBroadcaster(): void
	{
		$key = $this->key;
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
		$hive = new class ($this->key) {
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

		$trx = HiveBroadcast::createSignedTransaction($hive, $this->key, 'transfer', [
			'fromacct',
			'toacct',
			'1.000 HIVE',
			'memo',
		]);

		$this->assertSame('transfer', $trx->operations[0][0]);
		$this->assertSame('fromacct', $trx->operations[0][1]->from);
		$this->assertSame(HiveEcdsaSignature::LENGTH, strlen($trx->signatures[0]));
	}

	public function testOperationFallsBackToHiveBroadcastTransaction(): void
	{
		$key = $this->key;
		HiveBroadcast::setHiveFactory(static function () use ($key) {
			return new class ($key) {
				public string $chainId = 'beeab0de00000000000000000000000000000000000000000000000000000000';
				public bool $usedHiveBroadcast = false;

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
					$trx->ref_block_num = 1;
					$trx->ref_block_prefix = 2;
					$trx->expiration = '2030-01-01T00:00:00';
					$trx->extensions = [];
					$trx->signatures = [];
					$trx->operations = $operations;

					return $trx;
				}

				public function broadcastTransaction(Transaction $trx): array
				{
					$this->usedHiveBroadcast = true;

					return ['trx_id' => 'via-hive-' . $trx->getTrxId()];
				}
			};
		});

		$result = HiveBroadcast::operation($key->stringKey, 'vote', ['alice', 'bob', 'a-post', 10000]);
		$this->assertStringStartsWith('via-hive-', $result['trx_id']);
	}

	public function testIsSuccessfulBroadcastRequiresTrxId(): void
	{
		$this->assertTrue(HiveBroadcast::isSuccessfulBroadcast(['trx_id' => 'abc']));
		$this->assertTrue(HiveBroadcast::isSuccessfulBroadcast(['id' => 'abc']));
		$this->assertFalse(HiveBroadcast::isSuccessfulBroadcast(['code' => 1, 'message' => 'Missing Posting Authority']));
		$this->assertFalse(HiveBroadcast::isSuccessfulBroadcast([]));
	}

	public function testBroadcastToNodesSkipsAuthorityErrorsUntilSuccess(): void
	{
		$trx = new Transaction();
		$trx->ref_block_num = 1;
		$trx->ref_block_prefix = 1;
		$trx->expiration = '2030-01-01T00:00:00';
		$trx->extensions = [];
		$trx->signatures = [str_repeat('a', 130)];
		$trx->operations = [];
		$trx->setTrxId('deadbeef');

		$tried = [];
		$result = HiveBroadcast::broadcastToNodes(
			$trx,
			['https://bad.example', 'https://api.hive.blog', 'https://unused.example'],
			static function (string $node, Transaction $signed) use (&$tried) {
				$tried[] = $node;
				if ($node === 'https://bad.example') {
					return [
						'code' => 403010000,
						'message' => 'missing required posting authority',
					];
				}
				if ($node === 'https://api.hive.blog') {
					return ['trx_id' => $signed->getTrxId()];
				}

				return ['code' => -1, 'message' => 'should not reach'];
			}
		);

		$this->assertSame(['https://bad.example', 'https://api.hive.blog'], $tried);
		$this->assertSame('deadbeef', $result['trx_id']);
	}

	public function testBroadcastToNodesReturnsLastErrorWhenAllFail(): void
	{
		$trx = new Transaction();
		$trx->ref_block_num = 1;
		$trx->ref_block_prefix = 1;
		$trx->expiration = '2030-01-01T00:00:00';
		$trx->extensions = [];
		$trx->signatures = [];
		$trx->operations = [];
		$trx->setTrxId('x');

		$result = HiveBroadcast::broadcastToNodes(
			$trx,
			['https://a.example', 'https://b.example'],
			static function (string $node): array {
				return ['code' => 1, 'message' => 'fail-' . $node];
			}
		);

		$this->assertTrue(HiveUtil::isRpcError($result));
		$this->assertSame('fail-https://b.example', $result['message']);
	}

	public function testOperationFailsoverAcrossNodesViaNodeBroadcaster(): void
	{
		$key = $this->key;
		$tried = [];

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
					$trx->ref_block_num = 1;
					$trx->ref_block_prefix = 2;
					$trx->expiration = '2030-01-01T00:00:00';
					$trx->extensions = [];
					$trx->signatures = [];
					$trx->operations = $operations;

					return $trx;
				}

				public function broadcastTransaction(Transaction $trx): array
				{
					return ['trx_id' => 'should-not-use-single-hive'];
				}
			};
		});

		HiveBroadcast::setNodeBroadcaster(static function (string $node, Transaction $trx) use (&$tried) {
			$tried[] = $node;
			if (count($tried) === 1) {
				return ['code' => 1, 'message' => 'Missing Posting Authority'];
			}

			return ['trx_id' => 'failover-' . $trx->getTrxId()];
		});

		$result = HiveBroadcast::operation($key->stringKey, 'vote', ['alice', 'bob', 'a-post', 10000]);

		$this->assertGreaterThanOrEqual(2, count($tried));
		$this->assertStringStartsWith('failover-', $result['trx_id']);
		$this->assertSame(HiveUtil::getRpcNodes()[0], $tried[0]);
	}

	public function testResolveRpcNodesFallsBackWhenEmpty(): void
	{
		$this->assertSame(['https://api.hive.blog'], HiveBroadcast::resolveRpcNodes([]));
		$this->assertSame(['https://api.hive.blog'], HiveBroadcast::resolveRpcNodes(['', '  ', "\t"]));
		$this->assertSame(
			['https://a.example', 'https://b.example'],
			HiveBroadcast::resolveRpcNodes(['', 'https://a.example', '  ', 'https://b.example'])
		);

		HiveBroadcast::setRpcNodes(['', 'https://override.example']);
		$this->assertSame(['https://override.example'], HiveBroadcast::resolveRpcNodes());
		HiveBroadcast::setRpcNodes([]);
		$this->assertSame(['https://api.hive.blog'], HiveBroadcast::resolveRpcNodes());
	}

	public function testBroadcastToNodesSkipsBlankNodesAndCatchesThrownAttempts(): void
	{
		$trx = new Transaction();
		$trx->ref_block_num = 1;
		$trx->ref_block_prefix = 1;
		$trx->expiration = '2030-01-01T00:00:00';
		$trx->extensions = [];
		$trx->signatures = [];
		$trx->operations = [];
		$trx->setTrxId('blank-skip');

		$tried = [];
		$result = HiveBroadcast::broadcastToNodes(
			$trx,
			['', '  ', 'https://throw.example', 'https://ok.example'],
			static function (string $node, Transaction $signed) use (&$tried) {
				$tried[] = $node;
				if ($node === 'https://throw.example') {
					throw new RuntimeException('node blew up');
				}

				return ['trx_id' => $signed->getTrxId()];
			}
		);

		$this->assertSame(['https://throw.example', 'https://ok.example'], $tried);
		$this->assertSame('blank-skip', $result['trx_id']);
	}

	public function testBroadcastToNodesReturnsLastThrownMessageWhenAllThrow(): void
	{
		$trx = new Transaction();
		$trx->ref_block_num = 1;
		$trx->ref_block_prefix = 1;
		$trx->expiration = '2030-01-01T00:00:00';
		$trx->extensions = [];
		$trx->signatures = [];
		$trx->operations = [];
		$trx->setTrxId('all-throw');

		$result = HiveBroadcast::broadcastToNodes(
			$trx,
			['https://a.example', 'https://b.example'],
			static function (string $node): array {
				throw new RuntimeException('boom-' . $node);
			}
		);

		$this->assertTrue(HiveUtil::isRpcError($result));
		$this->assertSame(-1, $result['code']);
		$this->assertSame('boom-https://b.example', $result['message']);
	}

	public function testBroadcastToNodesUsesHiveNodeFactory(): void
	{
		$trx = new Transaction();
		$trx->ref_block_num = 1;
		$trx->ref_block_prefix = 1;
		$trx->expiration = '2030-01-01T00:00:00';
		$trx->extensions = [];
		$trx->signatures = [];
		$trx->operations = [];
		$trx->setTrxId('via-factory');

		$seenNodes = [];
		HiveBroadcast::setHiveNodeFactory(static function (string $node) use (&$seenNodes) {
			$seenNodes[] = $node;

			return new class ($node) {
				public function __construct(private string $node)
				{
				}

				public function broadcastTransaction(Transaction $trx): array
				{
					return ['trx_id' => 'node-' . $this->node . '-' . $trx->getTrxId()];
				}
			};
		});

		$result = HiveBroadcast::broadcastToNodes($trx, ['https://factory.example']);

		$this->assertSame(['https://factory.example'], $seenNodes);
		$this->assertSame('node-https://factory.example-via-factory', $result['trx_id']);
	}

	public function testOperationUsesResolvedRpcNodesOverride(): void
	{
		$key = $this->key;
		$tried = [];

		HiveBroadcast::setRpcNodes(['', 'https://override-a.example', 'https://override-b.example']);

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
					$trx->ref_block_num = 1;
					$trx->ref_block_prefix = 2;
					$trx->expiration = '2030-01-01T00:00:00';
					$trx->extensions = [];
					$trx->signatures = [];
					$trx->operations = $operations;

					return $trx;
				}

				public function broadcastTransaction(Transaction $trx): array
				{
					return ['trx_id' => 'should-not-use-single-hive'];
				}
			};
		});

		HiveBroadcast::setNodeBroadcaster(static function (string $node, Transaction $trx) use (&$tried) {
			$tried[] = $node;
			if ($node === 'https://override-a.example') {
				return ['code' => 1, 'message' => 'Missing Posting Authority'];
			}

			return ['trx_id' => 'override-' . $trx->getTrxId()];
		});

		$result = HiveBroadcast::operation($key->stringKey, 'vote', ['alice', 'bob', 'a-post', 10000]);

		$this->assertSame(['https://override-a.example'], $tried);
		$this->assertStringStartsWith('override-', $result['trx_id']);
	}
}
