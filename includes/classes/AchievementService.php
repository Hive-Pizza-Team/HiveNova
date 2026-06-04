<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;
use HiveNova\Core\Language;
use HiveNova\Core\PlayerUtil;

/**
 * Tracks achievement progress, unlocks, rewards, and celebration queue.
 */
class AchievementService
{
    private static ?self $instance = null;

    /** @var array<int, array<string, list<array>>> */
    private array $definitionsByTrigger = [];

    private int $definitionsUniverse = 0;

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    public static function record(int $userId, string $eventType, array $payload = [], bool $celebrate = true): array
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS) || !self::isSchemaReady()) {
            return [];
        }

        return self::get()->processEvent($userId, $eventType, $payload, $celebrate);
    }

    public static function evaluateSnapshot(int $userId, bool $celebrate = false): array
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS) || !self::isSchemaReady()) {
            return [];
        }

        return self::get()->processSnapshot($userId, $celebrate);
    }

    /**
     * @param list<int|string> $userIds
     */
    public static function recordCombatAfterBattle(array $userIds, bool $won): void
    {
        $service = self::get();
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($won) {
                $service->processEvent($userId, 'combat_wins', [], true);
            }
            $service->processEvent($userId, 'units_destroyed', [], true);
            $user = $service->loadUserPublic($userId);
            if ($user !== null) {
                $totalFights = (int) $user['wons'] + (int) $user['loos'] + (int) $user['draws'];
                $winRate = $totalFights > 0 ? ((int) $user['wons'] / $totalFights) * 100 : 0;
                $service->processEvent($userId, 'combat_win_rate', ['win_rate' => $winRate], true);
            }
        }
    }

    /**
     * @param array<int, int|float> $builded
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     */
    public static function recordBuildCompleted(int $userId, array $builded, array $user, array $planet): void
    {
        global $resource, $reslist;

        $service = self::get();
        foreach ($builded as $elementId => $count) {
            if (empty($count) || empty($resource[$elementId])) {
                continue;
            }
            $column = $resource[$elementId];
            $level = isset($planet[$column]) ? (int) $planet[$column] : (int) ($user[$column] ?? 0);
            $service->processEvent($userId, 'element_level', [
                'element_id' => (int) $elementId,
                'level'      => $level,
            ], true);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadUserPublic(int $userId): ?array
    {
        return $this->loadUser($userId);
    }

    public function clearDefinitionCache(): void
    {
        $this->definitionsByTrigger = [];
        $this->definitionsUniverse = 0;
    }

    public function loadDefinitions(int $universe): void
    {
        if ($this->definitionsUniverse === $universe && !empty($this->definitionsByTrigger)) {
            return;
        }

        $this->definitionsByTrigger = [];
        $this->definitionsUniverse = $universe;

        $rows = Database::get()->select(
            'SELECT * FROM %%ACHIEVEMENTS%% WHERE universe = :universe AND active = 1 ORDER BY sort_order ASC, id ASC;',
            [':universe' => $universe]
        );

        foreach ($rows as $row) {
            $row['trigger_params'] = $this->decodeParams($row['trigger_params']);
            $this->definitionsByTrigger[$row['trigger_type']][] = $row;
        }
    }

    /**
     * @return list<int> Newly unlocked achievement IDs
     */
    public function processEvent(int $userId, string $eventType, array $payload, bool $celebrate): array
    {
        $user = $this->loadUser($userId);
        if ($user === null) {
            return [];
        }

        $this->loadDefinitions((int) $user['universe']);
        if (empty($this->definitionsByTrigger[$eventType])) {
            return [];
        }

        $unlocked = [];
        foreach ($this->definitionsByTrigger[$eventType] as $achievement) {
            $value = $this->resolveEventValue($eventType, $payload, $user, $achievement);
            if ($value === null) {
                continue;
            }
            $newId = $this->applyProgress($user, $achievement, $value, $celebrate);
            if ($newId !== null) {
                $unlocked[] = $newId;
            }
        }

        return $unlocked;
    }

    /**
     * @return list<int>
     */
    public function processSnapshot(int $userId, bool $celebrate): array
    {
        $user = $this->loadUser($userId);
        if ($user === null) {
            return [];
        }

        $snapshot = $this->buildSnapshot($user);
        $this->loadDefinitions((int) $user['universe']);

        $unlocked = [];
        foreach ($this->definitionsByTrigger as $triggerType => $achievements) {
            foreach ($achievements as $achievement) {
                $value = $this->resolveSnapshotValue($triggerType, $snapshot, $user, $achievement);
                if ($value === null) {
                    continue;
                }
                $newId = $this->applyProgress($user, $achievement, $value, $celebrate);
                if ($newId !== null) {
                    $unlocked[] = $newId;
                }
            }
        }

        return $unlocked;
    }

    public static function isSchemaReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $version = (int) Database::get()->selectSingle(
                'SELECT dbVersion FROM %%SYSTEM%% LIMIT 1;',
                [],
                'dbVersion'
            );
            if ($version < 19) {
                $ready = false;
            } else {
                $tables = Database::get()->nativeQuery(
                    "SHOW TABLES LIKE '" . DB_PREFIX . "achievements'"
                );
                $ready = !empty($tables);
            }
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    public function getPendingCelebrations(int $userId): array
    {
        if (!self::isSchemaReady()) {
            return [];
        }

        $sql = 'SELECT a.id, a.`key`, a.category, a.name_key, a.desc_key, a.reward_type, a.reward_amount,
                a.celebration_tier, a.points, ua.unlocked_at
            FROM %%USER_ACHIEVEMENTS%% ua
            INNER JOIN %%ACHIEVEMENTS%% a ON a.id = ua.achievement_id
            WHERE ua.user_id = :userId AND ua.celebrated = 0
            ORDER BY ua.unlocked_at ASC, a.sort_order ASC;';

        return Database::get()->select($sql, [':userId' => $userId]);
    }

    public function markCelebrated(int $userId, int $achievementId): void
    {
        Database::get()->update(
            'UPDATE %%USER_ACHIEVEMENTS%% SET celebrated = 1
            WHERE user_id = :userId AND achievement_id = :achievementId;',
            [
                ':userId'         => $userId,
                ':achievementId'  => $achievementId,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadUser(int $userId): ?array
    {
        return Database::get()->selectSingle(
            'SELECT * FROM %%USERS%% WHERE id = :userId;',
            [':userId' => $userId]
        ) ?: null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function buildSnapshot(array $user): array
    {
        global $resource, $reslist;

        $db = Database::get();
        $userId = (int) $user['id'];
        $universe = (int) $user['universe'];

        $stats = $db->selectSingle(
            'SELECT total_points, fleet_points, tech_points, build_points, defs_points
            FROM %%STATPOINTS%% WHERE id_owner = :userId AND stat_type = 1;',
            [':userId' => $userId]
        ) ?: [];

        $planetCount = (int) $db->selectSingle(
            'SELECT COUNT(*) FROM %%PLANETS%% WHERE id_owner = :userId AND planet_type = 1 AND universe = :universe;',
            [':userId' => $userId, ':universe' => $universe],
            'COUNT(*)'
        );

        $expeditionCount = (int) $db->selectSingle(
            'SELECT COUNT(*) FROM %%LOG_FLEETS%% WHERE fleet_owner = :userId AND fleet_mission = 15;',
            [':userId' => $userId],
            'COUNT(*)'
        );

        $elementLevels = [];
        if (!empty($reslist['build'])) {
            $cols = [];
            foreach ($reslist['build'] as $elementId) {
                if (!empty($resource[$elementId])) {
                    $cols[] = 'MAX(' . $resource[$elementId] . ') AS e' . $elementId;
                }
            }
            if ($cols !== []) {
                $row = $db->selectSingle(
                    'SELECT ' . implode(', ', $cols) . ' FROM %%PLANETS%% WHERE id_owner = :userId;',
                    [':userId' => $userId]
                );
                if (is_array($row)) {
                    foreach ($reslist['build'] as $elementId) {
                        $elementLevels[$elementId] = (int) ($row['e' . $elementId] ?? 0);
                    }
                }
            }
        }

        if (!empty($reslist['tech'])) {
            foreach ($reslist['tech'] as $elementId) {
                if (!empty($resource[$elementId])) {
                    $elementLevels[$elementId] = (int) ($user[$resource[$elementId]] ?? 0);
                }
            }
        }

        $totalFights = (int) $user['wons'] + (int) $user['loos'] + (int) $user['draws'];
        $winRate = $totalFights > 0 ? ((int) $user['wons'] / $totalFights) * 100 : 0;

        return [
            'wons'              => (int) $user['wons'],
            'desunits'          => (int) $user['desunits'],
            'total_points'      => (int) ($stats['total_points'] ?? 0),
            'fleet_points'      => (int) ($stats['fleet_points'] ?? 0),
            'planet_count'      => $planetCount,
            'expedition_count'  => $expeditionCount,
            'ally_id'           => (int) $user['ally_id'],
            'hive_valid'        => PlayerUtil::isHiveAccountValid($user['hive_account'] ?? '') ? 1 : 0,
            'account_age_days'  => (int) floor((TIMESTAMP - (int) $user['register_time']) / 86400),
            'win_rate'          => $winRate,
            'total_fights'      => $totalFights,
            'element_levels'    => $elementLevels,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     * @param array<string, mixed> $achievement
     */
    private function resolveEventValue(string $triggerType, array $payload, array $user, array $achievement): ?int
    {
        $params = $achievement['trigger_params'];

        return match ($triggerType) {
            'combat_wins' => isset($payload['total']) ? (int) $payload['total'] : (int) $user['wons'],
            'units_destroyed' => isset($payload['total']) ? (int) $payload['total'] : (int) $user['desunits'],
            'element_level' => isset($payload['element_id'], $payload['level'])
                && (int) $payload['element_id'] === (int) ($params['element_id'] ?? 0)
                ? (int) $payload['level'] : null,
            'planet_count' => isset($payload['total']) ? (int) $payload['total'] : $this->countPlanets((int) $user['id'], (int) $user['universe']),
            'stat_points' => isset($payload['value']) ? (int) $payload['value'] : null,
            'expedition_count' => isset($payload['total']) ? (int) $payload['total'] : $this->countExpeditions((int) $user['id']),
            'ally_joined' => !empty($payload['joined']) || (int) $user['ally_id'] > 0 ? 1 : null,
            'hive_account_valid' => !empty($payload['valid']) ? 1 : null,
            'account_age_days' => isset($payload['days']) ? (int) $payload['days'] : null,
            'combat_win_rate' => isset($payload['win_rate']) ? (int) floor($payload['win_rate']) : null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $user
     * @param array<string, mixed> $achievement
     */
    private function resolveSnapshotValue(string $triggerType, array $snapshot, array $user, array $achievement): ?int
    {
        $params = $achievement['trigger_params'];

        return match ($triggerType) {
            'combat_wins' => $snapshot['wons'],
            'units_destroyed' => $snapshot['desunits'],
            'element_level' => $snapshot['element_levels'][(int) ($params['element_id'] ?? 0)] ?? 0,
            'planet_count' => $snapshot['planet_count'],
            'stat_points' => match ($params['stat'] ?? 'total') {
                'fleet' => $snapshot['fleet_points'],
                default => $snapshot['total_points'],
            },
            'expedition_count' => $snapshot['expedition_count'],
            'ally_joined' => $snapshot['ally_id'] > 0 ? 1 : 0,
            'hive_account_valid' => $snapshot['hive_valid'],
            'account_age_days' => $snapshot['account_age_days'],
            'combat_win_rate' => $snapshot['total_fights'] >= (int) ($params['min_fights'] ?? 1)
                ? (int) floor($snapshot['win_rate']) : null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $achievement
     */
    private function applyProgress(array $user, array $achievement, int $value, bool $celebrate): ?int
    {
        $threshold = (int) ($achievement['trigger_params']['threshold'] ?? 1);
        $userId = (int) $user['id'];
        $achievementId = (int) $achievement['id'];

        if ($this->isUnlocked($userId, $achievementId)) {
            return null;
        }

        $db = Database::get();
        $db->insert(
            'INSERT INTO %%USER_ACHIEVEMENT_PROGRESS%% (user_id, achievement_id, progress, updated_at)
            VALUES (:userId, :achievementId, :progress, :time)
            ON DUPLICATE KEY UPDATE progress = :progress, updated_at = :time;',
            [
                ':userId'         => $userId,
                ':achievementId'  => $achievementId,
                ':progress'       => $value,
                ':time'           => TIMESTAMP,
            ]
        );

        if ($value < $threshold) {
            return null;
        }

        return $this->unlock($user, $achievement, $celebrate);
    }

    private function isUnlocked(int $userId, int $achievementId): bool
    {
        return (bool) Database::get()->selectSingle(
            'SELECT 1 FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => $achievementId],
            '1'
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $achievement
     */
    private function unlock(array $user, array $achievement, bool $celebrate): ?int
    {
        $userId = (int) $user['id'];
        $achievementId = (int) $achievement['id'];

        if ($this->isUnlocked($userId, $achievementId)) {
            return null;
        }

        Database::get()->insert(
            'INSERT INTO %%USER_ACHIEVEMENTS%% (user_id, achievement_id, unlocked_at, celebrated)
            VALUES (:userId, :achievementId, :time, :celebrated);',
            [
                ':userId'         => $userId,
                ':achievementId'  => $achievementId,
                ':time'           => TIMESTAMP,
                ':celebrated'     => $celebrate ? 0 : 1,
            ]
        );

        $this->applyReward($user, $achievement);
        $this->notifyUnlock($user, $achievement, $celebrate);

        return $achievementId;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $achievement
     */
    private function applyReward(array $user, array $achievement): void
    {
        $rewardType = $achievement['reward_type'];
        $amount = (float) $achievement['reward_amount'];

        if ($rewardType === 'none' || $amount <= 0) {
            return;
        }

        $userId = (int) $user['id'];
        $db = Database::get();

        if ($rewardType === 'darkmatter') {
            $db->update(
                'UPDATE %%USERS%% SET darkmatter = darkmatter + :amount WHERE id = :userId;',
                [':amount' => $amount, ':userId' => $userId]
            );
            $db->insert(
                'INSERT INTO %%DM_TRANSACTIONS%% SET timestamp = NOW(), user_id = :userId,
                amount_received = :amount, memo = :memo;',
                [
                    ':userId'  => $userId,
                    ':amount'  => $amount,
                    ':memo'    => 'achievement:' . $achievement['key'],
                ]
            );
        }

        $db->insert(
            'INSERT INTO %%ACHIEVEMENT_GRANTS%% (user_id, achievement_id, reward_type, reward_amount, granted_at)
            VALUES (:userId, :achievementId, :rewardType, :amount, :time);',
            [
                ':userId'         => $userId,
                ':achievementId'  => (int) $achievement['id'],
                ':rewardType'     => $rewardType,
                ':amount'         => $amount,
                ':time'           => TIMESTAMP,
            ]
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $achievement
     */
    private function notifyUnlock(array $user, array $achievement, bool $celebrate): void
    {
        $lang = new Language($user['lang']);
        $lang->includeData(['L18N', 'INGAME', 'ACHIEVEMENTS', 'TECH', 'CUSTOM']);
        $LNG = $lang;

        $name = $LNG[$achievement['name_key']] ?? $achievement['name_key'];
        $subject = sprintf($LNG['ach_unlock_subject'] ?? 'Achievement unlocked: %s', $name);
        $body = sprintf(
            $LNG['ach_unlock_body'] ?? 'Congratulations! You unlocked "%s".%s',
            $name,
            $achievement['reward_type'] !== 'none' && $achievement['reward_amount'] > 0
                ? ' ' . sprintf($LNG['ach_unlock_reward'] ?? 'Reward: %s %s', pretty_number($achievement['reward_amount']), $LNG['tech'][921] ?? '')
                : ''
        );

        PlayerUtil::sendMessage(
            (int) $user['id'],
            0,
            $LNG['ach_unlock_from'] ?? 'Achievements',
            4,
            $subject,
            $body,
            TIMESTAMP,
            null,
            1,
            (int) $user['universe']
        );
    }

    private function countPlanets(int $userId, int $universe): int
    {
        return (int) Database::get()->selectSingle(
            'SELECT COUNT(*) FROM %%PLANETS%% WHERE id_owner = :userId AND planet_type = 1 AND universe = :universe;',
            [':userId' => $userId, ':universe' => $universe],
            'COUNT(*)'
        );
    }

    private function countExpeditions(int $userId): int
    {
        return (int) Database::get()->selectSingle(
            'SELECT COUNT(*) FROM %%LOG_FLEETS%% WHERE fleet_owner = :userId AND fleet_mission = 15;',
            [':userId' => $userId],
            'COUNT(*)'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeParams(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * List achievements with progress for UI.
     *
     * @return list<array<string, mixed>>
     */
    public function getAchievementsForUser(int $userId, int $universe): array
    {
        global $LNG;

        $this->loadDefinitions($universe);

        $sql = 'SELECT a.*, COALESCE(p.progress, 0) AS progress,
                (ua.achievement_id IS NOT NULL) AS unlocked, ua.unlocked_at
            FROM %%ACHIEVEMENTS%% a
            LEFT JOIN %%USER_ACHIEVEMENT_PROGRESS%% p ON p.achievement_id = a.id AND p.user_id = :userId
            LEFT JOIN %%USER_ACHIEVEMENTS%% ua ON ua.achievement_id = a.id AND ua.user_id = :userId
            WHERE a.universe = :universe AND a.active = 1
            ORDER BY a.category ASC, a.sort_order ASC, a.id ASC;';

        $rows = Database::get()->select($sql, [
            ':userId'   => $userId,
            ':universe' => $universe,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $params = $this->decodeParams($row['trigger_params']);
            $threshold = (int) ($params['threshold'] ?? 1);
            $hidden = (int) $row['hidden'] === 1 && !(int) $row['unlocked'];

            $result[] = [
                'id'                => (int) $row['id'],
                'key'               => $row['key'],
                'category'          => $row['category'],
                'name'              => $hidden ? ($LNG['ach_hidden_name'] ?? '???') : ($LNG[$row['name_key']] ?? $row['name_key']),
                'description'       => $hidden ? ($LNG['ach_hidden_desc'] ?? '') : ($LNG[$row['desc_key']] ?? $row['desc_key']),
                'progress'          => (int) $row['progress'],
                'threshold'         => $threshold,
                'unlocked'          => (bool) $row['unlocked'],
                'unlocked_at'       => (int) ($row['unlocked_at'] ?? 0),
                'reward_type'       => $row['reward_type'],
                'reward_amount'     => (float) $row['reward_amount'],
                'points'            => (int) $row['points'],
                'celebration_tier'  => $row['celebration_tier'],
                'hidden'            => $hidden,
            ];
        }

        return $result;
    }
}
