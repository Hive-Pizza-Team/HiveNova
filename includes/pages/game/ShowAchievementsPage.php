<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\AchievementService;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;

class ShowAchievementsPage extends AbstractGamePage
{
    public static $requireModule = MODULE_ACHIEVEMENTS;

    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        global $USER, $LNG;

        $LNG->includeData(['ACHIEVEMENTS']);

        $achievements = AchievementService::get()->getAchievementsForUser(
            (int) $USER['id'],
            Universe::current()
        );

        $byCategory = [];
        $unlockedCount = 0;
        $pointsTotal = 0;

        foreach ($achievements as $row) {
            $cat = $row['category'];
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = [];
            }
            $byCategory[$cat][] = $row;
            if ($row['unlocked']) {
                $unlockedCount++;
                $pointsTotal += $row['points'];
            }
        }

        $categoryLabels = [
            'combat'      => $LNG['ach_category_combat'],
            'economy'     => $LNG['ach_category_economy'],
            'research'    => $LNG['ach_category_research'],
            'fleet'       => $LNG['ach_category_fleet'],
            'exploration' => $LNG['ach_category_exploration'],
            'empire'      => $LNG['ach_category_empire'],
            'social'      => $LNG['ach_category_social'],
            'hive'        => $LNG['ach_category_hive'],
        ];

        $this->assign([
            'achievementsByCategory' => $byCategory,
            'categoryLabels'         => $categoryLabels,
            'unlockedCount'          => $unlockedCount,
            'totalCount'             => count($achievements),
            'pointsTotal'            => $pointsTotal,
        ]);

        $this->display('page.achievements.default.tpl');
    }

    public function celebrate()
    {
        global $USER;

        $this->setWindow('ajax');

        $achievementId = HTTP::_GP('achievementId', 0);

        if ($achievementId > 0) {
            AchievementService::get()->markCelebrated((int) $USER['id'], $achievementId);
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}
