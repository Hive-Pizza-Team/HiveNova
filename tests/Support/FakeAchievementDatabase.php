<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory stub for AchievementService and related unit tests.
 */
class FakeAchievementDatabase implements DatabaseInterface
{
    /** @var list<array<string, mixed>> */
    public array $achievementDefinitions = [];

    public array $users = [];

    /** @var array<int, true> */
    public array $unlocked = [];

    /** @var array<string, int> progress key "userId:achievementId" */
    public array $progress = [];

    public float $darkmatter = 0;

    /** @var list<array<string, mixed>> */
    public array $grants = [];

    /** @var list<array<string, mixed>> */
    public array $messages = [];

    /** @var list<array<string, mixed>> */
    public array $pendingCelebrationRows = [];

    /** @var list<array<string, mixed>> */
    public array $badgeRows = [];

    /** @var list<array{id: int}> */
    public array $cronUserBatch = [];

    public ?int $allianceRequestUserId = null;

    public bool $schemaReady = true;

    public int $planetCount = 1;

    public int $expeditionCount = 0;

    /** @var array<string, int|string> */
    public array $statPoints = [
        'total_points' => 0,
        'fleet_points' => 0,
        'tech_points'  => 0,
        'build_points' => 0,
        'defs_points'  => 0,
    ];

    /** @var array<string, int> */
    public array $planetMaxLevels = [];

    /** @var list<string> */
    public array $planetColumns = ['metal_mine', 'crystal_mine', 'solar_plant'];

    public function __construct()
    {
        $this->achievementDefinitions = [[
            'id'               => 1,
            'key'              => 'combat_first_win',
            'category'         => 'combat',
            'name_key'         => 'ach_combat_first_win_name',
            'desc_key'         => 'ach_combat_first_win_desc',
            'trigger_type'     => 'combat_wins',
            'trigger_params'   => '{"threshold":1}',
            'reward_type'      => 'darkmatter',
            'reward_amount'    => 100,
            'points'           => 10,
            'celebration_tier' => 'normal',
            'hidden'           => 0,
            'active'           => 1,
            'universe'         => 1,
        ]];
    }

    public function addAchievement(array $row): void
    {
        $this->achievementDefinitions[] = $row;
    }

    public function select($qry, array $params = array())
    {
        if (str_contains($qry, 'FROM %%ACHIEVEMENTS%%') && str_contains($qry, 'active = 1') && !str_contains($qry, 'LEFT JOIN')) {
            return $this->achievementDefinitions;
        }

        if (str_contains($qry, 'FROM %%ACHIEVEMENTS%% a') && str_contains($qry, 'LEFT JOIN')) {
            $userId = (int) ($params[':userId'] ?? 0);
            $rows = [];
            foreach ($this->achievementDefinitions as $def) {
                $aid = (int) $def['id'];
                $key = $userId . ':' . $aid;
                $rows[] = array_merge($def, [
                    'progress'    => $this->progress[$key] ?? 0,
                    'unlocked'    => isset($this->unlocked[$key]) ? 1 : 0,
                    'unlocked_at' => isset($this->unlocked[$key]) ? TIMESTAMP : 0,
                ]);
            }
            return $rows;
        }

        if (str_contains($qry, 'celebrated = 0')) {
            return $this->pendingCelebrationRows;
        }

        if (str_contains($qry, 'USER_ACHIEVEMENTS%% ua') && str_contains($qry, 'ORDER BY a.points')) {
            return $this->badgeRows;
        }

        if (str_contains($qry, 'FROM %%USERS%% WHERE id >') && str_contains($qry, 'LIMIT')) {
            return $this->cronUserBatch;
        }

        return [];
    }

    public function selectSingle($qry, array $params = array(), $field = false)
    {
        if (str_contains($qry, 'dbVersion')) {
            return $this->schemaReady ? 19 : 0;
        }

        if (str_contains($qry, 'SHOW TABLES')) {
            return $this->schemaReady ? 'uni1_achievements' : null;
        }

        if (str_contains($qry, 'FROM %%USERS%%') && str_contains($qry, 'WHERE id')) {
            $userId = (int) ($params[':userId'] ?? $params[':id'] ?? 0);
            $row = $this->users[$userId] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }
            if ($field === 'lang') {
                return $row['lang'] ?? 'en';
            }
            if ($field !== false) {
                return $row[$field] ?? false;
            }
            return $row;
        }

        if (str_contains($qry, 'FROM %%USER_ACHIEVEMENTS%%') && str_contains($qry, 'SELECT 1')) {
            $key = ($params[':userId'] ?? 0) . ':' . ($params[':achievementId'] ?? 0);
            return isset($this->unlocked[$key]) ? '1' : null;
        }

        if (str_contains($qry, 'progress FROM %%USER_ACHIEVEMENT_PROGRESS%%')) {
            $key = ($params[':userId'] ?? 0) . ':' . ($params[':achievementId'] ?? 0);
            return $this->progress[$key] ?? 0;
        }

        if (str_contains($qry, 'FROM %%STATPOINTS%%')) {
            if (str_contains($qry, 'MAX(') && ($field === 'total' || $field === false)) {
                $total = (int) ($this->statPoints['total_points'] ?? 0);
                return $field === 'total' ? $total : ['total' => $total];
            }
            if ($field !== false) {
                return $this->statPoints[$field] ?? false;
            }
            return $this->statPoints;
        }

        if (str_contains($qry, 'planet_type = 1') && str_contains($qry, 'COUNT(*)')) {
            return $this->planetCount;
        }

        if (str_contains($qry, 'fleet_mission = 15')) {
            return $this->expeditionCount;
        }

        if (str_contains($qry, 'MAX(') && str_contains($qry, 'FROM %%PLANETS%%')) {
            return $this->planetMaxLevels;
        }

        if (str_contains($qry, 'COUNT(*) FROM %%ALLIANCE%%')) {
            return 0;
        }

        if (str_contains($qry, 'ALLIANCE_REQUEST%%') && str_contains($qry, 'userId')) {
            return $this->allianceRequestUserId;
        }

        return null;
    }

    public function insert($qry, array $params = array())
    {
        if (str_contains($qry, 'USER_ACHIEVEMENT_PROGRESS%%')) {
            $key = $params[':userId'] . ':' . $params[':achievementId'];
            $this->progress[$key] = (int) $params[':progress'];
        }

        if (str_contains($qry, 'USER_ACHIEVEMENTS%%')) {
            $key = $params[':userId'] . ':' . $params[':achievementId'];
            $this->unlocked[$key] = true;
        }

        if (str_contains($qry, 'ACHIEVEMENT_GRANTS%%')) {
            $this->grants[] = $params;
        }

        if (str_contains($qry, '%%MESSAGES%%')) {
            $this->messages[] = $params;
        }

        if (str_contains($qry, 'INSERT INTO %%ALLIANCE%%')) {
            return true;
        }

        return true;
    }

    public function update($qry, array $params = array())
    {
        if (str_contains($qry, 'darkmatter')) {
            $this->darkmatter += (float) ($params[':amount'] ?? 0);
        }

        if (str_contains($qry, 'celebrated = 1')) {
            return true;
        }

        if (str_contains($qry, 'CRONJOBS%%')) {
            return true;
        }

        if (str_contains($qry, 'UPDATE %%PLANETS%% as p,%%USERS%% as u')) {
            return true;
        }

        return true;
    }

    public function delete($qry, array $params = array()) { return true; }
    public function replace($qry, array $params = array()) { return true; }
    public function query($qry) { return true; }

    public function nativeQuery($qry)
    {
        if (str_contains($qry, 'SHOW TABLES')) {
            return $this->schemaReady ? [['uni1_achievements']] : [];
        }

        if (str_contains($qry, 'SHOW COLUMNS FROM %%PLANETS%%')) {
            return array_map(static fn (string $col) => ['Field' => $col], $this->planetColumns);
        }

        return [];
    }

    public function lastInsertId() { return 99; }
    public function rowCount() { return 1; }
    public function getQueryCounter() { return 0; }
    public function quote($str) { return "'" . addslashes((string) $str) . "'"; }
    public function disconnect() {}
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollback(): void {}
}
