<?php

namespace HiveNova\Repository;

use HiveNova\Core\Database;

class PlanetRepository
{
    public static function getPlanetById(int $id): ?array
    {
        $db = Database::get();
        $row = $db->selectSingle(
            'SELECT * FROM %%PLANETS%% WHERE id = :id;',
            [':id' => $id]
        );
        return $row ?: null;
    }

    public static function getPlanetByCoords(int $gal, int $sys, int $planet, int $type, int $universe): ?array
    {
        $db = Database::get();
        $row = $db->selectSingle(
            'SELECT * FROM %%PLANETS%% WHERE universe = :universe AND galaxy = :galaxy AND `system` = :system AND planet = :planet AND planet_type = :type;',
            [
                ':universe' => $universe,
                ':galaxy'   => $gal,
                ':system'   => $sys,
                ':planet'   => $planet,
                ':type'     => $type,
            ]
        );
        return $row ?: null;
    }

    public static function getPlanetName(int $id): ?string
    {
        $db = Database::get();
        $name = $db->selectSingle(
            'SELECT name FROM %%PLANETS%% WHERE id = :id;',
            [':id' => $id],
            'name'
        );
        return $name !== false ? (string) $name : null;
    }
}
