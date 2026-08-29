<?php

namespace HiveNova\Core;

use Hive\Hive;
use Throwable;

/**
 * Fail-open Hive-Engine token transfer helper. Never logs the active key.
 */
class HiveEngineTransfer
{
	/** PIZZA (and current Engine transfers) use 2 decimal places. */
	public const PRECISION = 2;
	public const MIN_AMOUNT = 0.01;
	public const CONTRACT_ID = 'ssc-mainnet-hive';

	/** @var callable|null fn(string $from, string $to, string $quantity, string $symbol, string $memo, string $wif): mixed */
	private static $broadcaster = null;

	public static function setBroadcaster(?callable $broadcaster): void
	{
		self::$broadcaster = $broadcaster;
	}

	/**
	 * @return array{ok: bool, trx_id: string}
	 */
	public function send(string $from, string $to, float $amount, string $symbol, string $memo, string $wif): array
	{
		try {
			$symbol = strtoupper(trim($symbol));
			if ($symbol === '' || !preg_match('/^[A-Z0-9]{1,10}$/', $symbol)) {
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

			$quantity = sprintf('%.' . self::PRECISION . 'f', $amount);
			$result = $this->broadcast($from, $to, $quantity, $symbol, $memo, $wif);
			$trxId = self::extractTrxId($result);
			if ($trxId === '') {
				return self::fail();
			}

			return ['ok' => true, 'trx_id' => $trxId];
		} catch (Throwable $e) {
			return self::fail();
		}
	}

	/**
	 * Hive Engine tokens.transfer body for custom_json.json (must be a JSON string on-chain).
	 */
	public static function transferJson(string $to, string $quantity, string $symbol, string $memo): string
	{
		$encoded = json_encode(
			[
				'contractName'    => 'tokens',
				'contractAction'  => 'transfer',
				'contractPayload' => [
					'symbol'   => $symbol,
					'to'       => $to,
					'quantity' => $quantity,
					'memo'     => $memo,
				],
			],
			JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		);
		// custom_json.json must itself be parseable JSON
		json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

		return $encoded;
	}

	/**
	 * Full custom_json op params as passed to Hive::broadcast (json is a string, not an object).
	 *
	 * @return array{0: list<string>, 1: list<string>, 2: string, 3: string}
	 */
	public static function customJsonParams(string $from, string $to, string $quantity, string $symbol, string $memo): array
	{
		return [
			[$from],
			[],
			self::CONTRACT_ID,
			self::transferJson($to, $quantity, $symbol, $memo),
		];
	}

	private function broadcast(string $from, string $to, string $quantity, string $symbol, string $memo, string $wif): mixed
	{
		if (self::$broadcaster !== null) {
			return (self::$broadcaster)($from, $to, $quantity, $symbol, $memo, $wif);
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
			$params = self::customJsonParams($from, $to, $quantity, $symbol, $memo);

			return $hive->broadcast($key, 'custom_json', $params);
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
