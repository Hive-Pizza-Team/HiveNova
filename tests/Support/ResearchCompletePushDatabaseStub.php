<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory DB for research-complete push unit tests.
 */
class ResearchCompletePushDatabaseStub implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $users = [];

	/** @var array<string, array{user_id: int, element_id: int, level: int, tech_end: int}> */
	public array $notified = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $inserts = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $deletes = [];

	public function select($qry, array $params = [])
	{
		if (str_contains($qry, '%%USERS%%')) {
			$now = (int) ($params[':now'] ?? 0);

			return array_values(array_filter(
				$this->users,
				static function (array $row) use ($now): bool {
					return (int) ($row['b_tech'] ?? 0) > 0
						&& (int) ($row['b_tech'] ?? 0) <= $now
						&& !empty($row['has_subscription'])
						&& (int) ($row['settings_push'] ?? 1) === 1;
				}
			));
		}

		return [];
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%PUSH_RESEARCH_NOTIFIED%%')) {
			$key = $this->key(
				(int) ($params[':userId'] ?? 0),
				(int) ($params[':elementId'] ?? 0),
				(int) ($params[':level'] ?? 0),
				(int) ($params[':techEnd'] ?? 0)
			);
			$row = $this->notified[$key] ?? null;
			if ($row === null) {
				return false;
			}

			return $field === false ? $row : ($row[$field] ?? false);
		}

		return false;
	}

	public function insert($qry, array $params = [])
	{
		$this->inserts[] = ['qry' => $qry, 'params' => $params];
		if (str_contains($qry, '%%PUSH_RESEARCH_NOTIFIED%%')) {
			$key = $this->key(
				(int) ($params[':userId'] ?? 0),
				(int) ($params[':elementId'] ?? 0),
				(int) ($params[':level'] ?? 0),
				(int) ($params[':techEnd'] ?? 0)
			);
			$this->notified[$key] = [
				'user_id'     => (int) ($params[':userId'] ?? 0),
				'element_id'  => (int) ($params[':elementId'] ?? 0),
				'level'       => (int) ($params[':level'] ?? 0),
				'tech_end'    => (int) ($params[':techEnd'] ?? 0),
				'notified_at' => (int) ($params[':notifiedAt'] ?? 0),
			];
		}

		return 1;
	}

	public function delete($qry, array $params = [])
	{
		$this->deletes[] = ['qry' => $qry, 'params' => $params];
		if (str_contains($qry, '%%PUSH_RESEARCH_NOTIFIED%%') && isset($params[':old'])) {
			$old = (int) $params[':old'];
			foreach ($this->notified as $key => $row) {
				if ((int) ($row['notified_at'] ?? 0) > 0 && (int) $row['notified_at'] < $old) {
					unset($this->notified[$key]);
				}
			}
		}

		return 1;
	}

	public function update($qry, array $params = []) { return 0; }
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

	private function key(int $userId, int $elementId, int $level, int $techEnd): string
	{
		return $userId . ':' . $elementId . ':' . $level . ':' . $techEnd;
	}
}
