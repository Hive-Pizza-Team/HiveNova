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

    public int $lastUserInsertId = 0;

    /** @var list<array<string, mixed>> */
    public array $galaxyRows = [];

    /** @var list<array{ally_name: string}> */
    public array $systemControlAlliances = [];

    private ?string $lastInsertKind = null;

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
        if ($this->isFlyingFleetsTableQuery($qry)) {
            return $this->flyingFleetsTableSelect($qry, $params);
        }

        if ($this->isGalaxyDataQuery($qry)) {
            return $this->galaxyDataSelect($qry, $params);
        }

        if ($this->isSystemControlQuery($qry)) {
            return $this->systemControlSelect();
        }

        return match ($this->route($qry)) {
            'fleet' => $this->fleetSelect($qry, $params),
            'session' => $this->session->select($qry, $params),
            default => $this->achievement->select($qry, $params),
        };
    }

    private function isFlyingFleetsTableQuery(string $qry): bool
    {
        return str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'own_username');
    }

    private function isGalaxyDataQuery(string $qry): bool
    {
        return str_contains($qry, 'SQL_BIG_RESULT')
            && str_contains($qry, '%%PLANETS%%')
            && str_contains($qry, 'diploLevel');
    }

    private function isSystemControlQuery(string $qry): bool
    {
        return str_contains($qry, 'planet_count')
            && str_contains($qry, 'ally_name')
            && str_contains($qry, 'MAX(planet_count)');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function galaxyDataSelect(string $qry, array $params): array
    {
        $galaxy = (int) ($params[':galaxy'] ?? 0);
        $system = (int) ($params[':system'] ?? 0);
        $universe = (int) ($params[':universe'] ?? 0);
        $planetType = (int) ($params[':planetTypePlanet'] ?? 1);

        return array_values(array_filter(
            $this->galaxyRows,
            static function (array $row) use ($galaxy, $system, $universe, $planetType): bool {
                if ((int) ($row['galaxy'] ?? 0) !== $galaxy) {
                    return false;
                }
                if ((int) ($row['system'] ?? 0) !== $system) {
                    return false;
                }
                if (isset($row['universe']) && (int) $row['universe'] !== $universe) {
                    return false;
                }
                if (isset($row['planet_type']) && (int) $row['planet_type'] !== $planetType) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * @return list<array{ally_name: string}>
     */
    private function systemControlSelect(): array
    {
        return $this->systemControlAlliances;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flyingFleetsTableSelect(string $qry, array $params): array
    {
        $rows = array_values($this->fleetRowsById);

        if (isset($params[':acsId'])) {
            $acsId = (int) $params[':acsId'];
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (int) ($row['fleet_group'] ?? 0) === $acsId
            ));
        } elseif (isset($params[':planetId']) && str_contains($qry, 'fleet_start_id = :planetId')) {
            $planetId = (int) $params[':planetId'];
            $rows = array_values(array_filter(
                $rows,
                static function (array $row) use ($planetId): bool {
                    $startMatch = (int) ($row['fleet_start_id'] ?? 0) === $planetId
                        && (int) ($row['fleet_start_type'] ?? 0) === 1
                        && (int) ($row['fleet_mission'] ?? 0) !== 4;
                    $endMatch = (int) ($row['fleet_end_id'] ?? 0) === $planetId
                        && (int) ($row['fleet_end_type'] ?? 0) === 1
                        && (int) ($row['fleet_mission'] ?? 0) !== 8
                        && in_array((int) ($row['fleet_mess'] ?? 0), [0, 2], true);

                    return $startMatch || $endMatch;
                }
            ));
        } elseif (isset($params[':userId'])) {
            $userId = (int) $params[':userId'];
            if (str_contains($qry, 'fleet_mission IN')) {
                preg_match('/fleet_mission IN \(([^)]+)\)/', $qry, $matches);
                $missions = array_map(intval(...), explode(',', $matches[1] ?? ''));
                $rows = array_values(array_filter(
                    $rows,
                    static function (array $row) use ($userId, $missions): bool {
                        $owner = (int) ($row['fleet_owner'] ?? 0) === $userId;
                        $target = (int) ($row['fleet_target_owner'] ?? 0) === $userId
                            && (int) ($row['fleet_mission'] ?? 0) !== 8;
                        $missionOk = in_array((int) ($row['fleet_mission'] ?? 0), $missions, true);

                        return ($owner || $target) && $missionOk;
                    }
                ));
            } else {
                $rows = array_values(array_filter(
                    $rows,
                    static function (array $row) use ($userId): bool {
                        return (int) ($row['fleet_owner'] ?? 0) === $userId
                            || ((int) ($row['fleet_target_owner'] ?? 0) === $userId
                                && (int) ($row['fleet_mission'] ?? 0) !== 8);
                    }
                ));
            }
        } else {
            return [];
        }

        return array_map(fn (array $row): array => $this->enrichFlyingFleetRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichFlyingFleetRow(array $row): array
    {
        $ownerId = (int) ($row['fleet_owner'] ?? 0);
        $targetId = (int) ($row['fleet_target_owner'] ?? 0);
        $startPlanetId = (int) ($row['fleet_start_id'] ?? 0);
        $endPlanetId = (int) ($row['fleet_end_id'] ?? 0);

        $row['own_username'] = $this->achievement->users[$ownerId]['username'] ?? 'owner' . $ownerId;
        $row['target_username'] = $this->achievement->users[$targetId]['username'] ?? 'target' . $targetId;
        $row['own_planetname'] = $this->planetRowsById[$startPlanetId]['name'] ?? 'Planet' . $startPlanetId;
        $row['target_planetname'] = $this->planetRowsById[$endPlanetId]['name'] ?? 'Planet' . $endPlanetId;

        return $row;
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
        if ($this->isPlanetQuery($qry) && str_contains($qry, 'INSERT')) {
            $this->lastInsertKind = 'planet';

            return $this->planetInsert($qry, $params);
        }

        if (str_contains($qry, '%%USERS%%') && str_contains($qry, 'INSERT')) {
            $this->lastInsertKind = 'user';
            $this->lastUserInsertId = ($this->lastUserInsertId === 0) ? 100 : $this->lastUserInsertId + 1;
            $this->achievement->users[$this->lastUserInsertId] = [
                'id' => $this->lastUserInsertId,
                'username' => $params[':username'] ?? '',
                'universe' => (int) ($params[':universe'] ?? 1),
                'lang' => $params[':language'] ?? 'en',
            ];

            return true;
        }

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
        return match ($this->lastInsertKind) {
            'user' => $this->lastUserInsertId,
            'planet' => $this->lastPlanetInsertId,
            default => $this->achievement->lastInsertId(),
        };
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
