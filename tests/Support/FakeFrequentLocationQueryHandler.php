<?php

/**
 * In-memory %%FREQUENT_LOCATIONS%% queries for unit tests.
 */
trait FakeFrequentLocationQueryHandler
{
    /** @var array<int, array<string, mixed>> */
    public array $frequentLocationRows = [];

    public int $lastFrequentLocationId = 0;

    public bool $throwOnFrequentLocations = false;

    private function isFrequentLocationQuery(string $qry): bool
    {
        return str_contains($qry, '%%FREQUENT_LOCATIONS%%');
    }

    private function throwIfFrequentLocationsUnavailable(): void
    {
        if ($this->throwOnFrequentLocations) {
            throw new RuntimeException('frequent locations unavailable');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function frequentLocationSelect(string $qry, array $params): array
    {
        $this->throwIfFrequentLocationsUnavailable();

        $ownerId = (int) ($params[':ownerID'] ?? 0);
        $rows = array_values(array_filter(
            $this->frequentLocationRows,
            static fn (array $row): bool => (int) ($row['ownerID'] ?? 0) === $ownerId
        ));

        usort(
            $rows,
            static function (array $a, array $b): int {
                $used = ((int) ($b['lastUsed'] ?? 0)) <=> ((int) ($a['lastUsed'] ?? 0));
                if ($used !== 0) {
                    return $used;
                }

                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }
        );

        return $rows;
    }

    private function frequentLocationSelectSingle(string $qry, array $params, $field = false)
    {
        $rows = $this->frequentLocationSelect($qry, $params);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return $field === false ? false : false;
        }

        return $field === false ? $row : ($row[$field] ?? false);
    }

    private function frequentLocationInsert(string $qry, array $params): bool
    {
        $this->throwIfFrequentLocationsUnavailable();

        $ownerId = (int) $params[':ownerID'];
        $galaxy = (int) $params[':galaxy'];
        $system = (int) $params[':system'];
        $planet = (int) $params[':planet'];
        $type = (int) $params[':type'];
        $lastUsed = (int) ($params[':lastUsedUpdate'] ?? $params[':lastUsed'] ?? 0);

        foreach ($this->frequentLocationRows as $id => $row) {
            if ((int) $row['ownerID'] === $ownerId
                && (int) $row['galaxy'] === $galaxy
                && (int) $row['system'] === $system
                && (int) $row['planet'] === $planet
                && (int) $row['type'] === $type
            ) {
                $this->frequentLocationRows[$id]['lastUsed'] = $lastUsed;

                return true;
            }
        }

        $this->lastFrequentLocationId++;
        $id = $this->lastFrequentLocationId;
        $this->frequentLocationRows[$id] = [
            'id'       => $id,
            'ownerID'  => $ownerId,
            'galaxy'   => $galaxy,
            'system'   => $system,
            'planet'   => $planet,
            'type'     => $type,
            'lastUsed' => $lastUsed,
        ];

        return true;
    }

    private function frequentLocationUpdate(string $qry, array $params): bool
    {
        $this->throwIfFrequentLocationsUnavailable();

        return $this->frequentLocationInsert($qry, $params);
    }

    private function frequentLocationDelete(string $qry, array $params): bool
    {
        $this->throwIfFrequentLocationsUnavailable();

        $id = (int) ($params[':id'] ?? 0);
        $ownerId = (int) ($params[':ownerID'] ?? 0);
        if (isset($this->frequentLocationRows[$id])
            && (int) $this->frequentLocationRows[$id]['ownerID'] === $ownerId
        ) {
            unset($this->frequentLocationRows[$id]);
        }

        return true;
    }
}
