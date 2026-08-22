<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Read-only Hive-Engine HTTP helper. Inject a fetcher in tests.
 */
class HiveEngineClient
{
	public const TX_URL = 'https://api.hive-engine.com/blockchain/getTransactionInfo?txid=';
	public const HISTORY_URL = 'https://history.hive-engine.com/accountHistory?account=';

	/** @var callable|null fn(string $url): string|false */
	private static $fetcher = null;

	public static function setFetcher(?callable $fetcher): void
	{
		self::$fetcher = $fetcher;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getTransaction(string $txid): ?array
	{
		$txid = preg_replace('/[^a-fA-F0-9]/', '', $txid) ?? '';
		if ($txid === '') {
			return null;
		}

		$raw = $this->fetch(self::TX_URL . urlencode($txid));
		if ($raw === null) {
			return null;
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function accountHistory(string $account, string $symbol = 'PIZZA', int $limit = 100): array
	{
		if (!HiveUtil::isAccountValid($account)) {
			return [];
		}
		$limit = max(1, min(500, $limit));
		$url = self::HISTORY_URL . urlencode($account)
			. '&limit=' . $limit
			. '&offset=0&type=user&symbol=' . urlencode($symbol);
		$raw = $this->fetch($url);
		if ($raw === null) {
			return [];
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? array_values($decoded) : [];
	}

	/**
	 * Normalize an engine transfer record into a common shape.
	 *
	 * @param array<string, mixed> $row
	 * @return array{from: string, to: string, quantity: float, symbol: string, memo: string, trx_id: string, timestamp: int}|null
	 */
	public static function parseTransfer(array $row): ?array
	{
		$payload = $row;
		if (isset($row['payload']) && is_array($row['payload'])) {
			$payload = $row['payload'];
		} elseif (isset($row['contractPayload']) && is_array($row['contractPayload'])) {
			$payload = $row['contractPayload'];
		}

		$action = (string) ($row['action'] ?? $row['operation'] ?? $row['contractAction'] ?? '');
		if ($action !== '' && strtolower($action) !== 'transfer') {
			return null;
		}

		$from = strtolower(trim((string) ($row['from'] ?? $row['sender'] ?? $row['account'] ?? '')));
		$to = strtolower(trim((string) ($payload['to'] ?? $row['to'] ?? '')));
		$symbol = strtoupper(trim((string) ($payload['symbol'] ?? $row['symbol'] ?? '')));
		$quantity = (float) ($payload['quantity'] ?? $row['quantity'] ?? 0);
		$memo = (string) ($payload['memo'] ?? $row['memo'] ?? '');
		$trxId = (string) ($row['transactionId'] ?? $row['trx_id'] ?? $row['txid'] ?? $row['_id'] ?? '');
		$timestamp = self::parseTimestamp($row['timestamp'] ?? $row['blockTime'] ?? $row['timestampISO'] ?? 0);

		if ($from === '' || $to === '' || $symbol === '' || $quantity <= 0) {
			return null;
		}

		return [
			'from'      => $from,
			'to'        => $to,
			'quantity'  => $quantity,
			'symbol'    => $symbol,
			'memo'      => $memo,
			'trx_id'    => $trxId,
			'timestamp' => $timestamp,
		];
	}

	private static function parseTimestamp(mixed $value): int
	{
		if (is_int($value) || is_float($value)) {
			$n = (int) $value;
			return $n > 20000000000 ? (int) floor($n / 1000) : $n;
		}
		if (is_string($value) && $value !== '') {
			$ts = strtotime($value);
			return $ts !== false ? $ts : 0;
		}

		return 0;
	}

	private function fetch(string $url): ?string
	{
		try {
			$fn = self::$fetcher;
			if ($fn !== null) {
				$raw = $fn($url);
				return is_string($raw) && $raw !== '' ? $raw : null;
			}

			$ctx = stream_context_create([
				'http' => [
					'timeout' => 8,
					'ignore_errors' => true,
				],
			]);
			$raw = @file_get_contents($url, false, $ctx);
			return is_string($raw) && $raw !== '' ? $raw : null;
		} catch (Throwable $e) {
			return null;
		}
	}
}
