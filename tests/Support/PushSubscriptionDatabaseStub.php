<?php

use HiveNova\Core\DatabaseInterface;

/**
 * Tracks push subscription writes for push notification unit tests.
 */
class PushSubscriptionDatabaseStub implements DatabaseInterface
{
	/** @var array<string, array{user_id: int, endpoint: string, p256dh: string, auth: string, content_encoding?: string|null}> */
	public array $subscriptionsByEndpoint = [];

	/** When true, queries that mention content_encoding throw (pre-migration 51). */
	public bool $rejectContentEncoding = false;

	public array $updates = [];
	public array $inserts = [];
	public array $deletes = [];
	/** @var array<int, int> */
	public array $settingsPushByUser = [];

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && str_contains($qry, 'endpoint')) {
			$endpoint = $params[':endpoint'] ?? '';
			$row = $this->subscriptionsByEndpoint[$endpoint] ?? null;
			if ($row === null) {
				return false;
			}

			return $field === false ? $row : ($row[$field] ?? false);
		}

		if (str_contains($qry, '%%USERS%%') && str_contains($qry, 'settings_push')) {
			$userId = (int) ($params[':userId'] ?? 0);

			return $this->settingsPushByUser[$userId] ?? 1;
		}

		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && isset($params[':userId'])) {
			$userId = (int) $params[':userId'];
			foreach ($this->subscriptionsByEndpoint as $row) {
				if ((int) $row['user_id'] === $userId) {
					return $field === false ? $row : ($row[$field] ?? false);
				}
			}

			return false;
		}

		return false;
	}

	public function select($qry, array $params = [])
	{
		$this->rejectContentEncodingQuery($qry);
		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && isset($params[':userId'])) {
			$userId = (int) $params[':userId'];
			$rows = [];
			foreach ($this->subscriptionsByEndpoint as $row) {
				if ((int) $row['user_id'] === $userId) {
					$rows[] = $row;
				}
			}

			return $rows;
		}

		return [];
	}

	public function delete($qry, array $params = [])
	{
		$this->deletes[] = ['qry' => $qry, 'params' => $params];

		if (!str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%')) {
			return 1;
		}

		$hasUser = isset($params[':userId']);
		$hasEndpoint = isset($params[':endpoint']);
		$dropsOthers = $hasUser && $hasEndpoint && (str_contains($qry, '<>') || str_contains($qry, '!='));

		if ($dropsOthers) {
			$userId = (int) $params[':userId'];
			$keep = (string) $params[':endpoint'];
			foreach ($this->subscriptionsByEndpoint as $endpoint => $row) {
				if ((int) $row['user_id'] === $userId && $endpoint !== $keep) {
					unset($this->subscriptionsByEndpoint[$endpoint]);
				}
			}

			return 1;
		}

		if ($hasUser && $hasEndpoint) {
			$endpoint = (string) $params[':endpoint'];
			$row = $this->subscriptionsByEndpoint[$endpoint] ?? null;
			if ($row !== null && (int) $row['user_id'] === (int) $params[':userId']) {
				unset($this->subscriptionsByEndpoint[$endpoint]);
			}

			return 1;
		}

		if ($hasUser) {
			$userId = (int) $params[':userId'];
			foreach ($this->subscriptionsByEndpoint as $endpoint => $row) {
				if ((int) $row['user_id'] === $userId) {
					unset($this->subscriptionsByEndpoint[$endpoint]);
				}
			}

			return 1;
		}

		if ($hasEndpoint) {
			unset($this->subscriptionsByEndpoint[$params[':endpoint']]);
		}

		return 1;
	}

	public function replace($qry, array $params = []) { return 0; }
	public function query($qry) { return 0; }
	public function nativeQuery($qry) { return false; }
	public function lastInsertId() { return false; }
	public function rowCount() { return false; }
	public function getQueryCounter() { return 0; }
	public function quote($str) { return "'" . addslashes((string) $str) . "'"; }
	public function disconnect() {}
	public function getHandle(): ?\PDO { return null; }
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	public function insert($qry, array $params = [])
	{
		$this->rejectContentEncodingQuery($qry);
		$this->inserts[] = $params;
		$this->subscriptionsByEndpoint[$params[':endpoint']] = $this->subscriptionRow($params);

		return 1;
	}

	public function update($qry, array $params = [])
	{
		$this->rejectContentEncodingQuery($qry);
		$this->updates[] = ['qry' => $qry, 'params' => $params];

		if (str_contains($qry, 'settings_push')) {
			$this->settingsPushByUser[(int) $params[':userId']] = (int) $params[':enabled'];
		}

		if (isset($params[':endpoint'], $params[':p256dh'])) {
			$this->subscriptionsByEndpoint[$params[':endpoint']] = $this->subscriptionRow($params);
		}

		return 1;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array{user_id: int, endpoint: string, p256dh: string, auth: string, content_encoding?: string|null}
	 */
	private function subscriptionRow(array $params): array
	{
		$row = [
			'user_id'  => (int) $params[':userId'],
			'endpoint' => $params[':endpoint'],
			'p256dh'   => $params[':p256dh'],
			'auth'     => $params[':auth'],
		];
		if (array_key_exists(':contentEncoding', $params)) {
			$row['content_encoding'] = $params[':contentEncoding'];
		}

		return $row;
	}

	private function rejectContentEncodingQuery(string $qry): void
	{
		if ($this->rejectContentEncoding && str_contains($qry, 'content_encoding')) {
			throw new \RuntimeException('unknown column content_encoding');
		}
	}
}
