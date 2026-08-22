<?php

use HiveNova\Core\DatabaseInterface;

class RecordingDatabase implements DatabaseInterface
{
	/** @var list<array{0: string, 1: array}> */
	public array $selects = [];

	/** @var list<array{0: string, 1: array}> */
	public array $inserts = [];

	/** @var list<array{0: string, 1: array}> */
	public array $updates = [];

	/** @var list<array{0: string, 1: array}> */
	public array $deletes = [];

	/** @var list<array<string, mixed>> */
	public array $selectResult = [];

	/** @var array<string, mixed>|false */
	public $selectSingleResult = false;

	public int $transactionDepth = 0;

	public function select($qry, array $params = array())
	{
		$this->selects[] = [$qry, $params];

		return $this->selectResult;
	}

	public function selectSingle($qry, array $params = array(), $field = false)
	{
		$this->selects[] = [$qry, $params];
		if ($this->selectSingleResult === false || $this->selectSingleResult === null) {
			return false;
		}
		if ($field !== false && is_array($this->selectSingleResult)) {
			return $this->selectSingleResult[$field] ?? false;
		}

		return $this->selectSingleResult;
	}

	public function insert($qry, array $params = array())
	{
		$this->inserts[] = [$qry, $params];

		return true;
	}

	public function update($qry, array $params = array())
	{
		$this->updates[] = [$qry, $params];

		return true;
	}

	public function delete($qry, array $params = array())
	{
		$this->deletes[] = [$qry, $params];

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
		return 1;
	}

	public function rowCount()
	{
		return 1;
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
