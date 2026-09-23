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

    /** @var list<array<string, mixed>> */
    public array $logFleetRows = [];

    public int $onlineUserCount = 0;

    public int $acsGroupMemberCount = 0;

    /**
     * Joinable ACS memberships for AcsJoinService queries (marker: member.userID).
     *
     * @var list<array<string, mixed>>
     */
    public array $acsInvites = [];

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
        if (str_contains($qry, 'member.userID')) {
            return $this->acsInviteRows($params);
        }

        if (str_contains($qry, '%%FLEETS%%')
            && str_contains($qry, 'DISTINCT fleet_owner')
            && str_contains($qry, 'fleet_mission')) {
            $seen = [];
            $out = [];
            foreach ($this->fleetRowsById as $row) {
                if ((int) ($row['fleet_universe'] ?? 1) !== (int) ($params[':universe'] ?? 1)) {
                    continue;
                }
                if ((int) ($row['fleet_mess'] ?? 0) !== (int) ($params[':outward'] ?? 0)) {
                    continue;
                }
                if (!in_array((int) ($row['fleet_mission'] ?? 0), [1, 2, 9], true)) {
                    continue;
                }
                $owner = (int) ($row['fleet_owner'] ?? 0);
                if (isset($seen[$owner])) {
                    continue;
                }
                $seen[$owner] = true;
                $out[] = $row;
            }
            return $out;
        }

        if (str_contains($qry, '%%FLEETS%%')
            && str_contains($qry, 'fleet_end_id')
            && str_contains($qry, 'fleet_mission')
            && str_contains($qry, ':mission')) {
            $planetId = (int) ($params[':fleetEndId'] ?? 0);
            return array_values(array_filter(
                $this->stayFleetsAtPlanet,
                static fn (array $row): bool => (int) ($row['fleet_end_id'] ?? 0) === $planetId
            ));
        }

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
        if (str_contains($qry, 'member.userID')) {
            $rows = $this->acsInviteRows($params);
            $row = $rows[0] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }

            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, '%%AKS%%') && str_contains($qry, 'ankunft')) {
            $id = (int) ($params[':acsId'] ?? 0);
            $row = $this->aksRows[$id] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, '%%LOG_FLEETS%%') && str_contains($qry, 'COUNT(*)')) {
            if (isset($params[':since']) && str_contains($qry, 'fleet_owner = 0')) {
                $count = ['total' => $this->countRecentNpcRaids($this->logFleetRows, $params)];
                return $field === false ? $count : ($count[$field] ?? false);
            }
            if (str_contains($qry, 'fleet_end_galaxy') || str_contains($qry, 'AS total')) {
                $count = ['total' => $this->expeditionLogCount];
                return $field === false ? $count : ($count[$field] ?? false);
            }
            $count = ['state' => $this->bashLogCount];
            return $field === false ? $count : ($count[$field] ?? false);
        }

        if (str_contains($qry, '%%USERS%%') && str_contains($qry, 'COUNT(*)') && str_contains($qry, 'onlinetime')) {
            $count = ['total' => $this->onlineUserCount];
            return $field === false ? $count : ($count[$field] ?? false);
        }

        if (str_contains($qry, '%%FLEETS%%')
            && str_contains($qry, 'COUNT(*)')
            && str_contains($qry, 'fleet_owner = 0')) {
            if (isset($params[':since'])) {
                $count = ['total' => $this->countRecentNpcRaids($this->fleetRowsById, $params)];
                return $field === false ? $count : ($count[$field] ?? false);
            }
            $planetId = (int) ($params[':planetId'] ?? 0);
            $outward = (int) ($params[':outward'] ?? 0);
            $n = 0;
            foreach ($this->fleetRowsById as $row) {
                if ((int) ($row['fleet_end_id'] ?? 0) !== $planetId) {
                    continue;
                }
                if ((int) ($row['fleet_owner'] ?? -1) !== 0) {
                    continue;
                }
                if ((int) ($row['fleet_mission'] ?? 0) !== 1) {
                    continue;
                }
                if ((int) ($row['fleet_mess'] ?? 0) !== $outward) {
                    continue;
                }
                $n++;
            }
            $count = ['total' => $n];
            return $field === false ? $count : ($count[$field] ?? false);
        }

        if (str_contains($qry, '%%FLEETS%%')
            && str_contains($qry, 'COUNT(*)')
            && str_contains($qry, 'fleet_target_owner')) {
            $targetId = (int) ($params[':targetUserId'] ?? 0);
            $ownerId = (int) ($params[':ownerId'] ?? $targetId);
            $outward = (int) ($params[':outward'] ?? 0);
            $hostile = [1, 2, 6, 9, 10];
            $incoming = 0;
            foreach ($this->fleetRowsById as $row) {
                if ((int) ($row['fleet_target_owner'] ?? 0) !== $targetId) {
                    continue;
                }
                if ((int) ($row['fleet_owner'] ?? 0) === $ownerId) {
                    continue;
                }
                if ((int) ($row['fleet_mess'] ?? 0) !== $outward) {
                    continue;
                }
                if (!in_array((int) ($row['fleet_mission'] ?? 0), $hostile, true)) {
                    continue;
                }
                $incoming++;
            }
            $count = ['incoming' => $incoming];
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

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function acsInviteRows(array $params): array
    {
        $userId = (int) ($params[':userID'] ?? 0);
        $acsId = (int) ($params[':acsId'] ?? 0);
        $targetId = (int) ($params[':targetId'] ?? 0);
        $maxFleets = (int) ($params[':maxFleets'] ?? PHP_INT_MAX);
        $out = [];

        foreach ($this->acsInvites as $invite) {
            if ((int) ($invite['userId'] ?? 0) !== $userId) {
                continue;
            }
            $group = (int) ($invite['acsId'] ?? 0);
            if ($acsId > 0 && $group !== $acsId) {
                continue;
            }
            if ($targetId > 0 && (int) ($invite['target'] ?? 0) !== $targetId) {
                continue;
            }

            $fleetCount = 0;
            foreach ($this->fleetRowsById as $fleet) {
                if ((int) ($fleet['fleet_group'] ?? 0) === $group) {
                    $fleetCount++;
                }
            }
            if ($maxFleets <= $fleetCount) {
                continue;
            }

            $out[] = [
                'id' => $group,
                'name' => (string) ($invite['name'] ?? ''),
                'galaxy' => (int) ($invite['galaxy'] ?? 0),
                'system' => (int) ($invite['system'] ?? 0),
                'planet' => (int) ($invite['planet'] ?? 0),
                'planet_type' => (int) ($invite['planet_type'] ?? 1),
                'ankunft' => (int) ($invite['ankunft'] ?? 0),
                'target' => (int) ($invite['target'] ?? 0),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        return $out;
    }

    /**
     * @param iterable<int|string, array<string, mixed>> $rows
     * @param array<string, mixed> $params
     */
    private function countRecentNpcRaids(iterable $rows, array $params): int
    {
        $planetId = (int) ($params[':planetId'] ?? 0);
        $since = (int) ($params[':since'] ?? 0);
        $n = 0;
        foreach ($rows as $row) {
            if ((int) ($row['fleet_end_id'] ?? 0) !== $planetId) {
                continue;
            }
            if ((int) ($row['fleet_owner'] ?? -1) !== 0) {
                continue;
            }
            if ((int) ($row['fleet_mission'] ?? 0) !== 1) {
                continue;
            }
            if ((int) ($row['start_time'] ?? 0) < $since) {
                continue;
            }
            $n++;
        }

        return $n;
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
