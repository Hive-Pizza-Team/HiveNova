<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\Database;
use HiveNova\Cronjob\AchievementBackfillCronjob;

class AchievementBackfillCronjobIntegrationTest extends IntegrationTestCase
{
    private const OFFSET_FILE = 'cache/achievement_backfill.offset';

    public function testBackfillBatchUnlocksWithCelebratedFlag(): void
    {
        if (!AchievementService::isSchemaReady()) {
            $this->markTestSkipped('Achievement schema not ready.');
        }

        $offsetPath = ROOT_PATH . self::OFFSET_FILE;
        if (is_file($offsetPath)) {
            unlink($offsetPath);
        }

        $username = self::makeUniqueUsername('ach_bf');
        [$userId] = self::createTestPlayer($username, 3, 1, 10);

        $firstWinId = (int) Database::get()->selectSingle(
            "SELECT id FROM %%ACHIEVEMENTS%% WHERE `key` = 'combat_first_win' LIMIT 1;",
            [],
            'id'
        );
        Database::get()->delete(
            'DELETE FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => $firstWinId]
        );
        Database::get()->delete(
            'DELETE FROM %%USER_ACHIEVEMENT_PROGRESS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => $firstWinId]
        );

        Database::get()->update(
            'UPDATE %%USERS%% SET wons = 1 WHERE id = :userId;',
            [':userId' => $userId]
        );

        // Start processing after the user before our test user so this batch includes $userId.
        Database::get()->update(
            'UPDATE %%CRONJOBS%% SET isActive = 1 WHERE class = :class;',
            [':class' => 'HiveNova\\Cronjob\\AchievementBackfillCronjob']
        );
        file_put_contents($offsetPath, (string) ($userId - 1));

        $cron = new AchievementBackfillCronjob();
        $this->assertTrue($cron->run());

        $row = Database::get()->selectSingle(
            'SELECT celebrated FROM %%USER_ACHIEVEMENTS%% WHERE user_id = :userId AND achievement_id = :achievementId;',
            [':userId' => $userId, ':achievementId' => $firstWinId]
        );
        $this->assertNotEmpty($row);
        $this->assertSame('1', (string) $row['celebrated'], 'Backfill must not queue celebration overlay.');

        if (is_file($offsetPath)) {
            unlink($offsetPath);
        }
    }
}
