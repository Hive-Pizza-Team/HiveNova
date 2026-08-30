<?php

namespace HiveNova\Core;

use Hive\Hive;
use Hive\Helpers\PrivateKey;
use Hive\Helpers\Serializer;
use Hive\Helpers\Transaction;
use stdClass;

/**
 * Build / sign / broadcast Hive operations with padded ECDSA signatures.
 */
final class HiveBroadcast
{
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
			$hive = new Hive([
				'rpcNodes' => HiveUtil::rpcNodesToTry(1),
				'timeout'  => \HIVE_RPC_TIMEOUT,
			]);
			$key = $hive->privateKeyFrom($wif);
			$trx = self::createSignedTransaction($hive, $key, $opName, $opParams);

			return $hive->broadcastTransaction($trx);
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
	 * @param list<mixed> $opParams
	 */
	private static function createSignedTransaction(Hive $hive, PrivateKey $key, string $opName, array $opParams): Transaction
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

		$trx = $hive->createTransaction([[$opName, $operation]]);
		self::signTransaction($hive, $trx, $key);

		return $trx;
	}

	private static function signTransaction(Hive $hive, Transaction $trx, PrivateKey $key): void
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
