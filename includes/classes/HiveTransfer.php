<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Fail-open Hive transfer+memo helper. Never logs the active key.
 */
class HiveTransfer
{
	public const MIN_AMOUNT = 0.003;

	/** @var callable|null fn(string $from, string $to, string $amountAsset, string $memo, string $wif): mixed */
	private static $broadcaster = null;

	/** @var callable|null fn(string $message): void */
	private static $errorLogger = null;

	public static function setBroadcaster(?callable $broadcaster): void
	{
		self::$broadcaster = $broadcaster;
	}

	public static function setErrorLogger(?callable $logger): void
	{
		self::$errorLogger = $logger;
	}

	/**
	 * @return array{ok: bool, trx_id: string}
	 */
	public function send(string $from, string $to, float $amount, string $asset, string $memo, string $wif, bool $encrypted = false): array
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
			if ($encrypted) {
				if (!str_starts_with($memo, '#') || strlen($memo) < 2 || strlen($memo) > HiveMemo::MAX_MEMO_BYTES) {
					return self::fail();
				}
			} elseif (str_starts_with($memo, '#')) {
				return self::fail();
			}

			$amountAsset = sprintf('%.3f %s', $amount, $asset);
			$result = $this->broadcast($from, $to, $amountAsset, $memo, $wif);
			$trxId = self::extractTrxId($result);
			if ($trxId === '') {
				self::logResourceCreditFailure($from, $to, HiveUtil::rpcErrorMessage($result));
				return self::fail();
			}

			return ['ok' => true, 'trx_id' => $trxId];
		} catch (Throwable $e) {
			self::logResourceCreditFailure($from, $to, $e->getMessage());
			return self::fail();
		}
	}

	private function broadcast(string $from, string $to, string $amountAsset, string $memo, string $wif): mixed
	{
		if (self::$broadcaster !== null) {
			return (self::$broadcaster)($from, $to, $amountAsset, $memo, $wif);
		}

		return HiveBroadcast::operation($wif, 'transfer', [$from, $to, $amountAsset, $memo]);
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

	private static function logResourceCreditFailure(string $from, string $to, string $detail): void
	{
		if (!HiveUtil::isResourceCreditError($detail)) {
			return;
		}

		$line = 'HiveTransfer: resource credits exhausted for ' . $from . ' -> ' . $to . ': ' . $detail;
		if (self::$errorLogger !== null) {
			(self::$errorLogger)($line);
			return;
		}

		error_log($line);
	}
}
