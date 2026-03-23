<?php

namespace HiveNova\Repository;

use HiveNova\Core\Database;

class BuddyRepository
{
    public static function isBuddy(int $userId, int $friendId): bool
    {
        $db = Database::get();
        $count = $db->selectSingle(
            'SELECT COUNT(*) as c FROM %%BUDDY%% WHERE (sender = :userId AND owner = :friendId) OR (owner = :userId AND sender = :friendId);',
            [':userId' => $userId, ':friendId' => $friendId],
            'c'
        );
        return (int) $count > 0;
    }

    public static function getBuddyList(int $userId): array
    {
        $db = Database::get();
        return $db->select(
            'SELECT a.sender, a.id as buddyid, b.id, b.username, b.onlinetime, b.galaxy, b.system, b.planet, b.ally_id, c.ally_name, d.text
             FROM (%%BUDDY%% as a, %%USERS%% as b) LEFT JOIN %%ALLIANCE%% as c ON c.id = b.ally_id LEFT JOIN %%BUDDY_REQUEST%% as d ON a.id = d.id
             WHERE (a.sender = :userId AND a.owner = b.id) OR (a.owner = :userId AND a.sender = b.id);',
            [':userId' => $userId]
        );
    }
}
