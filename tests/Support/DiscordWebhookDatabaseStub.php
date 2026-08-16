<?php

use HiveNova\Core\DatabaseInterface;

/**
 * Database stub for Discord webhook notify lookups and fleet inserts.
 */
class DiscordWebhookDatabaseStub implements DatabaseInterface
{
	/** @var array<int, array<string, mixed>> */
	public array $users = [];

	/** @var array<int, array<string, mixed>> */
	public array $alliances = [];

	/** @var list<array{qry: string, params: array}> */
	public array $inserts = [];

	/** @var list<array{qry: string, params: array}> */
	public array $updates = [];

	public int $nextInsertId = 1;

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%USERS%%') && str_contains($qry, 'ally_discord_webhook')) {
			$userId = (int) ($params[':userId'] ?? 0);
			$user = $this->users[$userId] ?? null;
			if ($user === null) {
				return $field === false ? false : false;
			}
			$allyId = (int) ($user['ally_id'] ?? 0);
			$webhook = '';
			if ($allyId > 0 && isset($this->alliances[$allyId])) {
				$webhook = (string) ($this->alliances[$allyId]['ally_discord_webhook'] ?? '');
			}
			$row = [
				'username'              => $user['username'] ?? 'player',
				'ally_id'               => $allyId,
				'ally_discord_webhook'  => $webhook,
			];
			return $field === false ? $row : ($row[$field] ?? false);
		}

		return $field === false ? false : false;
	}

	public function select($qry, array $params = [])
	{
		return [];
	}

	public function insert($qry, array $params = [])
	{
		$this->inserts[] = ['qry' => $qry, 'params' => $params];
		return $this->nextInsertId++;
	}

	public function lastInsertId()
	{
		return max(0, $this->nextInsertId - 1);
	}

	public function update($qry, array $params = [])
	{
		$this->updates[] = ['qry' => $qry, 'params' => $params];
		if (str_contains($qry, 'ally_discord_webhook') && isset($params[':AllianceID'])) {
			$allyId = (int) $params[':AllianceID'];
			if (isset($this->alliances[$allyId])) {
				$this->alliances[$allyId]['ally_discord_webhook'] = (string) ($params[':ally_discord_webhook'] ?? '');
			}
		}
		return 1;
	}

	public function delete($qry, array $params = [])
	{
		return 0;
	}

	public function replace($qry, array $params = [])
	{
		return 0;
	}

	public function query($qry)
	{
		return 0;
	}

	public function nativeQuery($qry)
	{
		return false;
	}

	public function rowCount()
	{
		return 0;
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
	}

	public function commit(): void
	{
	}

	public function rollback(): void
	{
	}
}
