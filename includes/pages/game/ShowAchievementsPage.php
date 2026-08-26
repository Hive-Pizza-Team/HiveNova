<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\AchievementService;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;

class ShowAchievementsPage extends AbstractGamePage
{
    public static $requireModule = MODULE_ACHIEVEMENTS;

    public const SHOWCASE_LIMIT = 5;

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
        $showcaseCount = 0;

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
            if (!empty($row['showcase_order'])) {
                $showcaseCount++;
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

        $this->tplObj->loadscript('achievements-showcase.js');

        $this->assign([
            'achievementsByCategory' => $byCategory,
            'categoryLabels'         => $categoryLabels,
            'unlockedCount'          => $unlockedCount,
            'totalCount'             => count($achievements),
            'pointsTotal'            => $pointsTotal,
            'showcaseCount'          => $showcaseCount,
            'showcaseLimit'          => self::SHOWCASE_LIMIT,
            'darkmatterName'         => $LNG['tech'][921] ?? '',
            'achShowcaseMaxMsg'      => $LNG['ach_showcase_max'] ?? '',
            'achShowcaseSavedMsg'    => $LNG['ach_showcase_saved'] ?? '',
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

        $this->sendJSON(['ok' => true]);
    }

    public function showcase()
    {
        global $USER;

        $this->setWindow('ajax');

        $ids = HTTP::_GP('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }

        $count = AchievementService::get()->setShowcase((int) $USER['id'], $ids);

        $this->sendJSON(['ok' => true, 'count' => $count]);
    }
}
