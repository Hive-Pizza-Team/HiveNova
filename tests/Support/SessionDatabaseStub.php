<?php

use HiveNova\Core\DatabaseInterface;

/**
 * Minimal DatabaseInterface stub for Session unit tests.
 */
class SessionDatabaseStub implements DatabaseInterface
{
    /** @var array<string, array{userID: int, lastonline: int}> */
    public array $sessionRows = [];

    /** @var array<int, array{id: int, id_planet: int, bana: int}> */
    public array $userRows = [];

    public int $sessionCount = 0;

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if (str_contains($qry, '%%SESSION%%') && str_contains($qry, 'lastonline')) {
            $sessionId = $params[':sessionId'] ?? '';
            $row = $this->sessionRows[$sessionId] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, '%%USERS%%')) {
            $userId = (int) ($params[':userId'] ?? 0);
            $row = $this->userRows[$userId] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, 'COUNT(*)')) {
            $count = ['record' => $this->sessionCount];
            return $field === false ? $count : $count[$field];
        }

        return $field === false ? null : false;
    }

    public function select($qry, array $params = [])
    {
        return [];
    }

    public function insert($qry, array $params = [])
    {
        return 0;
    }

    public function update($qry, array $params = [])
    {
        return 0;
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

    public function lastInsertId()
    {
        return false;
    }

    public function rowCount()
    {
        return false;
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
