<?php

namespace HiveNova\Repository;

use HiveNova\Core\Database;

class UserRepository
{
    public static function getUserById(int $id): ?array
    {
        $db = Database::get();
        $row = $db->selectSingle(
            'SELECT * FROM %%USERS%% WHERE id = :id;',
            [':id' => $id]
        );
        return $row ?: null;
    }

    public static function getUserWithStats(int $id): ?array
    {
        $db = Database::get();
        $row = $db->selectSingle(
            'SELECT user.*, stat.stat_points
             FROM %%USERS%% as user
             LEFT JOIN %%STATPOINTS%% as stat ON stat.id_owner = user.id AND stat.stat_type = 1
             WHERE user.id = :id;',
            [':id' => $id]
        );
        return $row ?: null;
    }
}
