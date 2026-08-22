<?php

namespace HiveNova\Core;

use Throwable;

class FeatService
{
    public static function tryClaim(int $universe, string $featKey, int $userId, int $at = 0): bool
    {
        if ($userId <= 0 || !in_array($featKey, FeatCatalog::keys(), true)) {
            return false;
        }

        $at = $at > 0 ? $at : (defined('TIMESTAMP') ? TIMESTAMP : time());

        try {
            $db = Database::get();
            self::ensureSeeded($universe);
            $state = $db->selectSingle(
                'SELECT status FROM %%FEAT_STATES%% WHERE universe = :universe AND feat_key = :featKey;',
                [':universe' => $universe, ':featKey' => $featKey],
                'status'
            );

            if ($state !== FeatCatalog::STATUS_OPEN) {
                return false;
            }

            try {
                $db->insert(
                    'INSERT INTO %%FEAT_CLAIMS%% (universe, feat_key, user_id, claimed_at)
                    VALUES (:universe, :featKey, :userId, :at);',
                    [
                        ':universe' => $universe,
                        ':featKey'  => $featKey,
                        ':userId'   => $userId,
                        ':at'       => $at,
                    ]
                );
            } catch (Throwable) {
                return false;
            }

            $db->update(
                'UPDATE %%FEAT_STATES%% SET status = :status, winner_id = :userId, claimed_at = :at
                WHERE universe = :universe AND feat_key = :featKey AND status = :open;',
                [
                    ':status'   => FeatCatalog::STATUS_CLAIMED,
                    ':userId'   => $userId,
                    ':at'       => $at,
                    ':universe' => $universe,
                    ':featKey'  => $featKey,
                    ':open'     => FeatCatalog::STATUS_OPEN,
                ]
            );

            self::unlockHofAchievement($universe, $featKey, $userId, $at);
            self::broadcast($universe, $featKey, $userId, $at);

            return true;
        } catch (Throwable $e) {
            error_log('FeatService::tryClaim: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForUniverse(int $universe): array
    {
        self::ensureSeeded($universe);
        $rows = Database::get()->select(
            'SELECT s.feat_key, s.status, s.winner_id, s.claimed_at, u.username
            FROM %%FEAT_STATES%% s
            LEFT JOIN %%USERS%% u ON u.id = s.winner_id
            WHERE s.universe = :universe;',
            [':universe' => $universe]
        );

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['feat_key']] = $row;
        }

        $list = [];
        foreach (FeatCatalog::keys() as $key) {
            $row = $byKey[$key] ?? null;
            $def = FeatCatalog::definition($key);
            $list[] = [
                'feat_key'   => $key,
                'status'     => $row['status'] ?? FeatCatalog::STATUS_UNKNOWN,
                'winner_id'  => isset($row['winner_id']) ? (int) $row['winner_id'] : 0,
                'username'   => $row['username'] ?? '',
                'claimed_at' => isset($row['claimed_at']) ? (int) $row['claimed_at'] : 0,
                'name_key'   => $def['name_key'],
                'desc_key'   => $def['desc_key'],
            ];
        }

        return $list;
    }

    public static function ensureSeeded(int $universe): void
    {
        $count = (int) Database::get()->selectSingle(
            'SELECT COUNT(*) FROM %%FEAT_STATES%% WHERE universe = :universe;',
            [':universe' => $universe],
            'COUNT(*)'
        );
        if ($count > 0) {
            return;
        }

        $fromStart = false;
        try {
            $fromStart = (int) Config::get($universe)->feat_tracking_from_start === 1;
        } catch (Throwable) {
            $fromStart = false;
        }
        self::seedUniverse($universe, $fromStart);
    }

    public static function seedUniverse(int $universe, bool $trackingFromStart): void
    {
        $db = Database::get();
        $hasGraviton = false;
        $hasHyperspace = false;
        $hasMoon = false;

        if (!$trackingFromStart) {
            $hasGraviton = (int) $db->selectSingle(
                'SELECT COUNT(*) FROM %%USERS%% WHERE universe = :universe AND graviton_tech > 0;',
                [':universe' => $universe],
                'COUNT(*)'
            ) > 0;
            $hasHyperspace = (int) $db->selectSingle(
                'SELECT COUNT(*) FROM %%USERS%% WHERE universe = :universe AND hyperspace_tech > 0;',
                [':universe' => $universe],
                'COUNT(*)'
            ) > 0;
            $hasMoon = (int) $db->selectSingle(
                'SELECT COUNT(*) FROM %%PLANETS%% WHERE universe = :universe AND planet_type = 3;',
                [':universe' => $universe],
                'COUNT(*)'
            ) > 0;
        }

        foreach (FeatCatalog::keys() as $key) {
            $status = FeatCatalog::initialStatus(
                $key,
                $trackingFromStart,
                $hasGraviton,
                $hasHyperspace,
                $hasMoon
            );
            $def = FeatCatalog::definition($key);
            $db->insert(
                'INSERT INTO %%ACHIEVEMENTS%% (universe, `key`, category, name_key, desc_key, trigger_type, trigger_params,
                sort_order, hidden, active, reward_type, reward_amount, points, celebration_tier, hof_only)
                VALUES (:universe, :key, :category, :nameKey, :descKey, :triggerType, :params,
                :sortOrder, 0, 1, :rewardType, 0, 0, :tier, 1)
                ON DUPLICATE KEY UPDATE hof_only = 1, points = 0, reward_type = :rewardType;',
                [
                    ':universe'    => $universe,
                    ':key'         => $key,
                    ':category'    => $def['category'],
                    ':nameKey'     => $def['name_key'],
                    ':descKey'     => $def['desc_key'],
                    ':triggerType' => 'universe_first',
                    ':params'      => '{"feat_key":"' . $key . '"}',
                    ':sortOrder'   => $def['sort_order'],
                    ':rewardType'  => 'none',
                    ':tier'        => 'normal',
                ]
            );
            $db->insert(
                'INSERT INTO %%FEAT_STATES%% (universe, feat_key, status, winner_id, claimed_at)
                VALUES (:universe, :featKey, :status, 0, 0)
                ON DUPLICATE KEY UPDATE status = status;',
                [
                    ':universe' => $universe,
                    ':featKey'  => $key,
                    ':status'   => $status,
                ]
            );
        }
    }

    private static function unlockHofAchievement(int $universe, string $featKey, int $userId, int $at): void
    {
        $row = Database::get()->selectSingle(
            'SELECT id FROM %%ACHIEVEMENTS%% WHERE universe = :universe AND `key` = :key;',
            [':universe' => $universe, ':key' => $featKey]
        );
        if (empty($row['id'])) {
            return;
        }

        try {
            Database::get()->insert(
                'INSERT INTO %%USER_ACHIEVEMENTS%% (user_id, achievement_id, unlocked_at, celebrated)
                VALUES (:userId, :achievementId, :time, 1);',
                [
                    ':userId'         => $userId,
                    ':achievementId'  => (int) $row['id'],
                    ':time'           => $at,
                ]
            );
        } catch (Throwable) {
            // already unlocked
        }
    }

    private static function broadcast(int $universe, string $featKey, int $userId, int $at): void
    {
        try {
            $config = Config::get($universe);
            $config->feat_banner_key = $featKey;
            $config->feat_banner_user_id = $userId;
            $config->feat_banner_at = $at;
            $config->save();
        } catch (Throwable $e) {
            error_log('FeatService banner: ' . $e->getMessage());
        }

        try {
            self::inboxUniverse($universe, $featKey, $userId, $at);
        } catch (Throwable $e) {
            error_log('FeatService inbox: ' . $e->getMessage());
        }

        try {
            EventFirehoseWriter::recordFeat($universe, $at);
        } catch (Throwable $e) {
            error_log('FeatService feed: ' . $e->getMessage());
        }

        try {
            DiscordWebhookService::notifyFeatClaimed($universe, $featKey, $userId);
        } catch (Throwable $e) {
            error_log('FeatService discord: ' . $e->getMessage());
        }
    }

    private static function inboxUniverse(int $universe, string $featKey, int $userId, int $at): void
    {
        $winner = Database::get()->selectSingle(
            'SELECT username FROM %%USERS%% WHERE id = :id;',
            [':id' => $userId],
            'username'
        );
        $username = is_string($winner) && $winner !== '' ? $winner : '#' . $userId;

        $db = Database::get();
        $users = $db->select(
            'SELECT id, lang FROM %%USERS%% WHERE universe = :universe;',
            [':universe' => $universe]
        );

        foreach ($users as $user) {
            $lang = new Language($user['lang'] ?: 'en');
            $lang->includeData(['INGAME']);
            $featName = $lang[$featKey . '_name'] ?? $featKey;
            $subject = $lang['feat_inbox_subject'] ?? 'Feat of Strength';
            $body = sprintf(
                $lang['feat_inbox_body'] ?? '%s claimed: %s',
                $username,
                $featName
            );
            PlayerUtil::sendMessage(
                (int) $user['id'],
                0,
                $lang['feat_inbox_from'] ?? 'Feats of Strength',
                4,
                $subject,
                $body,
                $at,
                null,
                1,
                $universe
            );
        }
    }
}
