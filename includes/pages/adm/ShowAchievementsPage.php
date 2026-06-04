<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\Template;
use HiveNova\Core\Universe;

if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    exit;
}

function ShowAchievementsPage()
{
    global $LNG, $USER;

    $db = Database::get();
    $universe = Universe::getEmulated();

    if (HTTP::_GP('run_backfill', 0) && $USER['authlevel'] >= AUTH_ADM) {
        $offsetPath = ROOT_PATH . 'cache/achievement_backfill.offset';
        if (is_file($offsetPath)) {
            unlink($offsetPath);
        }
        $db->update(
            "UPDATE %%CRONJOBS%% SET isActive = 1 WHERE class = 'HiveNova\\\\Cronjob\\\\AchievementBackfillCronjob';"
        );
        $message = 'Achievement backfill cron re-enabled. It will process users in batches of 200.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && HTTP::_GP('save', 0)) {
        $id = HTTP::_GP('id', 0);
        $db->update(
            'UPDATE %%ACHIEVEMENTS%% SET
            active = :active,
            hidden = :hidden,
            reward_type = :rewardType,
            reward_amount = :rewardAmount,
            celebration_tier = :tier,
            trigger_params = :params
            WHERE id = :id AND universe = :universe;',
            [
                ':active'       => HTTP::_GP('active', 0) ? 1 : 0,
                ':hidden'       => HTTP::_GP('hidden', 0) ? 1 : 0,
                ':rewardType'   => HTTP::_GP('reward_type', 'none'),
                ':rewardAmount' => HTTP::_GP('reward_amount', 0),
                ':tier'         => HTTP::_GP('celebration_tier', 'normal'),
                ':params'       => HTTP::_GP('trigger_params', '{}'),
                ':id'           => $id,
                ':universe'     => $universe,
            ]
        );
        AchievementService::get()->clearDefinitionCache();
        AchievementService::get()->loadDefinitions($universe);
        if (empty($message)) {
            $message = 'Achievement saved.';
        }
    }

    $achievements = $db->select(
        'SELECT * FROM %%ACHIEVEMENTS%% WHERE universe = :universe ORDER BY category, sort_order, id;',
        [':universe' => $universe]
    );

    $template = new Template();
    $template->assign_vars([
        'achievements' => $achievements,
        'message'      => $message ?? '',
    ]);

    $template->show('ShowAchievementsPage.tpl');
}
