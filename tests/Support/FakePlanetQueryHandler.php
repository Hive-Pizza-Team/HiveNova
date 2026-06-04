<?php

/**
 * In-memory %%PLANETS%% queries for mission and repository unit tests.
 */
trait FakePlanetQueryHandler
{
    /** @var array<int, array<string, mixed>> */
    public array $planetRowsById = [];

    private function isPlanetQuery(string $qry): bool
    {
        return str_contains($qry, '%%PLANETS%%');
    }

    private function planetSelectSingle(string $qry, array $params, $field = false)
    {
        $planetId = (int) ($params[':id'] ?? 0);
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
}
