<?php

use HiveNova\Core\DatabaseInterface;

/**
 * In-memory stub for AchievementService unit tests.
 */
class FakeAchievementDatabase implements DatabaseInterface
{
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

    public bool $schemaReady = true;

    public function select($qry, array $params = array())
    {
        if (str_contains($qry, 'FROM %%ACHIEVEMENTS%%') && str_contains($qry, 'active = 1')) {
            return [[
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

        if (str_contains($qry, 'USER_ACHIEVEMENTS%% ua') && str_contains($qry, 'celebrated = 0')) {
            return [];
        }

        if (str_contains($qry, 'FROM %%ACHIEVEMENTS%% a') && str_contains($qry, 'getAchievementsForUser')) {
            return [];
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
            $userId = (int) ($params[':userId'] ?? 0);
            return $this->users[$userId] ?? null;
        }

        if (str_contains($qry, 'FROM %%USER_ACHIEVEMENTS%%') && str_contains($qry, 'SELECT 1')) {
            $key = ($params[':userId'] ?? 0) . ':' . ($params[':achievementId'] ?? 0);
            return isset($this->unlocked[$key]) ? '1' : null;
        }

        if (str_contains($qry, 'progress FROM %%USER_ACHIEVEMENT_PROGRESS%%')) {
            $key = ($params[':userId'] ?? 0) . ':' . ($params[':achievementId'] ?? 0);
            return $this->progress[$key] ?? 0;
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

        if (str_contains($qry, 'DM_TRANSACTIONS%%')) {
            // tracked via grants path for DM
        }

        if (str_contains($qry, 'MESSAGES%%')) {
            $this->messages[] = $params;
        }

        return true;
    }

    public function update($qry, array $params = array())
    {
        if (str_contains($qry, 'darkmatter')) {
            $this->darkmatter += (float) $params[':amount'];
        }

        if (str_contains($qry, 'celebrated = 1')) {
            // mark celebrated — no-op for stub
        }

        return true;
    }

    public function delete($qry, array $params = array()) { return true; }
    public function replace($qry, array $params = array()) { return true; }
    public function query($qry) { return true; }
    public function nativeQuery($qry) { return $this->schemaReady ? [['uni1_achievements']] : []; }
    public function lastInsertId() { return 1; }
    public function rowCount() { return 1; }
    public function getQueryCounter() { return 0; }
    public function quote($str) { return "'" . addslashes((string) $str) . "'"; }
    public function disconnect() {}
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollback(): void {}
}
