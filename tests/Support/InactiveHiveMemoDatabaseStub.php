<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory users + config updates for inactive Hive memo tests.
 */
class InactiveHiveMemoDatabaseStub implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $users = [];

	/** @var array<string, mixed> */
	public array $configRow = [];

	public int $lastRowCount = 0;

	public int $transactionDepth = 0;

	public bool $failNextTransaction = false;

	public function select($qry, array $params = array())
	{
		$threshold = (int) ($params[':threshold'] ?? 0);
		$auth = (int) ($params[':auth'] ?? 0);
		$out = [];
		foreach ($this->users as $user) {
			if ((int) $user['authlevel'] !== $auth) {
				continue;
			}
			if ((string) $user['hive_account'] === '') {
				continue;
			}
			if ((int) $user['onlinetime'] >= $threshold) {
				continue;
			}
			$marker = $user['inactive_hive_memo_onlinetime'] ?? null;
			if ($marker !== null && (int) $marker === (int) $user['onlinetime']) {
				continue;
			}
			$out[] = $user;
		}

		return $out;
	}

	public function selectSingle($qry, array $params = array(), $field = false)
	{
		return false;
	}

	public function insert($qry, array $params = array())
	{
		return true;
	}

	public function update($qry, array $params = array())
	{
		$this->lastRowCount = 0;

		if (str_contains($qry, '%%CONFIG%%')) {
			foreach ($params as $key => $value) {
				$col = ltrim((string) $key, ':');
				$this->configRow[$col] = $value;
			}
			$this->lastRowCount = 1;
			return true;
		}

		if (str_contains($qry, 'SET `inactive_hive_memo_onlinetime` = `onlinetime`')) {
			$auth = (int) ($params[':auth'] ?? 0);
			$threshold = (int) ($params[':threshold'] ?? 0);
			foreach ($this->users as $i => $user) {
				if ((int) $user['authlevel'] !== $auth) {
					continue;
				}
				if ((string) $user['hive_account'] === '') {
					continue;
				}
				if ((int) $user['onlinetime'] >= $threshold) {
					continue;
				}
				$this->users[$i]['inactive_hive_memo_onlinetime'] = (int) $user['onlinetime'];
				$this->lastRowCount++;
			}
			return true;
		}

		if (str_contains($qry, 'SET `inactive_hive_memo_onlinetime` = NULL')) {
			$id = (int) ($params[':id'] ?? 0);
			$online = (int) ($params[':online'] ?? 0);
			foreach ($this->users as $i => $user) {
				if ((int) $user['id'] !== $id) {
					continue;
				}
				if ((int) ($user['inactive_hive_memo_onlinetime'] ?? -1) !== $online) {
					continue;
				}
				$this->users[$i]['inactive_hive_memo_onlinetime'] = null;
				$this->lastRowCount = 1;
			}
			return true;
		}

		if (str_contains($qry, 'SET `inactive_hive_memo_onlinetime` = :online')) {
			$id = (int) ($params[':id'] ?? 0);
			$online = (int) ($params[':online'] ?? 0);
			foreach ($this->users as $i => $user) {
				if ((int) $user['id'] !== $id) {
					continue;
				}
				if ((int) $user['onlinetime'] !== $online) {
					continue;
				}
				$marker = $user['inactive_hive_memo_onlinetime'] ?? null;
				if ($marker !== null && (int) $marker === $online) {
					return true;
				}
				$this->users[$i]['inactive_hive_memo_onlinetime'] = $online;
				$this->lastRowCount = 1;
				return true;
			}
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
		return [];
	}

	public function lastInsertId()
	{
		return 0;
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
		return "'" . $str . "'";
	}

	public function disconnect()
	{
	}

	public function getHandle(): ?PDO
	{
		return null;
	}

	public function beginTransaction(): void
	{
		if ($this->failNextTransaction) {
			$this->failNextTransaction = false;
			throw new RuntimeException('tx fail');
		}
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
