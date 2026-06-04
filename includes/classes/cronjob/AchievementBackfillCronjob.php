<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\AchievementService;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Cronjob\CronjobTask;

class AchievementBackfillCronjob implements CronjobTask
{
    private const BATCH_SIZE = 200;

    private const OFFSET_FILE = 'cache/achievement_backfill.offset';

    public function run()
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS)) {
            return true;
        }

        $offsetPath = ROOT_PATH . self::OFFSET_FILE;
        $lastId = is_file($offsetPath) ? (int) file_get_contents($offsetPath) : 0;

        $db = Database::get();
        $users = $db->select(
            'SELECT id FROM %%USERS%% WHERE id > :lastId ORDER BY id ASC LIMIT ' . self::BATCH_SIZE . ';',
            [':lastId' => $lastId]
        );

        if (empty($users)) {
            $this->disableCronjob();
            if (is_file($offsetPath)) {
                unlink($offsetPath);
            }
            return true;
        }

        $service = AchievementService::get();
        $maxId = $lastId;

        foreach ($users as $row) {
            $userId = (int) $row['id'];
            $service->evaluateSnapshot($userId, false);
            $maxId = $userId;
        }

        file_put_contents($offsetPath, (string) $maxId);

        if (count($users) < self::BATCH_SIZE) {
            $this->disableCronjob();
            if (is_file($offsetPath)) {
                unlink($offsetPath);
            }
        }

        return true;
    }

    private function disableCronjob(): void
    {
        Database::get()->update(
            "UPDATE %%CRONJOBS%% SET isActive = 0 WHERE class = 'HiveNova\\\\Cronjob\\\\AchievementBackfillCronjob';"
        );
    }
}
