<?php

namespace HiveNova\Core;

use Hive\Hive;
use Throwable;

/**
 * Fail-open Hive transfer+memo helper. Never logs the active key.
 */
class HiveTransfer
{
	public const MIN_AMOUNT = 0.003;

	/** @var callable|null fn(string $from, string $to, string $amountAsset, string $memo, string $wif): mixed */
	private static $broadcaster = null;

	public static function setBroadcaster(?callable $broadcaster): void
	{
		self::$broadcaster = $broadcaster;
	}

	/**
	 * @return array{ok: bool, trx_id: string}
	 */
	public function send(string $from, string $to, float $amount, string $asset, string $memo, string $wif): array
	{
		try {
			$asset = strtoupper(trim($asset));
			if ($asset !== 'HIVE' && $asset !== 'HBD') {
				return self::fail();
			}
			if ($amount < self::MIN_AMOUNT) {
				return self::fail();
			}
			if ($wif === '' || !HiveUtil::isAccountValid($from) || !HiveUtil::isAccountValid($to)) {
				return self::fail();
			}
			if (str_starts_with($memo, '#')) {
				return self::fail();
			}

			$amountAsset = sprintf('%.3f %s', $amount, $asset);
			$result = $this->broadcast($from, $to, $amountAsset, $memo, $wif);
			$trxId = self::extractTrxId($result);
			if ($trxId === '') {
				return self::fail();
			}

			return ['ok' => true, 'trx_id' => $trxId];
		} catch (Throwable $e) {
			return self::fail();
		}
	}

	private function broadcast(string $from, string $to, string $amountAsset, string $memo, string $wif): mixed
	{
		if (self::$broadcaster !== null) {
			return (self::$broadcaster)($from, $to, $amountAsset, $memo, $wif);
		}

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

			return $hive->broadcast($key, 'transfer', [$from, $to, $amountAsset, $memo]);
		} finally {
			if ($previousHandler !== null) {
				set_error_handler($previousHandler);
			} else {
				restore_error_handler();
			}
			date_default_timezone_set($previousTz);
		}
	}

	private static function extractTrxId(mixed $result): string
	{
		if (!is_array($result) || HiveUtil::isRpcError($result)) {
			return '';
		}

		foreach (['trx_id', 'id'] as $key) {
			if (!empty($result[$key]) && is_string($result[$key])) {
				return $result[$key];
			}
		}

		return '';
	}

	/**
	 * @return array{ok: bool, trx_id: string}
	 */
	private static function fail(): array
	{
		return ['ok' => false, 'trx_id' => ''];
	}
}
