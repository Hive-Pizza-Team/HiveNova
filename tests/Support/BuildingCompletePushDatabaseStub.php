<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory DB for building-complete push unit tests.
 */
class BuildingCompletePushDatabaseStub implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $planets = [];

	/** @var array<string, array{planet_id: int, element_id: int, level: int, build_end: int}> */
	public array $notified = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $inserts = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $deletes = [];

	/** @var list<array{qry: string, params: array<string, mixed>}> */
	public array $updates = [];

	public function select($qry, array $params = [])
	{
		if (str_contains($qry, '%%PUSH_BUILDING_NOTIFIED%%') && str_contains($qry, 'notified_at = 0')) {
			$pending = [];
			foreach ($this->notified as $row) {
				if ((int) ($row['notified_at'] ?? 0) !== 0) {
					continue;
				}
				$planet = $this->planetById((int) ($row['planet_id'] ?? 0));
				if ($planet === null || empty($planet['has_subscription']) || (int) ($planet['settings_push'] ?? 1) !== 1) {
					continue;
				}
				$pending[] = [
					'planet_id'   => $row['planet_id'],
					'element_id'  => $row['element_id'],
					'level'       => $row['level'],
					'build_end'   => $row['build_end'],
					'planet_name' => $planet['planet_name'] ?? '',
					'user_id'     => $planet['user_id'] ?? 0,
					'lang'        => $planet['lang'] ?? 'en',
				];
			}

			return $pending;
		}

		if (str_contains($qry, '%%PLANETS%%')) {
			$now = (int) ($params[':now'] ?? 0);

			return array_values(array_filter(
				$this->planets,
				static function (array $row) use ($now): bool {
					return (int) ($row['b_building'] ?? 0) > 0
						&& (int) ($row['b_building'] ?? 0) <= $now
						&& !empty($row['has_subscription'])
						&& (int) ($row['settings_push'] ?? 1) === 1;
				}
			));
		}

		return [];
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%PUSH_BUILDING_NOTIFIED%%')) {
			$key = $this->key(
				(int) ($params[':planetId'] ?? 0),
				(int) ($params[':elementId'] ?? 0),
				(int) ($params[':level'] ?? 0),
				(int) ($params[':buildEnd'] ?? 0)
			);
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
		if (str_contains($qry, '%%PUSH_BUILDING_NOTIFIED%%')) {
			$key = $this->key(
				(int) ($params[':planetId'] ?? 0),
				(int) ($params[':elementId'] ?? 0),
				(int) ($params[':level'] ?? 0),
				(int) ($params[':buildEnd'] ?? 0)
			);
			$this->notified[$key] = [
				'planet_id'   => (int) ($params[':planetId'] ?? 0),
				'element_id'  => (int) ($params[':elementId'] ?? 0),
				'level'       => (int) ($params[':level'] ?? 0),
				'build_end'   => (int) ($params[':buildEnd'] ?? 0),
				'notified_at' => (int) ($params[':notifiedAt'] ?? 0),
			];
		}

		return 1;
	}

	public function delete($qry, array $params = [])
	{
		$this->deletes[] = ['qry' => $qry, 'params' => $params];
		if (str_contains($qry, '%%PUSH_BUILDING_NOTIFIED%%') && isset($params[':old'])) {
			$old = (int) $params[':old'];
			foreach ($this->notified as $key => $row) {
				$notifiedAt = (int) ($row['notified_at'] ?? 0);
				$buildEnd = (int) ($row['build_end'] ?? 0);
				if (($notifiedAt > 0 && $notifiedAt < $old) || ($notifiedAt === 0 && $buildEnd < $old)) {
					unset($this->notified[$key]);
				}
			}
		}

		return 1;
	}

	public function update($qry, array $params = [])
	{
		$this->updates[] = ['qry' => $qry, 'params' => $params];
		if (str_contains($qry, '%%PUSH_BUILDING_NOTIFIED%%')) {
			$key = $this->key(
				(int) ($params[':planetId'] ?? 0),
				(int) ($params[':elementId'] ?? 0),
				(int) ($params[':level'] ?? 0),
				(int) ($params[':buildEnd'] ?? 0)
			);
			if (isset($this->notified[$key])) {
				$this->notified[$key]['notified_at'] = (int) ($params[':notifiedAt'] ?? 0);
			}
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

	private function key(int $planetId, int $elementId, int $level, int $buildEnd): string
	{
		return $planetId . ':' . $elementId . ':' . $level . ':' . $buildEnd;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function planetById(int $planetId): ?array
	{
		foreach ($this->planets as $planet) {
			if ((int) ($planet['planet_id'] ?? 0) === $planetId) {
				return $planet;
			}
		}

		return null;
	}
}
