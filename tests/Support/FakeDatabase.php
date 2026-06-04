<?php

use HiveNova\Core\DatabaseInterface;

require_once __DIR__ . '/FakeAchievementDatabase.php';
require_once __DIR__ . '/SessionDatabaseStub.php';
require_once __DIR__ . '/FakeFleetQueryHandler.php';
require_once __DIR__ . '/FakePlanetQueryHandler.php';

/**
 * Composed in-memory DatabaseInterface for unit tests.
 * Routes session queries to SessionDatabaseStub and achievement/game queries to FakeAchievementDatabase.
 */
class FakeDatabase implements DatabaseInterface
{
    use FakeFleetQueryHandler;
    use FakePlanetQueryHandler;

    public FakeAchievementDatabase $achievement;

    public SessionDatabaseStub $session;

    public function __construct(
        ?FakeAchievementDatabase $achievement = null,
        ?SessionDatabaseStub $session = null,
    ) {
        $this->achievement = $achievement ?? new FakeAchievementDatabase();
        $this->session = $session ?? new SessionDatabaseStub();
    }

    private function route(string $qry): string
    {
        if ($this->isFleetQuery($qry)) {
            return 'fleet';
        }
        if ($this->isPlanetQuery($qry)) {
            return 'planet';
        }
        if (str_contains($qry, '%%SESSION%%')) {
            return 'session';
        }
        if (str_contains($qry, '%%USERS%%')
            && (str_contains($qry, 'id_planet') || str_contains($qry, 'bana'))) {
            return 'session';
        }

        return 'achievement';
    }

    public function select($qry, array $params = [])
    {
        return match ($this->route($qry)) {
            'fleet' => $this->fleetSelect($qry, $params),
            'session' => $this->session->select($qry, $params),
            default => $this->achievement->select($qry, $params),
        };
    }

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if ($this->isPlanetQuery($qry) && str_contains($qry, 'INNER JOIN %%USERS%%')) {
            return $this->planetUserJoinSelectSingle($qry, $params, $field);
        }

        return match ($this->route($qry)) {
            'fleet' => $this->fleetSelectSingle($qry, $params, $field),
            'planet' => $this->planetSelectSingle($qry, $params, $field),
            'session' => $this->session->selectSingle($qry, $params, $field),
            default => $this->achievement->selectSingle($qry, $params, $field),
        };
    }

    private function planetUserJoinSelectSingle(string $qry, array $params, $field = false)
    {
        $planetId = (int) ($params[':planetId'] ?? 0);
        $planet = $this->planetRowsById[$planetId] ?? null;
        if ($planet === null) {
            return $field === false ? null : false;
        }

        $ownerId = (int) ($planet['id_owner'] ?? 0);
        $user = $this->achievement->users[$ownerId] ?? [];
        $row = array_merge($planet, [
            'lang' => $user['lang'] ?? 'en',
            'shield_tech' => $user['shielding_tech'] ?? 0,
        ]);

        if ($field !== false) {
            return $row[$field] ?? false;
        }

        return $row;
    }

    public function insert($qry, array $params = [])
    {
        return match ($this->route($qry)) {
            'fleet' => true,
            'session' => $this->session->insert($qry, $params),
            default => $this->achievement->insert($qry, $params),
        };
    }

    public function update($qry, array $params = [])
    {
        return match ($this->route($qry)) {
            'fleet' => $this->fleetUpdate($qry, $params),
            'planet' => $this->planetUpdate($qry, $params),
            'session' => $this->session->update($qry, $params),
            default => $this->achievement->update($qry, $params),
        };
    }

    public function delete($qry, array $params = [])
    {
        return match ($this->route($qry)) {
            'fleet' => $this->fleetDelete($qry, $params),
            'session' => $this->session->delete($qry, $params),
            default => $this->achievement->delete($qry, $params),
        };
    }

    public function replace($qry, array $params = [])
    {
        return match ($this->route($qry)) {
            'fleet' => true,
            'session' => $this->session->replace($qry, $params),
            default => $this->achievement->replace($qry, $params),
        };
    }

    public function query($qry)
    {
        return match ($this->route($qry)) {
            'fleet' => true,
            'session' => $this->session->query($qry),
            default => $this->achievement->query($qry),
        };
    }

    public function nativeQuery($qry)
    {
        return match ($this->route($qry)) {
            'fleet' => [],
            'session' => $this->session->nativeQuery($qry),
            default => $this->achievement->nativeQuery($qry),
        };
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
