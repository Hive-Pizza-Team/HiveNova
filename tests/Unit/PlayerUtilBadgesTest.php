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
