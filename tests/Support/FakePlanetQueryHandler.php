<?php

/**
 * In-memory %%PLANETS%% queries for mission and repository unit tests.
 */
trait FakePlanetQueryHandler
{
    /** @var array<int, array<string, mixed>> */
    public array $planetRowsById = [];

    /** Occupied coords for isPositionFree (COUNT … AS record). */
    public int $planetPositionCount = 0;

    /** @var array<int, int> ownerId => planet count (colonisation). */
    public array $planetCountByOwner = [];

    private function isPlanetQuery(string $qry): bool
    {
        return str_contains($qry, '%%PLANETS%%');
    }

    private function planetSelectSingle(string $qry, array $params, $field = false)
    {
        if (str_contains($qry, 'COUNT(*)') && str_contains($qry, 'record')) {
            $count = ['record' => $this->planetPositionCount];
            return $field === false ? $count : ($count[$field] ?? false);
        }

        if (str_contains($qry, 'COUNT(*)') && str_contains($qry, 'state') && str_contains($qry, 'id_owner')) {
            $ownerId = (int) ($params[':userId'] ?? 0);
            $count = ['state' => $this->planetCountByOwner[$ownerId] ?? 0];
            return $field === false ? $count : ($count[$field] ?? false);
        }

        $planetId = (int) ($params[':planetId'] ?? $params[':id'] ?? 0);
        if (str_contains($qry, 'der_') && str_contains($qry, 'AS total')) {
            $row = $this->planetRowsById[$planetId] ?? [
                'der_metal' => 0,
                'der_crystal' => 0,
                'total' => 0,
            ];
            if (!isset($row['total'])) {
                $row['total'] = (int) ($row['der_metal'] ?? 0) + (int) ($row['der_crystal'] ?? 0);
            }
            if ($field === 'total') {
                return $row['total'];
            }
            return $field === false ? $row : ($row[$field] ?? false);
        }

        $planetId = (int) ($params[':id'] ?? $planetId);
        $row = $this->planetRowsById[$planetId] ?? null;
        if ($row === null) {
            return $field === false ? null : false;
        }

        if ($field === 'name') {
            return $row['name'] ?? false;
        }

        if ($field === 'id_owner') {
            return $row['id_owner'] ?? false;
        }

        if ($field !== false) {
            return $row[$field] ?? false;
        }

        return $row;
    }

    public int $lastPlanetInsertId = 0;

    /** @var list<array<string, mixed>> */
    public array $planetInserts = [];

    private function planetInsert(string $qry, array $params): bool
    {
        $this->lastPlanetInsertId = ($this->lastPlanetInsertId === 0)
            ? 200
            : $this->lastPlanetInsertId + 1;
        $this->planetInserts[] = $params;
        $this->planetRowsById[$this->lastPlanetInsertId] = [
            'id' => $this->lastPlanetInsertId,
            'id_owner' => (int) ($params[':userId'] ?? 0),
            'galaxy' => (int) ($params[':galaxy'] ?? 0),
            'system' => (int) ($params[':system'] ?? 0),
            'planet' => (int) ($params[':position'] ?? 0),
            'name' => $params[':name'] ?? 'Planet',
        ];
        $this->planetPositionCount = 1;

        return true;
    }

    private function planetUpdate(string $qry, array $params): bool
    {
        if (str_contains($qry, '%%PLANETS%%') && str_contains($qry, 'der_')) {
            $planetId = (int) ($params[':planetId'] ?? 0);
            if (!isset($this->planetRowsById[$planetId])) {
                $this->planetRowsById[$planetId] = ['id' => $planetId];
            }
            foreach (['metal', 'crystal', 'deuterium'] as $res) {
                if (isset($params[':' . $res])) {
                    $this->planetRowsById[$planetId]['der_' . $res] = max(
                        0,
                        (int) ($this->planetRowsById[$planetId]['der_' . $res] ?? 0) - (int) $params[':' . $res]
                    );
                }
            }
            return true;
        }

        return true;
    }
}
