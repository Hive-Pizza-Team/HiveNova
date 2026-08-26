<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\PlayerUtil;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class PlayerUtilBadgesTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function setUp(): void
    {
        if (!defined('MODULE_ACHIEVEMENTS')) {
            define('MODULE_ACHIEVEMENTS', 46);
        }

        $modules = array_fill(0, 50, '1');
        $modules[46] = '1';
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', $modules)]), 1);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $cache = new ReflectionProperty(AchievementService::class, 'schemaReadyCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function test_getPlayerBadges_peakd_link_when_hive_account_matches_username(): void
    {
        $user = ['username' => 'tor', 'hive_account' => 'tor'];
        $this->assertStringContainsString('peakd.com/@tor', PlayerUtil::getPlayerBadges($user));
    }

    public function test_getPlayerBadges_chain_icon_when_hive_account_mismatch(): void
    {
        $user = ['username' => 'tor', 'hive_account' => 'other'];
        $this->assertSame('🔗', PlayerUtil::getPlayerBadges($user));
    }

    public function test_getAchievementBadges_returns_icons_for_unlocked_rows(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);
        $fake->achievement->unlocked['7:1'] = true;

        $html = PlayerUtil::getAchievementBadges(7, 5);

        $this->assertStringContainsString('achievement-badge', $html);
        $this->assertStringContainsString('combat_first_win', $html);
    }

    public function test_getAchievementBadges_prefers_showcase_order(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);
        $ach = $fake->achievement;
        $ach->addAchievement([
            'id'               => 2,
            'key'              => 'low_points_pick',
            'category'         => 'combat',
            'name_key'         => 'n',
            'desc_key'         => 'd',
            'trigger_type'     => 'combat_wins',
            'trigger_params'   => '{"threshold":2}',
            'reward_type'      => 'none',
            'reward_amount'    => 0,
            'points'           => 1,
            'celebration_tier' => 'normal',
            'hidden'           => 0,
            'active'           => 1,
            'universe'         => 1,
        ]);
        $ach->unlocked['7:1'] = true;
        $ach->unlocked['7:2'] = true;
        $ach->showcase['7:2'] = 1;

        $html = PlayerUtil::getAchievementBadges(7, 5);

        $this->assertStringContainsString('low_points_pick', $html);
        $this->assertStringNotContainsString('combat_first_win', $html);
    }

    public function test_getAchievementBadges_falls_back_to_points_when_no_showcase(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);
        $ach = $fake->achievement;
        $ach->addAchievement([
            'id'               => 2,
            'key'              => 'high_points',
            'category'         => 'combat',
            'name_key'         => 'n',
            'desc_key'         => 'd',
            'trigger_type'     => 'combat_wins',
            'trigger_params'   => '{"threshold":2}',
            'reward_type'      => 'none',
            'reward_amount'    => 0,
            'points'           => 100,
            'celebration_tier' => 'normal',
            'hidden'           => 0,
            'active'           => 1,
            'universe'         => 1,
        ]);
        $ach->unlocked['7:1'] = true;
        $ach->unlocked['7:2'] = true;

        $html = PlayerUtil::getAchievementBadges(7, 1);

        $this->assertStringContainsString('high_points', $html);
        $this->assertStringNotContainsString('combat_first_win', $html);
    }

    public function test_getAchievementBadges_returns_empty_when_module_disabled(): void
    {
        $modules = array_fill(0, 50, '1');
        $modules[46] = '0';
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', $modules)]), 1);

        $this->assertSame('', PlayerUtil::getAchievementBadges(7));
    }

    public function test_getAchievementBadges_returns_empty_when_schema_not_ready(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);
        $fake->achievement->schemaReady = false;

        $this->assertSame('', PlayerUtil::getAchievementBadges(7));
    }

    public function test_getAchievementBadges_returns_empty_when_user_has_no_unlocks(): void
    {
        $fake = new FakeDatabase();
        $this->swapDatabaseInstance($fake);

        $this->assertSame('', PlayerUtil::getAchievementBadges(99));
    }

    public function test_getPlayerBadges_broken_chain_for_invalid_hive_username(): void
    {
        $user = ['username' => 'Not Valid!', 'hive_account' => ''];
        $this->assertSame('⛓️‍💥', PlayerUtil::getPlayerBadges($user));
    }
}
