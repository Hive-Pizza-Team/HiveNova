<?php

/**
 * In-memory fleet / ACS / bash query handling for FakeDatabase.
 */
trait FakeFleetQueryHandler
{
    /** @var array<int, array<string, mixed>> */
    public array $fleetRowsById = [];

    /** @var array<int, array{ankunft: int}> */
    public array $aksRows = [];

    /** @var array<int, int> planetId => ownerId */
    public array $planetOwners = [];

    /** @var array<int, int> userId => onlinetime */
    public array $userOnlinetime = [];

    public int $fleetCountResult = 0;

    public int $bashLogCount = 0;

    /** Expeditions in target system (LOG_FLEETS COUNT … AS total). */
    public int $expeditionLogCount = 0;

    public int $acsGroupMemberCount = 0;

    /** @var list<array{sql: string, params: array}> */
    public array $fleetUpdates = [];

    /** @var list<array<string, mixed>> */
    public array $stayFleetsAtPlanet = [];

    private function isFleetQuery(string $qry): bool
    {
        return str_contains($qry, '%%LOG_FLEETS%%')
            || (str_contains($qry, '%%FLEETS%%') && !str_contains($qry, '%%LOG_FLEETS%%'))
            || str_contains($qry, '%%AKS%%')
            || str_contains($qry, '%%USERS_ACS%%')
            || (str_contains($qry, '%%USERS%%') && str_contains($qry, 'onlinetime'));
    }

    private function fleetSelect(string $qry, array $params): array
    {
        if (str_contains($qry, '%%FLEETS%%')
            && str_contains($qry, 'fleet_end_id')
            && str_contains($qry, 'fleet_mission')) {
            $planetId = (int) ($params[':planetId'] ?? 0);
            return array_values(array_filter(
                $this->stayFleetsAtPlanet,
                static fn (array $row): bool => (int) ($row['fleet_end_id'] ?? 0) === $planetId
            ));
        }

        if (str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'fleet_group')) {
            $acsId = (int) ($params[':acsId'] ?? 0);
            return array_values(array_filter(
                $this->fleetRowsById,
                static fn (array $row): bool => (int) ($row['fleet_group'] ?? 0) === $acsId
            ));
        }

        return [];
    }

    private function fleetSelectSingle(string $qry, array $params, $field = false)
    {
        if (str_contains($qry, '%%AKS%%') && str_contains($qry, 'ankunft')) {
            $id = (int) ($params[':acsId'] ?? 0);
            $row = $this->aksRows[$id] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, '%%LOG_FLEETS%%') && str_contains($qry, 'COUNT(*)')) {
            if (str_contains($qry, 'fleet_end_galaxy') || str_contains($qry, 'AS total')) {
                $count = ['total' => $this->expeditionLogCount];
                return $field === false ? $count : ($count[$field] ?? false);
            }
            $count = ['state' => $this->bashLogCount];
            return $field === false ? $count : ($count[$field] ?? false);
        }

        if (str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'COUNT(*)')) {
            $count = ['state' => $this->fleetCountResult];
            return $field === false ? $count : $count[$field];
        }

        if (str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'fleet_id')) {
            $id = (int) ($params[':fleetId'] ?? 0);
            $row = $this->fleetRowsById[$id] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, '%%USERS_ACS%%') && str_contains($qry, 'COUNT(*)')) {
            $count = ['state' => $this->acsGroupMemberCount];
            return $field === false ? $count : $count[$field];
        }

        if (str_contains($qry, '%%USERS%%') && str_contains($qry, 'onlinetime')) {
            $userId = (int) ($params[':id'] ?? 0);
            $time = $this->userOnlinetime[$userId] ?? TIMESTAMP;
            return $field === false ? ['onlinetime' => $time] : $time;
        }

        return $field === false ? null : false;
    }

    private function fleetUpdate(string $qry, array $params)
    {
        $this->fleetUpdates[] = ['sql' => $qry, 'params' => $params];
        return true;
    }

    private function fleetDelete(string $qry, array $params)
    {
        $this->fleetUpdates[] = ['sql' => $qry, 'params' => $params, 'delete' => true];
        return true;
    }
}
