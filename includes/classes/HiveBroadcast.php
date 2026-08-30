<?php

namespace HiveNova\Core;

use Hive\Hive;
use Hive\Helpers\PrivateKey;
use Hive\Helpers\Serializer;
use Hive\Helpers\Transaction;
use stdClass;
use Throwable;

/**
 * Build / sign / broadcast Hive operations with padded ECDSA signatures.
 *
 * Broadcasts try every configured RPC node: mahdiyari only retries on connection
 * failure, so a node that returns Missing Posting Authority would otherwise stick.
 */
final class HiveBroadcast
{
	/** @var callable|null fn(): object  object must provide privateKeyFrom, createTransaction, chainId, broadcastTransaction */
	private static $hiveFactory = null;

	/** @var callable|null fn(Transaction $trx): mixed */
	private static $transactionBroadcaster = null;

	/**
	 * Test seam: fn(string $node, Transaction $trx): mixed
	 *
	 * @var callable|null
	 */
	private static $nodeBroadcaster = null;

	public static function setHiveFactory(?callable $factory): void
	{
		self::$hiveFactory = $factory;
	}

	public static function setTransactionBroadcaster(?callable $broadcaster): void
	{
		self::$transactionBroadcaster = $broadcaster;
	}

	public static function setNodeBroadcaster(?callable $broadcaster): void
	{
		self::$nodeBroadcaster = $broadcaster;
	}

	/**
	 * @param list<mixed> $opParams Positional params matching mahdiyari OperationSerializers field order
	 */
	public static function operation(string $wif, string $opName, array $opParams): mixed
	{
		$hivePhp = __DIR__ . '/../../vendor/mahdiyari/hive-php/lib/Hive.php';
		if (is_readable($hivePhp)) {
			require_once $hivePhp;
		}

		$previousHandler = set_error_handler(static function () {
			return false;
		});
		$previousTz = date_default_timezone_get();
		try {
			$nodes = HiveUtil::getRpcNodes();
			$hive = self::$hiveFactory !== null
				? (self::$hiveFactory)()
				: new Hive([
					'rpcNodes' => $nodes !== [] ? $nodes : ['https://api.hive.blog'],
					'timeout'  => \HIVE_RPC_TIMEOUT,
				]);
			$key = $hive->privateKeyFrom($wif);
			$trx = self::createSignedTransaction($hive, $key, $opName, $opParams);

			if (self::$transactionBroadcaster !== null) {
				return (self::$transactionBroadcaster)($trx);
			}

			// Injected Hive (tests): single broadcast path.
			if (self::$hiveFactory !== null && self::$nodeBroadcaster === null) {
				return $hive->broadcastTransaction($trx);
			}

			return self::broadcastToNodes($trx, $nodes);
		} finally {
			if ($previousHandler !== null) {
				set_error_handler($previousHandler);
			} else {
				restore_error_handler();
			}
			date_default_timezone_set($previousTz);
		}
	}

	/**
	 * Sign once, then try each RPC node until one accepts the transaction.
	 *
	 * @param list<string> $nodes
	 * @param (callable(string, Transaction): mixed)|null $attempt
	 */
	public static function broadcastToNodes(Transaction $trx, array $nodes, ?callable $attempt = null): mixed
	{
		$last = [
			'code'    => -1,
			'message' => 'No Hive RPC nodes configured',
		];
		$attempt ??= self::$nodeBroadcaster;

		foreach ($nodes as $node) {
			$node = trim((string) $node);
			if ($node === '') {
				continue;
			}

			try {
				$result = $attempt !== null
					? $attempt($node, $trx)
					: self::broadcastToNode($node, $trx);
			} catch (Throwable $e) {
				$last = [
					'code'    => -1,
					'message' => $e->getMessage(),
				];
				continue;
			}

			if (self::isSuccessfulBroadcast($result)) {
				return $result;
			}

			$last = $result;
		}

		return $last;
	}

	public static function isSuccessfulBroadcast(mixed $result): bool
	{
		if (!is_array($result) || HiveUtil::isRpcError($result)) {
			return false;
		}

		foreach (['trx_id', 'id'] as $key) {
			if (!empty($result[$key]) && is_string($result[$key])) {
				return true;
			}
		}

		return false;
	}

	private static function broadcastToNode(string $node, Transaction $trx): mixed
	{
		$hive = new Hive([
			'rpcNodes' => [$node],
			'timeout'  => \HIVE_RPC_TIMEOUT,
		]);

		return $hive->broadcastTransaction($trx);
	}

	/**
	 * @param list<mixed> $opParams
	 * @return array{0: string, 1: stdClass}
	 */
	public static function buildOperation(string $opName, array $opParams): array
	{
		$serializer = new Serializer();
		$serializers = $serializer->OperationSerializers();
		if (!array_key_exists($opName, $serializers)) {
			throw new \InvalidArgumentException($opName . ' is not a valid operation.');
		}

		$opSerializer = $serializers[$opName][1];
		if (count($opSerializer) !== count($opParams)) {
			throw new \InvalidArgumentException(
				'Expected ' . count($opSerializer) . ' params but got ' . count($opParams)
			);
		}

		$operation = new stdClass();
		$i = 0;
		foreach ($opSerializer as $opParam) {
			$name = $opParam[0];
			$operation->$name = $opParams[$i];
			$i++;
		}

		return [$opName, $operation];
	}

	/**
	 * @param object{privateKeyFrom?: mixed, createTransaction: callable, chainId: string, broadcastTransaction?: callable} $hive
	 * @param list<mixed> $opParams
	 */
	public static function createSignedTransaction(object $hive, PrivateKey $key, string $opName, array $opParams): Transaction
	{
		$trx = $hive->createTransaction([self::buildOperation($opName, $opParams)]);
		self::signTransaction($hive, $trx, $key);

		return $trx;
	}

	/**
	 * @param object{chainId: string} $hive
	 */
	public static function signTransaction(object $hive, Transaction $trx, PrivateKey $key): void
	{
		$buffer = '';
		$serializer = new Serializer();
		$serializer->TransactionSerializer($buffer, $trx);
		$digest = hash('sha256', hex2bin($hive->chainId . $buffer));
		$trxId = substr(hash('sha256', hex2bin($buffer)), 0, 40);
		$trx->setTrxId($trxId);
		$trx->signatures = [HiveEcdsaSignature::signDigest($key->hexKey, $digest)];
	}
}
