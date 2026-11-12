<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory DB for fleet-return push unit tests.
 */
class FleetReturnPushDatabaseStub implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $fleets = [];

	/** @var array<string, array{fleet_id: int, user_id: int, mission: int, galaxy: int, system: int, planet: int, planet_name: string, return_end: int, notified_at: int}> */
	public array $notified = [];

	/** @var list<array{user_id: int, endpoint: string, p256dh: string, auth: string}> */
	public array $subscriptions = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $inserts = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $deletes = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $updates = [];

	/** @var int|false */
	public $lastRowCount = false;

	public function select($qry, array $params = [])
	{
		if (str_contains($qry, '%%PUSH_SUBSCRIPTIONS%%') && isset($params[':userId'])) {
			$userId = (int) $params[':userId'];
			$rows = [];
			foreach ($this->subscriptions as $row) {
				if ((int) $row['user_id'] === $userId) {
					$rows[] = $row;
				}
			}

			return $rows;
		}

		if (str_contains($qry, '%%PUSH_FLEET_RETURN_NOTIFIED%%') && str_contains($qry, 'notified_at = 0')) {
			$pending = [];
			foreach ($this->notified as $row) {
				if ((int) ($row['notified_at'] ?? 0) !== 0) {
					continue;
				}
				if (empty($row['has_subscription']) && !$this->userHasSubscription((int) ($row['user_id'] ?? 0))) {
					continue;
				}
				if ((int) ($row['settings_push'] ?? 1) !== 1) {
					continue;
				}
				$pending[] = [
					'fleet_id'    => $row['fleet_id'],
					'user_id'     => $row['user_id'],
					'mission'     => $row['mission'],
					'galaxy'      => $row['galaxy'],
					'system'      => $row['system'],
					'planet'      => $row['planet'],
					'planet_name' => $row['planet_name'],
					'return_end'  => $row['return_end'],
					'lang'        => $row['lang'] ?? 'en',
				];
			}

			return $pending;
		}

		if (str_contains($qry, '%%FLEETS%%')) {
			$now = (int) ($params[':now'] ?? 0);
			$returnState = (int) ($params[':returnState'] ?? 1);

			return array_values(array_filter(
				$this->fleets,
				static function (array $row) use ($now, $returnState): bool {
					return (int) ($row['fleet_mess'] ?? -1) === $returnState
						&& (int) ($row['return_end'] ?? 0) > 0
						&& (int) ($row['return_end'] ?? 0) <= $now
						&& (int) ($row['user_id'] ?? 0) > 0
						&& !empty($row['has_subscription'])
						&& (int) ($row['settings_push'] ?? 1) === 1;
				}
			));
		}

		return [];
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, 'settings_push')) {
			return 1;
		}

		if (str_contains($qry, '%%PUSH_FLEET_RETURN_NOTIFIED%%')) {
			$key = (string) (int) ($params[':fleetId'] ?? 0);
			$row = $this->notified[$key] ?? null;
			if ($row === null) {
				return false;
			}
			if (str_contains($qry, 'notified_at > 0') && (int) ($row['notified_at'] ?? 0) <= 0) {
				return false;
			}

			return $field === false ? $row : ($row[$field] ?? false);
		}

		return false;
	}

	public function insert($qry, array $params = [])
	{
		$this->inserts[] = ['qry' => $qry, 'params' => $params];
		$this->lastRowCount = 0;
		if (str_contains($qry, '%%PUSH_FLEET_RETURN_NOTIFIED%%')) {
			$key = (string) (int) ($params[':fleetId'] ?? 0);
			if (!isset($this->notified[$key])) {
				$this->notified[$key] = [
					'fleet_id'         => (int) ($params[':fleetId'] ?? 0),
					'user_id'          => (int) ($params[':userId'] ?? 0),
					'mission'          => (int) ($params[':mission'] ?? 0),
					'galaxy'           => (int) ($params[':galaxy'] ?? 0),
					'system'           => (int) ($params[':system'] ?? 0),
					'planet'           => (int) ($params[':planet'] ?? 0),
					'planet_name'      => (string) ($params[':planetName'] ?? ''),
					'return_end'       => (int) ($params[':returnEnd'] ?? 0),
					'notified_at'      => (int) ($params[':notifiedAt'] ?? 0),
					'has_subscription' => true,
					'settings_push'    => 1,
					'lang'             => 'en',
				];
				$this->lastRowCount = 1;
			}
		}

		return 1;
	}

	public function delete($qry, array $params = [])
	{
		$this->deletes[] = ['qry' => $qry, 'params' => $params];
		$this->lastRowCount = 0;
		if (str_contains($qry, '%%PUSH_FLEET_RETURN_NOTIFIED%%') && isset($params[':old'])) {
			$old = (int) $params[':old'];
			foreach ($this->notified as $key => $row) {
				$notifiedAt = (int) ($row['notified_at'] ?? 0);
				$returnEnd = (int) ($row['return_end'] ?? 0);
				if (($notifiedAt > 0 && $notifiedAt < $old) || ($notifiedAt === 0 && $returnEnd < $old)) {
					unset($this->notified[$key]);
					$this->lastRowCount = (int) $this->lastRowCount + 1;
				}
			}
		}

		return 1;
	}

	public function update($qry, array $params = [])
	{
		$this->updates[] = ['qry' => $qry, 'params' => $params];
		$this->lastRowCount = 0;
		if (str_contains($qry, '%%PUSH_FLEET_RETURN_NOTIFIED%%')) {
			$key = (string) (int) ($params[':fleetId'] ?? 0);
			if (!isset($this->notified[$key])) {
				return 1;
			}
			$current = (int) ($this->notified[$key]['notified_at'] ?? 0);
			$next = (int) ($params[':notifiedAt'] ?? 0);
			if (str_contains($qry, 'notified_at = 0') && $current === 0) {
				$this->notified[$key]['notified_at'] = $next;
				$this->lastRowCount = 1;
			} elseif (str_contains($qry, 'notified_at > 0') && $current > 0) {
				$this->notified[$key]['notified_at'] = $next;
				$this->lastRowCount = 1;
			}
		}

		return 1;
	}

	public function replace($qry, array $params = []) { return 0; }
	public function query($qry) { return 0; }
	public function nativeQuery($qry) { return false; }
	public function lastInsertId() { return false; }
	public function rowCount() { return $this->lastRowCount; }
	public function getQueryCounter() { return 0; }
	public function quote($str) { return "'" . addslashes((string) $str) . "'"; }
	public function disconnect() {}
	public function getHandle(): ?\PDO { return null; }
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	private function userHasSubscription(int $userId): bool
	{
		foreach ($this->subscriptions as $row) {
			if ((int) ($row['user_id'] ?? 0) === $userId) {
				return true;
			}
		}

		return false;
	}
}
