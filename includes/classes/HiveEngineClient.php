<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Read-only Hive-Engine HTTP helper. Inject a fetcher in tests.
 */
class HiveEngineClient
{
	public const RPC_URL = 'https://api.hive-engine.com/rpc/blockchain';
	public const HISTORY_URL = 'https://history.hive-engine.com/accountHistory?account=';
	public const HISTORY_MAX_PAGES = 20;

	/** @var callable|null fn(string $url, ?string $body = null): string|false */
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

		$body = json_encode([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'getTransactionInfo',
			'params'  => ['txid' => $txid],
		], JSON_UNESCAPED_SLASHES);
		$raw = $this->fetch(self::RPC_URL, is_string($body) ? $body : null);
		if ($raw === null) {
			return null;
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return null;
		}
		if (array_key_exists('result', $decoded)) {
			return is_array($decoded['result']) ? $decoded['result'] : null;
		}

		return $decoded;
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
		$out = [];
		$offset = 0;
		for ($page = 0; $page < self::HISTORY_MAX_PAGES; $page++) {
			$url = self::HISTORY_URL . urlencode($account)
				. '&limit=' . $limit
				. '&offset=' . $offset
				. '&type=user&symbol=' . urlencode($symbol);
			$raw = $this->fetch($url);
			if ($raw === null) {
				break;
			}
			$decoded = json_decode($raw, true);
			if (!is_array($decoded) || $decoded === []) {
				break;
			}
			$chunk = array_values($decoded);
			$out = array_merge($out, $chunk);
			if (count($chunk) < $limit) {
				break;
			}
			$offset += $limit;
		}

		return $out;
	}

	/**
	 * Normalize an engine transfer record into a common shape.
	 *
	 * @param array<string, mixed> $row
	 * @return array{from: string, to: string, quantity: float, symbol: string, memo: string, trx_id: string, timestamp: int}|null
	 */
	public static function parseTransfer(array $row): ?array
	{
		foreach (['payload', 'contractPayload'] as $payloadKey) {
			if (!isset($row[$payloadKey]) || !is_string($row[$payloadKey]) || $row[$payloadKey] === '') {
				continue;
			}
			$decodedPayload = json_decode($row[$payloadKey], true);
			if (is_array($decodedPayload)) {
				$row[$payloadKey] = $decodedPayload;
			}
		}

		$payload = $row;
		if (isset($row['payload']) && is_array($row['payload'])) {
			$payload = $row['payload'];
		} elseif (isset($row['contractPayload']) && is_array($row['contractPayload'])) {
			$payload = $row['contractPayload'];
		}

		$action = strtolower((string) ($row['action'] ?? $row['operation'] ?? $row['contractAction'] ?? ''));
		if ($action !== '' && $action !== 'transfer' && $action !== 'tokens_transfer') {
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

	private function fetch(string $url, ?string $body = null): ?string
	{
		try {
			$fn = self::$fetcher;
			if ($fn !== null) {
				$raw = $fn($url, $body);
				return is_string($raw) && $raw !== '' ? $raw : null;
			}

			$http = [
				'timeout'       => 8,
				'ignore_errors' => true,
				'header'        => "User-Agent: HiveNova\r\nAccept: application/json\r\n",
			];
			if ($body !== null) {
				$http['method'] = 'POST';
				$http['header'] .= "Content-Type: application/json\r\n";
				$http['content'] = $body;
			}
			$ctx = stream_context_create(['http' => $http]);
			$raw = @file_get_contents($url, false, $ctx);
			return is_string($raw) && $raw !== '' ? $raw : null;
		} catch (Throwable $e) {
			return null;
		}
	}
}
