<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;

class AchievementServiceIntegrationTest extends IntegrationTestCase
{
    private static ?int $firstWinId = null;
    private static ?int $wins10Id = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!AchievementService::isSchemaReady()) {
            return;
        }

        $db = Database::get();
        self::$firstWinId = (int) $db->selectSingle(
            "SELECT id FROM %%ACHIEVEMENTS%% WHERE `key` = 'combat_first_win' LIMIT 1;",
            [],
            'id'
        );
        self::$wins10Id = (int) $db->selectSingle(
            "SELECT id FROM %%ACHIEVEMENTS%% WHERE `key` = 'combat_wins_10' LIMIT 1;",
            [],
            'id'
        );
    }

    private function requireSchema(): void
    {
        if (!AchievementService::isSchemaReady() || !self::$firstWinId || !self::$wins10Id) {
            $this->markTestSkipped('Achievement tables or seed data not available (run migration 19 / ci-install).');
        }
    }

    private function resetUserAchievements(int $userId, int ...$achievementIds): void
    {
        $db = Database::get();
        foreach ($achievementIds as $achievementId) {
            $db->delete(
                'DELETE FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
                [':userId' => $userId, ':achievementId' => $achievementId]
            );
            $db->delete(
                'DELETE FROM %%USER_ACHIEVEMENT_PROGRESS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
                [':userId' => $userId, ':achievementId' => $achievementId]
            );
        }
    }

    public function testProgressBelowThresholdDoesNotUnlock(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_prog');
        [$userId] = self::createTestPlayer($username, 3, 1, 5);
        $this->resetUserAchievements($userId, self::$wins10Id);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 3 WHERE id = :userId;',
            [':userId' => $userId]
        );

        $service = AchievementService::get();
        $service->clearDefinitionCache();
        $unlocked = $service->processEvent($userId, 'combat_wins', [], true);

        $this->assertSame([], $unlocked);

        $progress = (int) Database::get()->selectSingle(
            'SELECT progress FROM %%USER_ACHIEVEMENT_PROGRESS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => self::$wins10Id],
            'progress'
        );
        $this->assertSame(3, $progress);

        $this->assertFalse((bool) Database::get()->selectSingle(
            'SELECT 1 FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => self::$wins10Id],
            '1'
        ));
    }

    public function testDarkmatterRewardAndGrantAuditOnUnlock(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_dm');
        [$userId] = self::createTestPlayer($username, 3, 1, 4);
        $this->resetUserAchievements($userId, self::$firstWinId);

        $dmBefore = (float) Database::get()->selectSingle(
            'SELECT darkmatter FROM %%USERS%% WHERE id = :userId;',
            [':userId' => $userId],
            'darkmatter'
        );

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );

        $service = AchievementService::get();
        $service->clearDefinitionCache();
        $service->processEvent($userId, 'combat_wins', [], false);

        $dmAfter = (float) Database::get()->selectSingle(
            'SELECT darkmatter FROM %%USERS%% WHERE id = :userId;',
            [':userId' => $userId],
            'darkmatter'
        );
        $this->assertSame($dmBefore + 500.0, $dmAfter);

        $grant = Database::get()->selectSingle(
            'SELECT reward_type, reward_amount FROM %%ACHIEVEMENT_GRANTS%%
            WHERE user_id = :userId AND achievement_id = :achievementId ORDER BY id DESC LIMIT 1;',
            [':userId' => $userId, ':achievementId' => self::$firstWinId]
        );
        $this->assertSame('darkmatter', $grant['reward_type']);
        $this->assertSame(500.0, (float) $grant['reward_amount']);
    }

    public function testUnlockCreatesInboxMessage(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_msg');
        [$userId] = self::createTestPlayer($username, 3, 1, 3);
        $this->resetUserAchievements($userId, self::$firstWinId);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );

        $service = AchievementService::get();
        $service->clearDefinitionCache();
        $service->processEvent($userId, 'combat_wins', [], false);

        $message = Database::get()->selectSingle(
            'SELECT message_subject FROM %%MESSAGES%% WHERE message_owner = :userId ORDER BY message_id DESC LIMIT 1;',
            [':userId' => $userId],
            'message_subject'
        );
        $this->assertStringContainsString('Achievement', (string) $message);
    }

    public function testGetAchievementsForUserReflectsUnlockState(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_list');
        [$userId] = self::createTestPlayer($username, 3, 1, 2);
        $this->resetUserAchievements($userId, self::$firstWinId);

        $listBefore = AchievementService::get()->getAchievementsForUser($userId, 1);
        $row = null;
        foreach ($listBefore as $item) {
            if ($item['key'] === 'combat_first_win') {
                $row = $item;
                break;
            }
        }
        $this->assertNotNull($row);
        $this->assertFalse($row['unlocked']);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );
        AchievementService::get()->clearDefinitionCache();
        AchievementService::get()->processEvent($userId, 'combat_wins', [], false);

        $listAfter = AchievementService::get()->getAchievementsForUser($userId, 1);
        foreach ($listAfter as $item) {
            if ($item['key'] === 'combat_first_win') {
                $this->assertTrue($item['unlocked']);
                $this->assertGreaterThan(0, $item['unlocked_at']);
                return;
            }
        }
        $this->fail('combat_first_win not found in achievement list');
    }

    public function testHiveAccountValidEventUnlock(): void
    {
        $this->requireSchema();

        $hiveId = (int) Database::get()->selectSingle(
            "SELECT id FROM %%ACHIEVEMENTS%% WHERE `key` = 'hive_linked' LIMIT 1;",
            [],
            'id'
        );
        $this->assertGreaterThan(0, $hiveId);

        $username = self::makeUniqueUsername('ach_hive');
        [$userId] = self::createTestPlayer($username, 3, 1, 1);
        $this->resetUserAchievements($userId, $hiveId);

        $service = AchievementService::get();
        $service->clearDefinitionCache();
        $unlocked = $service->processEvent($userId, 'hive_account_valid', ['valid' => 1], true);

        $this->assertContains($hiveId, $unlocked);
    }

    public function testEvaluateSnapshotUnlocksCombatFirstWinWithSilentCelebration(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_snap');
        [$userId] = self::createTestPlayer($username, 3, 1, 8);
        $this->resetUserAchievements($userId, self::$firstWinId);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );

        $unlocked = AchievementService::evaluateSnapshot($userId, false);
        $this->assertContains(self::$firstWinId, $unlocked);

        $row = Database::get()->selectSingle(
            'SELECT celebrated FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => self::$firstWinId]
        );
        $this->assertSame('1', (string) $row['celebrated']);
    }

    public function testProcessEventUnlockSetsCelebratedPendingForLiveHook(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_live');
        [$userId] = self::createTestPlayer($username, 3, 1, 7);
        $this->resetUserAchievements($userId, self::$firstWinId);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );

        $service = AchievementService::get();
        $service->clearDefinitionCache();
        $unlocked = $service->processEvent($userId, 'combat_wins', [], true);

        $this->assertContains(self::$firstWinId, $unlocked);

        $row = Database::get()->selectSingle(
            'SELECT celebrated FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => self::$firstWinId]
        );
        $this->assertSame('0', (string) $row['celebrated']);

        AchievementService::get()->markCelebrated($userId, self::$firstWinId);
        $row = Database::get()->selectSingle(
            'SELECT celebrated FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => self::$firstWinId]
        );
        $this->assertSame('1', (string) $row['celebrated']);
        $this->assertSame([], AchievementService::get()->getPendingCelebrations($userId));
    }

    public function testUnlockIsIdempotent(): void
    {
        $this->requireSchema();

        $username = self::makeUniqueUsername('ach_idem');
        [$userId] = self::createTestPlayer($username, 3, 1, 6);
        $this->resetUserAchievements($userId, self::$firstWinId);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );

        $service = AchievementService::get();
        $service->clearDefinitionCache();

        $first = $service->processEvent($userId, 'combat_wins', [], true);
        $second = $service->processEvent($userId, 'combat_wins', [], true);

        $this->assertContains(self::$firstWinId, $first);
        $this->assertSame([], $second);

        $count = (int) Database::get()->selectSingle(
            'SELECT COUNT(*) FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => self::$firstWinId],
            'COUNT(*)'
        );
        $this->assertSame(1, $count);
    }

    public function testRecordCombatAfterBattleUpdatesWinRate(): void
    {
        $this->requireSchema();

        $winRateId = (int) Database::get()->selectSingle(
            "SELECT id FROM %%ACHIEVEMENTS%% WHERE `key` = 'combat_win_rate_50' LIMIT 1;",
            [],
            'id'
        );
        if (!$winRateId) {
            $this->markTestSkipped('combat_win_rate_50 seed missing');
        }

        $username = self::makeUniqueUsername('ach_wr');
        [$userId] = self::createTestPlayer($username, 3, 1, 9);
        $this->resetUserAchievements($userId, $winRateId);

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 15, loos = 5, draws = 0 WHERE id = :userId;',
            [':userId' => $userId]
        );

        AchievementService::get()->clearDefinitionCache();
        AchievementService::recordCombatAfterBattle([$userId], true);

        $progress = (int) Database::get()->selectSingle(
            'SELECT progress FROM %%USER_ACHIEVEMENT_PROGRESS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => $winRateId],
            'progress'
        );
        $this->assertGreaterThanOrEqual(50, $progress);
    }
}
