<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory users + social-memo queue for unit tests.
 */
class SocialHiveMemoDatabaseStub implements DatabaseInterface
{
	/** @var list<array<string, mixed>> */
	public array $users = [];

	/** @var list<array<string, mixed>> */
	public array $queue = [];

	public int $nextQueueId = 1;

	public int $lastRowCount = 0;

	public function select($qry, array $params = array())
	{
		if (str_contains($qry, '%%HIVE_SOCIAL_MEMO_QUEUE%%') && str_contains($qry, 'ORDER BY')) {
			$maxAttempts = (int) ($params[':maxAttempts'] ?? 5);
			$stale = (int) ($params[':stale'] ?? 0);
			$out = [];
			foreach ($this->queue as $row) {
				if ($row['sent_at'] !== null) {
					continue;
				}
				if ((int) $row['attempts'] >= $maxAttempts) {
					continue;
				}
				if ($row['claimed'] !== null && (int) $row['claimed'] >= $stale) {
					continue;
				}
				$out[] = $row;
			}

			return array_slice($out, 0, 25);
		}

		return [];
	}

	public function selectSingle($qry, array $params = array(), $field = false)
	{
		if (str_contains($qry, '%%USERS%%')) {
			$id = (int) ($params[':userId'] ?? 0);
			foreach ($this->users as $user) {
				if ((int) $user['id'] === $id) {
					if ($field !== false) {
						return $user[$field] ?? false;
					}

					return $user;
				}
			}

			return false;
		}

		if (str_contains($qry, 'MAX(`sent_at`)')) {
			$id = (int) ($params[':userId'] ?? 0);
			$kind = (string) ($params[':kind'] ?? '');
			$last = null;
			foreach ($this->queue as $row) {
				if ((int) $row['user_id'] !== $id || $row['kind'] !== $kind || $row['sent_at'] === null) {
					continue;
				}
				if ($last === null || (int) $row['sent_at'] > $last) {
					$last = (int) $row['sent_at'];
				}
			}
			if ($field !== false) {
				return $last;
			}

			return ['last_sent' => $last];
		}

		if (str_contains($qry, '%%HIVE_SOCIAL_MEMO_QUEUE%%') && str_contains($qry, '`sent_at` IS NULL')) {
			$id = (int) ($params[':userId'] ?? 0);
			$kind = (string) ($params[':kind'] ?? '');
			$maxAttempts = (int) ($params[':maxAttempts'] ?? 5);
			foreach ($this->queue as $row) {
				if ((int) $row['user_id'] !== $id || $row['kind'] !== $kind) {
					continue;
				}
				if ($row['sent_at'] !== null || (int) $row['attempts'] >= $maxAttempts) {
					continue;
				}
				if ($field !== false) {
					return $row[$field] ?? $row['queue_id'];
				}

				return $row;
			}

			return false;
		}

		return false;
	}

	public function insert($qry, array $params = array())
	{
		$this->queue[] = [
			'queue_id'    => $this->nextQueueId,
			'user_id'     => (int) ($params[':userId'] ?? 0),
			'kind'        => (string) ($params[':kind'] ?? ''),
			'sender_name' => (string) ($params[':sender'] ?? ''),
			'lang'        => (string) ($params[':lang'] ?? 'en'),
			'created'     => (int) ($params[':created'] ?? 0),
			'claimed'     => null,
			'sent_at'     => null,
			'attempts'    => 0,
		];
		$this->nextQueueId++;

		return true;
	}

	public function update($qry, array $params = array())
	{
		$this->lastRowCount = 0;
		$id = (int) ($params[':id'] ?? 0);
		$now = (int) ($params[':now'] ?? 0);
		$stale = (int) ($params[':stale'] ?? 0);

		foreach ($this->queue as $i => $row) {
			if ((int) $row['queue_id'] !== $id) {
				continue;
			}
			if (str_contains($qry, '`attempts` = `attempts` + 1')) {
				if ($row['sent_at'] !== null) {
					return true;
				}
				if ($row['claimed'] !== null && (int) $row['claimed'] >= $stale) {
					return true;
				}
				$this->queue[$i]['claimed'] = $now;
				$this->queue[$i]['attempts'] = (int) $row['attempts'] + 1;
				$this->lastRowCount = 1;
				return true;
			}
			if (str_contains($qry, 'SET `sent_at`')) {
				if ($row['sent_at'] !== null) {
					return true;
				}
				$this->queue[$i]['sent_at'] = $now;
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
		return $this->nextQueueId - 1;
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
	}

	public function commit(): void
	{
	}

	public function rollback(): void
	{
	}
}
