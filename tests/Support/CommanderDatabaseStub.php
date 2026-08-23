<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory commander-loop tables for unit tests.
 */
class CommanderDatabaseStub implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $periods = [];

	/** @var list<array<string, mixed>> */
	public array $userDirectives = [];

	/** @var array<int, array<string, mixed>> */
	public array $pendingChoices = [];

	/** @var array<int, array<string, mixed>> */
	public array $planets = [];

	/** @var array<int, array<string, mixed>> */
	public array $users = [];

	/** @var list<array<string, mixed>> */
	public array $fleets = [];

	/** @var array<int, int> userId => total_points */
	public array $statpoints = [];

	public int $lastInsertIdValue = 0;

	public int $lastRowCount = 0;

	public int $transactionDepth = 0;

	public array $inserts = [];

	public array $updates = [];

	public function select($qry, array $params = array())
	{
		if (str_contains($qry, '%%DIRECTIVE_PERIODS%%')) {
			$universe = (int) ($params[':universe'] ?? 0);
			$start = (int) ($params[':start'] ?? 0);
			return array_values(array_filter(
				$this->periods,
				static fn (array $row): bool => (int) $row['universe'] === $universe
					&& ($start === 0 || (int) $row['period_start'] === $start)
			));
		}
		if (str_contains($qry, '%%USER_DIRECTIVES%%')) {
			$userId = (int) ($params[':userId'] ?? 0);
			$periodId = (int) ($params[':periodId'] ?? 0);
			return array_values(array_filter(
				$this->userDirectives,
				static function (array $row) use ($userId, $periodId): bool {
					if ($userId !== 0 && (int) $row['user_id'] !== $userId) {
						return false;
					}
					if ($periodId !== 0 && (int) $row['period_id'] !== $periodId) {
						return false;
					}
					return true;
				}
			));
		}
		if (str_contains($qry, '%%EXPEDITION_PENDING_CHOICES%%')) {
			$userId = (int) ($params[':userId'] ?? 0);
			$cutoff = (int) ($params[':cutoff'] ?? 0);
			$out = [];
			foreach ($this->pendingChoices as $row) {
				if ($userId !== 0 && (int) $row['user_id'] !== $userId) {
					continue;
				}
				if (str_contains($qry, 'resolved_at IS NULL') && !empty($row['resolved_at'])) {
					continue;
				}
				if ($cutoff !== 0 && (int) $row['created_at'] > $cutoff) {
					continue;
				}
				$out[] = $row;
			}
			return $out;
		}
		if (str_contains($qry, '%%FLEETS%%')) {
			$userId = (int) ($params[':userId'] ?? 0);
			$fleetId = (int) ($params[':fleetId'] ?? 0);
			return array_values(array_filter(
				$this->fleets,
				static function (array $row) use ($userId, $fleetId): bool {
					if ($fleetId !== 0 && (int) $row['fleet_id'] !== $fleetId) {
						return false;
					}
					return $userId === 0 || (int) $row['fleet_owner'] === $userId;
				}
			));
		}

		return [];
	}

	public function selectSingle($qry, array $params = array(), $field = false)
	{
		if (str_contains($qry, '%%STATPOINTS%%')) {
			$userId = (int) ($params[':userId'] ?? 0);
			if (!isset($this->statpoints[$userId])) {
				return false;
			}
			$row = ['total_points' => (int) $this->statpoints[$userId]];
			return $field ? ($row[$field] ?? false) : $row;
		}
		if (str_contains($qry, '%%USERS%%') && str_contains($qry, 'settings_push')) {
			$userId = (int) ($params[':userId'] ?? 0);
			$value = $this->users[$userId]['settings_push'] ?? 1;
			return $field ? $value : ['settings_push' => $value];
		}
		if (str_contains($qry, '%%EXPEDITION_PENDING_CHOICES%%')) {
			$fleetId = (int) ($params[':fleetId'] ?? 0);
			$row = $this->pendingChoices[$fleetId] ?? null;
			if ($row === null) {
				return false;
			}
			return $field ? ($row[$field] ?? false) : $row;
		}
		$rows = $this->select($qry, $params);
		if ($rows === []) {
			return false;
		}
		$row = $rows[0];
		return $field ? ($row[$field] ?? false) : $row;
	}

	public function insert($qry, array $params = array())
	{
		$this->inserts[] = ['qry' => $qry, 'params' => $params];
		if (str_contains($qry, '%%DIRECTIVE_PERIODS%%')) {
			$this->lastInsertIdValue++;
			$this->periods[] = [
				'id' => $this->lastInsertIdValue,
				'universe' => (int) $params[':universe'],
				'period_start' => (int) $params[':start'],
				'period_end' => (int) $params[':end'],
				'created_at' => (int) $params[':created'],
			];
			return true;
		}
		if (str_contains($qry, '%%USER_DIRECTIVES%%')) {
			$this->lastInsertIdValue++;
			$this->userDirectives[] = [
				'id' => $this->lastInsertIdValue,
				'user_id' => (int) $params[':userId'],
				'period_id' => (int) $params[':periodId'],
				'directive_key' => (string) $params[':directiveKey'],
				'progress_json' => (string) $params[':progress'],
				'completed_at' => null,
				'reward_claimed_at' => null,
			];
			return true;
		}
		if (str_contains($qry, '%%EXPEDITION_PENDING_CHOICES%%')) {
			$fleetId = (int) $params[':fleetId'];
			$this->pendingChoices[$fleetId] = [
				'fleet_id' => $fleetId,
				'user_id' => (int) $params[':userId'],
				'fleet_start_id' => (int) $params[':planetId'],
				'encounter_key' => (string) $params[':encounter'],
				'options_json' => (string) $params[':options'],
				'stance' => (string) $params[':stance'],
				'resolved_at' => null,
				'created_at' => (int) $params[':created'],
			];
			$this->lastInsertIdValue = $fleetId;
			return true;
		}

		return true;
	}

	public function update($qry, array $params = array())
	{
		$this->updates[] = ['qry' => $qry, 'params' => $params];
		$this->lastRowCount = 0;
		if (str_contains($qry, '%%USER_DIRECTIVES%%')) {
			$id = (int) ($params[':id'] ?? 0);
			foreach ($this->userDirectives as $i => $row) {
				if ((int) $row['id'] !== $id) {
					continue;
				}
				if (isset($params[':progress'])) {
					$this->userDirectives[$i]['progress_json'] = $params[':progress'];
				}
				if (isset($params[':completed'])) {
					$this->userDirectives[$i]['completed_at'] = $params[':completed'];
				}
				if (isset($params[':claimed'])) {
					$this->userDirectives[$i]['reward_claimed_at'] = $params[':claimed'];
				}
				$this->lastRowCount = 1;
			}
			return true;
		}
		if (str_contains($qry, '%%EXPEDITION_PENDING_CHOICES%%')) {
			$fleetId = (int) ($params[':fleetId'] ?? 0);
			if (isset($this->pendingChoices[$fleetId])) {
				$this->pendingChoices[$fleetId]['resolved_at'] = $params[':resolved'] ?? TIMESTAMP;
				$this->lastRowCount = 1;
			}
			return true;
		}
		if (str_contains($qry, '%%FLEETS%%')) {
			$fleetId = (int) ($params[':fleetId'] ?? 0);
			foreach ($this->fleets as $i => $row) {
				if ((int) $row['fleet_id'] !== $fleetId) {
					continue;
				}
				if (isset($params[':fleetArray'])) {
					$this->fleets[$i]['fleet_array'] = $params[':fleetArray'];
				}
				if (isset($params[':amount'])) {
					$this->fleets[$i]['fleet_amount'] = $params[':amount'];
				}
				foreach (['metal', 'crystal', 'deuterium'] as $res) {
					$key = ':' . $res;
					if (isset($params[$key])) {
						$field = 'fleet_resource_' . $res;
						$this->fleets[$i][$field] = (int) ($this->fleets[$i][$field] ?? 0) + (int) $params[$key];
					}
				}
				$this->lastRowCount = 1;
			}
			return true;
		}
		if (str_contains($qry, '%%PLANETS%%')) {
			$planetId = (int) ($params[':planetId'] ?? 0);
			if (!isset($this->planets[$planetId])) {
				$this->planets[$planetId] = [
					'id' => $planetId,
					'metal' => 0,
					'crystal' => 0,
					'deuterium' => 0,
				];
			}
			foreach (['metal', 'crystal', 'deuterium'] as $res) {
				$key = ':' . $res;
				if (isset($params[$key])) {
					$this->planets[$planetId][$res] = (int) $this->planets[$planetId][$res] + (int) $params[$key];
				}
			}
			if (isset($params[':delta'])) {
				$this->planets[$planetId]['ship_delta'] = (int) ($this->planets[$planetId]['ship_delta'] ?? 0) + (int) $params[':delta'];
			}
			$this->lastRowCount = 1;
			return true;
		}

		return true;
	}

	public function delete($qry, array $params = array())
	{
		return true;
	}

	public function replace($qry, array $params = array())
	{
		return true;
	}

	public function query($qry)
	{
		return true;
	}

	public function nativeQuery($qry)
	{
		return true;
	}

	public function lastInsertId()
	{
		return $this->lastInsertIdValue;
	}

	public function rowCount()
	{
		return $this->lastRowCount;
	}

	public function getQueryCounter()
	{
		return 0;
	}

	public function quote($str)
	{
		return "'" . addslashes((string) $str) . "'";
	}

	public function disconnect()
	{
	}

	public function getHandle(): ?\PDO
	{
		return null;
	}

	public function beginTransaction(): void
	{
		$this->transactionDepth++;
	}

	public function commit(): void
	{
		if ($this->transactionDepth > 0) {
			$this->transactionDepth--;
		}
	}

	public function rollback(): void
	{
		if ($this->transactionDepth > 0) {
			$this->transactionDepth--;
		}
	}
}
