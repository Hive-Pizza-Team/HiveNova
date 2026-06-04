<?php

use HiveNova\Core\DatabaseInterface;

require_once __DIR__ . '/FakeAchievementDatabase.php';
require_once __DIR__ . '/SessionDatabaseStub.php';

/**
 * Composed in-memory DatabaseInterface for unit tests.
 * Routes session queries to SessionDatabaseStub and achievement/game queries to FakeAchievementDatabase.
 */
class FakeDatabase implements DatabaseInterface
{
    public FakeAchievementDatabase $achievement;

    public SessionDatabaseStub $session;

    public function __construct(
        ?FakeAchievementDatabase $achievement = null,
        ?SessionDatabaseStub $session = null,
    ) {
        $this->achievement = $achievement ?? new FakeAchievementDatabase();
        $this->session = $session ?? new SessionDatabaseStub();
    }

    private function route(string $qry): SessionDatabaseStub|FakeAchievementDatabase
    {
        if (str_contains($qry, '%%SESSION%%')) {
            return $this->session;
        }

        if (str_contains($qry, '%%USERS%%')
            && (str_contains($qry, 'id_planet') || str_contains($qry, 'bana'))) {
            return $this->session;
        }

        return $this->achievement;
    }

    public function select($qry, array $params = [])
    {
        return $this->route($qry)->select($qry, $params);
    }

    public function selectSingle($qry, array $params = [], $field = false)
    {
        return $this->route($qry)->selectSingle($qry, $params, $field);
    }

    public function insert($qry, array $params = [])
    {
        return $this->route($qry)->insert($qry, $params);
    }

    public function update($qry, array $params = [])
    {
        return $this->route($qry)->update($qry, $params);
    }

    public function delete($qry, array $params = [])
    {
        return $this->route($qry)->delete($qry, $params);
    }

    public function replace($qry, array $params = [])
    {
        return $this->route($qry)->replace($qry, $params);
    }

    public function query($qry)
    {
        return $this->route($qry)->query($qry);
    }

    public function nativeQuery($qry)
    {
        return $this->route($qry)->nativeQuery($qry);
    }

    public function lastInsertId()
    {
        return $this->achievement->lastInsertId();
    }

    public function rowCount()
    {
        return $this->achievement->rowCount();
    }

    public function getQueryCounter()
    {
        return $this->achievement->getQueryCounter() + $this->session->getQueryCounter();
    }

    public function quote($str)
    {
        return $this->achievement->quote($str);
    }

    public function disconnect()
    {
        $this->achievement->disconnect();
        $this->session->disconnect();
    }

    public function beginTransaction(): void
    {
        $this->achievement->beginTransaction();
        $this->session->beginTransaction();
    }

    public function commit(): void
    {
        $this->achievement->commit();
        $this->session->commit();
    }

    public function rollback(): void
    {
        $this->achievement->rollback();
        $this->session->rollback();
    }
}
