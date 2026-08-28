<?php

use HiveNova\Core\DatabaseInterface;

require_once __DIR__ . '/FakeAchievementDatabase.php';
require_once __DIR__ . '/SessionDatabaseStub.php';
require_once __DIR__ . '/FakeFleetQueryHandler.php';
require_once __DIR__ . '/FakePlanetQueryHandler.php';
require_once __DIR__ . '/FakeFrequentLocationQueryHandler.php';

/**
 * Composed in-memory DatabaseInterface for unit tests.
 * Routes session queries to SessionDatabaseStub and achievement/game queries to FakeAchievementDatabase.
 */
class FakeDatabase implements DatabaseInterface
{
    use FakeFleetQueryHandler;
    use FakePlanetQueryHandler;
    use FakeFrequentLocationQueryHandler;

    public FakeAchievementDatabase $achievement;

    public SessionDatabaseStub $session;

    public int $lastUserInsertId = 0;

    /** @var list<array<string, mixed>> */
    public array $salvagePackages = [];

    /** @var list<int> */
    public array $accusedDestIds = [];

    public int $lastFleetInsertId = 0;

    /** @var list<array<string, mixed>> */
    public array $eventFleets = [];

    public ?int $lastRowCount = null;

    public int $transactionDepth = 0;

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
        if ($this->isFrequentLocationQuery($qry)) {
            return 'frequent';
        }
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
        if (str_contains($qry, '%%FLEETS_EVENT%%')) {
            return $this->eventFleets;
        }
        if (str_contains($qry, '%%SALVAGE_PACKAGES%%')) {
            return $this->salvageSelect($qry, $params);
        }
        if (str_contains($qry, '%%LOG_FLEETS%%') && str_contains($qry, 'dest_id')) {
            return array_map(static fn (int $id): array => ['dest_id' => $id], $this->accusedDestIds);
        }
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
            'frequent' => $this->frequentLocationSelect($qry, $params),
            'fleet' => $this->fleetSelect($qry, $params),
            'planet' => $this->planetSelect($qry, $params),
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
        return str_contains($qry, '%%PLANETS%%')
            && str_contains($qry, 'diploLevel')
            && (str_contains($qry, 'SQL_BIG_RESULT') || str_contains($qry, 'GROUP BY p.id'));
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
        if (str_contains($qry, '%%SALVAGE_PACKAGES%%')) {
            $rows = $this->salvageSelect($qry, $params);
            $row = $rows[0] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }
        if ($this->isPlanetQuery($qry) && str_contains($qry, 'INNER JOIN %%USERS%%')) {
            return $this->planetUserJoinSelectSingle($qry, $params, $field);
        }

        return match ($this->route($qry)) {
            'frequent' => $this->frequentLocationSelectSingle($qry, $params, $field),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function salvageSelect(string $qry, array $params): array
    {
        if (str_contains($qry, 'COUNT(*)')) {
            $universe = (int) ($params[':universe'] ?? 0);
            $now = (int) ($params[':now'] ?? TIMESTAMP);
            $n = 0;
            foreach ($this->salvagePackages as $row) {
                if ((int) $row['universe'] === $universe && (int) $row['expires_at'] > $now) {
                    $n++;
                }
            }
            return [['total' => $n]];
        }

        $out = [];
        foreach ($this->salvagePackages as $row) {
            if (isset($params[':universe']) && (int) $row['universe'] !== (int) $params[':universe']) {
                continue;
            }
            if (isset($params[':galaxy']) && (int) $row['galaxy'] !== (int) $params[':galaxy']) {
                continue;
            }
            if (isset($params[':system']) && (int) $row['system'] !== (int) $params[':system']) {
                continue;
            }
            if (isset($params[':planet']) && (int) $row['planet'] !== (int) $params[':planet']) {
                continue;
            }
            if (isset($params[':now']) && (int) $row['expires_at'] <= (int) $params[':now']) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    public function insert($qry, array $params = [])
    {
        if (str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'INSERT')) {
            $this->lastInsertKind = 'fleet';
            $this->lastFleetInsertId = ($this->lastFleetInsertId === 0) ? 99 : $this->lastFleetInsertId + 1;
            $this->fleetRowsById[$this->lastFleetInsertId] = [
                'fleet_id' => $this->lastFleetInsertId,
                'fleet_owner' => (int) ($params[':fleetStartOwner'] ?? 0),
                'fleet_target_owner' => (int) ($params[':fleetTargetOwner'] ?? 0),
                'fleet_mission' => (int) ($params[':fleetMission'] ?? 0),
                'fleet_amount' => (int) ($params[':fleetShipCount'] ?? 0),
                'fleet_end_id' => (int) ($params[':fleetTargetPlanetID'] ?? 0),
                'fleet_mess' => FLEET_OUTWARD,
                'fleet_universe' => (int) ($params[':universe'] ?? 1),
                'start_time' => (int) ($params[':timestamp'] ?? TIMESTAMP),
                'fleet_meta' => $params[':fleetMeta'] ?? null,
            ];
            return true;
        }

        if (str_contains($qry, '%%LOG_FLEETS%%') && str_contains($qry, 'INSERT')) {
            $this->logFleetRows[] = [
                'fleet_owner' => (int) ($params[':fleetStartOwner'] ?? 0),
                'fleet_mission' => (int) ($params[':fleetMission'] ?? 0),
                'fleet_end_id' => (int) ($params[':fleetTargetPlanetID'] ?? 0),
                'start_time' => (int) ($params[':timestamp'] ?? TIMESTAMP),
            ];
            return true;
        }

        if (str_contains($qry, '%%SALVAGE_PACKAGES%%')) {
            $id = count($this->salvagePackages) + 1;
            $this->salvagePackages[] = [
                'id' => $id,
                'universe' => (int) ($params[':universe'] ?? 1),
                'galaxy' => (int) ($params[':galaxy'] ?? 0),
                'system' => (int) ($params[':system'] ?? 0),
                'planet' => (int) ($params[':planet'] ?? 0),
                'planet_id' => $params[':planetId'] ?? null,
                'metal' => (int) ($params[':metal'] ?? 0),
                'crystal' => (int) ($params[':crystal'] ?? 0),
                'spawned_at' => (int) ($params[':spawned'] ?? TIMESTAMP),
                'expires_at' => (int) ($params[':expires'] ?? TIMESTAMP + 86400),
                'tier' => (int) ($params[':tier'] ?? 1),
                'encounter_seed' => (int) ($params[':seed'] ?? 0),
            ];
            return true;
        }
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
            'frequent' => $this->frequentLocationInsert($qry, $params),
            'fleet' => true,
            'session' => $this->session->insert($qry, $params),
            default => $this->achievement->insert($qry, $params),
        };
    }

    public function update($qry, array $params = [])
    {
        if (str_contains($qry, '%%SALVAGE_PACKAGES%%')) {
            $this->lastRowCount = 0;
            foreach ($this->salvagePackages as &$row) {
                if (isset($params[':id']) && (int) $row['id'] !== (int) $params[':id']) {
                    continue;
                }
                if (isset($params[':galaxy']) && (
                    (int) $row['galaxy'] !== (int) $params[':galaxy']
                    || (int) $row['system'] !== (int) $params[':system']
                    || (int) $row['planet'] !== (int) $params[':planet']
                    || (int) $row['universe'] !== (int) ($params[':universe'] ?? $row['universe'])
                )) {
                    continue;
                }
                if (isset($params[':expectedMetal'])
                    && ((int) $row['metal'] !== (int) $params[':expectedMetal']
                        || (int) $row['crystal'] !== (int) $params[':expectedCrystal'])) {
                    continue;
                }
                if (isset($params[':planetId'])) {
                    $row['planet_id'] = (int) $params[':planetId'];
                }
                if (isset($params[':metal'])) {
                    $row['metal'] = max(0, (int) $row['metal'] - (int) $params[':metal']);
                }
                if (isset($params[':crystal'])) {
                    $row['crystal'] = max(0, (int) $row['crystal'] - (int) $params[':crystal']);
                }
                $this->lastRowCount++;
            }
            unset($row);
            return true;
        }
        return match ($this->route($qry)) {
            'frequent' => $this->frequentLocationUpdate($qry, $params),
            'fleet' => $this->fleetUpdate($qry, $params),
            'planet' => $this->planetUpdate($qry, $params),
            'session' => $this->session->update($qry, $params),
            default => $this->achievement->update($qry, $params),
        };
    }

    public function delete($qry, array $params = [])
    {
        if (str_contains($qry, '%%SALVAGE_PACKAGES%%')) {
            $this->salvagePackages = array_values(array_filter(
                $this->salvagePackages,
                static function (array $row) use ($params, $qry): bool {
                    if (isset($params[':id']) && (int) $row['id'] === (int) $params[':id']) {
                        if (str_contains($qry, 'metal <= 0')) {
                            return (int) $row['metal'] > 0 || (int) $row['crystal'] > 0;
                        }
                        return false;
                    }
                    if (isset($params[':now']) && (int) $row['expires_at'] <= (int) $params[':now']) {
                        return false;
                    }
                    return true;
                }
            ));
            return true;
        }
        return match ($this->route($qry)) {
            'frequent' => $this->frequentLocationDelete($qry, $params),
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
            'fleet' => $this->lastFleetInsertId,
            default => $this->achievement->lastInsertId(),
        };
    }

    public function rowCount()
    {
        return $this->lastRowCount ?? $this->achievement->rowCount();
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

    public function getHandle(): ?\PDO
    {
        return null;
    }

    public function beginTransaction(): void
    {
        $this->transactionDepth++;
        $this->achievement->beginTransaction();
        $this->session->beginTransaction();
    }

    public function commit(): void
    {
        $this->transactionDepth = max(0, $this->transactionDepth - 1);
        $this->achievement->commit();
        $this->session->commit();
    }

    public function rollback(): void
    {
        $this->transactionDepth = max(0, $this->transactionDepth - 1);
        $this->achievement->rollback();
        $this->session->rollback();
    }
}
