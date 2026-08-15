<?php

use HiveNova\Core\DatabaseInterface;

/**
 * Tracks push subscription writes for push notification unit tests.
 */
class PushSubscriptionDatabaseStub implements DatabaseInterface
{
	/** @var array<string, array{user_id: int, endpoint: string, p256dh: string, auth: string}> */
	public array $subscriptionsByEndpoint = [];

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

		return false;
	}

	public function select($qry, array $params = []) { return []; }

	public function delete($qry, array $params = [])
	{
		$this->deletes[] = ['qry' => $qry, 'params' => $params];

		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && isset($params[':userId'])) {
			$userId = (int) $params[':userId'];
			foreach ($this->subscriptionsByEndpoint as $endpoint => $row) {
				if ((int) $row['user_id'] === $userId) {
					unset($this->subscriptionsByEndpoint[$endpoint]);
				}
			}
		}

		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && isset($params[':endpoint']) && !isset($params[':userId'])) {
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
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	public function insert($qry, array $params = [])
	{
		$this->inserts[] = $params;
		$this->subscriptionsByEndpoint[$params[':endpoint']] = [
			'user_id'  => (int) $params[':userId'],
			'endpoint' => $params[':endpoint'],
			'p256dh'   => $params[':p256dh'],
			'auth'     => $params[':auth'],
		];

		return 1;
	}

	public function update($qry, array $params = [])
	{
		$this->updates[] = ['qry' => $qry, 'params' => $params];

		if (str_contains($qry, 'settings_push')) {
			$this->settingsPushByUser[(int) $params[':userId']] = (int) $params[':enabled'];
		}

		if (isset($params[':endpoint'])) {
			$this->subscriptionsByEndpoint[$params[':endpoint']] = [
				'user_id'  => (int) $params[':userId'],
				'endpoint' => $params[':endpoint'],
				'p256dh'   => $params[':p256dh'],
				'auth'     => $params[':auth'],
			];
		}

		return 1;
	}
}
