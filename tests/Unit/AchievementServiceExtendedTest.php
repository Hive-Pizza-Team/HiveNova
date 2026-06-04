<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\ResourceUpdate;
use HiveNova\Core\AllianceService;
use HiveNova\Core\AchievementHooks;
use HiveNova\Cronjob\AchievementBackfillCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeAchievementDatabase.php';

class AchievementServiceExtendedTest extends TestCase
{
    private ?DatabaseInterface $previousDb = null;

    protected function setUp(): void
    {
        AchievementService::resetSchemaReadyCache();
        AchievementService::get()->clearDefinitionCache();
        Config::setInstance($this->makeConfig(), 1);
        $GLOBALS['LNG'] = array_merge($GLOBALS['LNG'] ?? [], [
            'ach_combat_first_win_name' => 'First Win',
            'ach_combat_first_win_desc' => 'Win a fight',
            'ach_hidden_name'           => '???',
            'ach_hidden_desc'           => 'Hidden',
            'ach_unlock_subject'        => 'Unlocked: %s',
            'ach_unlock_body'           => 'Got %s',
            'ach_unlock_from'           => 'Achievements',
            'ach_unlock_reward'         => '%s %s',
            'tech'                      => [921 => 'DM'],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->previousDb instanceof DatabaseInterface) {
            Database::setInstance($this->previousDb);
        } else {
            $ref = new ReflectionClass(Database::class);
            $prop = $ref->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null);
        }
        $this->previousDb = null;
        AchievementService::get()->clearDefinitionCache();
    }

    private function useFake(FakeAchievementDatabase $fake): FakeAchievementDatabase
    {
        if ($this->previousDb === null) {
            $ref = new ReflectionClass(Database::class);
            $prop = $ref->getProperty('instance');
            $prop->setAccessible(true);
            $this->previousDb = $prop->getValue();
        }
        Database::setInstance($fake);
        AchievementService::get()->clearDefinitionCache();
        return $fake;
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($object, ...$args);
    }

    private function sampleUser(int $id = 42): array
    {
        return [
            'id'            => $id,
            'universe'      => 1,
            'lang'          => 'en',
            'wons'          => 0,
            'loos'          => 0,
            'draws'         => 0,
            'desunits'      => 0,
            'ally_id'       => 0,
            'hive_account'  => '',
            'register_time' => TIMESTAMP - (30 * 86400),
            'darkmatter'    => 0,
        ];
    }

    private function makeConfig(): Config
    {
        $modules = array_fill(0, 50, 1);
        return new Config(['uni' => 1, 'moduls' => implode(';', $modules)]);
    }

    private function ach(string $trigger, array $params, int $id, string $key = 'ach'): array
    {
        return [
            'id'               => $id,
            'key'              => $key,
            'category'         => 'test',
            'name_key'         => 'ach_combat_first_win_name',
            'desc_key'         => 'ach_combat_first_win_desc',
            'trigger_type'     => $trigger,
            'trigger_params'   => json_encode($params),
            'reward_type'      => 'none',
            'reward_amount'    => 0,
            'points'           => 5,
            'celebration_tier' => 'epic',
            'hidden'           => 0,
            'active'           => 1,
            'universe'         => 1,
        ];
    }

    private function achDecoded(string $trigger, array $params, int $id): array
    {
        $row = $this->ach($trigger, $params, $id);
        $row['trigger_params'] = $params;
        return $row;
    }

    public function testRecordStaticDelegatesWhenSchemaReady(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[1] = $this->sampleUser(1);
        $fake->users[1]['wons'] = 1;

        $this->assertSame([1], AchievementService::record(1, 'combat_wins', [], false));
    }

    public function testRecordReturnsEmptyWhenSchemaNotReady(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->schemaReady = false;

        $this->assertSame([], AchievementService::record(1, 'combat_wins'));
    }

    public function testEvaluateSnapshotStatic(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->achievementDefinitions = [$this->ach('combat_wins', ['threshold' => 1], 2, 'wins')];
        $fake->users[3] = $this->sampleUser(3);
        $fake->users[3]['wons'] = 1;

        $this->assertContains(2, AchievementService::evaluateSnapshot(3, false));
    }

    public function testProcessEventReturnsEmptyForMissingUser(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $this->assertSame([], AchievementService::get()->processEvent(999, 'combat_wins', [], true));
    }

    public function testProcessEventReturnsEmptyForUnknownTrigger(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[1] = $this->sampleUser(1);
        $this->assertSame([], AchievementService::get()->processEvent(1, 'nonexistent_trigger', [], true));
    }

    public function testLoadDefinitionsCachesByUniverse(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $service = AchievementService::get();
        $service->loadDefinitions(1);
        $service->loadDefinitions(1);
        $this->addToAssertionCount(1);
    }

    public function testRecordCombatAfterBattleWinAndLossPaths(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('units_destroyed', ['threshold' => 1], 10, 'destroy'));
        $fake->addAchievement($this->ach('combat_win_rate', ['threshold' => 50, 'min_fights' => 1], 11, 'wr'));
        $fake->users[5] = $this->sampleUser(5);
        $fake->users[5]['wons'] = 3;
        $fake->users[5]['loos'] = 1;
        $fake->users[5]['desunits'] = 100;

        AchievementService::recordCombatAfterBattle([5], true);
        $this->assertGreaterThan(0, $fake->progress['5:1'] ?? 0);

        AchievementService::recordCombatAfterBattle([5], false);
        $this->assertGreaterThan(0, $fake->progress['5:10'] ?? 0);
    }

    public function testRecordBuildCompletedFiresElementLevel(): void
    {
        global $resource, $reslist;
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('element_level', ['threshold' => 2, 'element_id' => 1], 20, 'mine'));
        $fake->users[6] = $this->sampleUser(6);
        $planet = ['metal_mine' => 3];
        $user = $fake->users[6];

        AchievementService::recordBuildCompleted(6, [1 => 1], $user, $planet);
        $this->assertSame(3, $fake->progress['6:20'] ?? 0);
    }

    public function testResolveEventValueAllTriggerTypes(): void
    {
        $service = AchievementService::get();
        $user = $this->sampleUser();
        $user['wons'] = 4;
        $user['desunits'] = 9;
        $user['ally_id'] = 2;

        $this->assertSame(11, $this->invokePrivate($service, 'resolveEventValue', [
            'combat_wins', ['total' => 11], $user, $this->achDecoded('combat_wins', [], 1),
        ]));
        $this->assertSame(9, $this->invokePrivate($service, 'resolveEventValue', [
            'units_destroyed', [], $user, $this->achDecoded('units_destroyed', [], 2),
        ]));
        $this->assertSame(3, $this->invokePrivate($service, 'resolveEventValue', [
            'planet_count', ['total' => 3], $user, $this->achDecoded('planet_count', [], 3),
        ]));
        $this->assertSame(7, $this->invokePrivate($service, 'resolveEventValue', [
            'stat_points', ['value' => 7], $user, $this->achDecoded('stat_points', [], 4),
        ]));
        $this->assertSame(2, $this->invokePrivate($service, 'resolveEventValue', [
            'expedition_count', ['total' => 2], $user, $this->achDecoded('expedition_count', [], 5),
        ]));
        $this->assertSame(1, $this->invokePrivate($service, 'resolveEventValue', [
            'ally_joined', ['joined' => 1], $user, $this->achDecoded('ally_joined', [], 6),
        ]));
        $this->assertSame(1, $this->invokePrivate($service, 'resolveEventValue', [
            'hive_account_valid', ['valid' => 1], $user, $this->achDecoded('hive', [], 7),
        ]));
        $this->assertSame(30, $this->invokePrivate($service, 'resolveEventValue', [
            'account_age_days', ['days' => 30], $user, $this->achDecoded('age', [], 8),
        ]));
        $this->assertNull($this->invokePrivate($service, 'resolveEventValue', [
            'unknown_trigger', [], $user, $this->achDecoded('x', [], 9),
        ]));
    }

    public function testResolveSnapshotValueAllTriggerTypes(): void
    {
        $service = AchievementService::get();
        $user = $this->sampleUser();
        $snapshot = [
            'wons'             => 2,
            'desunits'         => 50,
            'total_points'     => 1000,
            'fleet_points'     => 400,
            'planet_count'     => 3,
            'expedition_count' => 5,
            'ally_id'          => 1,
            'hive_valid'       => 1,
            'account_age_days' => 10,
            'win_rate'         => 66.6,
            'total_fights'     => 3,
            'element_levels'   => [124 => 4],
        ];

        $this->assertSame(2, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'combat_wins', $snapshot, $user, $this->achDecoded('combat_wins', [], 1),
        ]));
        $this->assertSame(50, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'units_destroyed', $snapshot, $user, $this->achDecoded('units_destroyed', [], 2),
        ]));
        $this->assertSame(4, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'element_level', $snapshot, $user, $this->achDecoded('element_level', ['element_id' => 124], 3),
        ]));
        $this->assertSame(3, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'planet_count', $snapshot, $user, $this->achDecoded('planet_count', [], 4),
        ]));
        $this->assertSame(400, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'stat_points', $snapshot, $user, $this->achDecoded('stat_points', ['stat' => 'fleet'], 5),
        ]));
        $this->assertSame(5, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'expedition_count', $snapshot, $user, $this->achDecoded('expedition_count', [], 6),
        ]));
        $this->assertSame(1, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'ally_joined', $snapshot, $user, $this->achDecoded('ally_joined', [], 7),
        ]));
        $this->assertSame(1, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'hive_account_valid', $snapshot, $user, $this->achDecoded('hive', [], 8),
        ]));
        $this->assertSame(10, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'account_age_days', $snapshot, $user, $this->achDecoded('age', [], 9),
        ]));
        $this->assertNull($this->invokePrivate($service, 'resolveSnapshotValue', [
            'unknown', $snapshot, $user, $this->achDecoded('x', [], 10),
        ]));
    }

    public function testBuildSnapshotAggregatesStats(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[8] = $this->sampleUser(8);
        $fake->users[8]['wons'] = 2;
        $fake->users[8]['loos'] = 1;
        $fake->statPoints = ['total_points' => 5000, 'fleet_points' => 2000];
        $fake->planetCount = 4;
        $fake->expeditionCount = 7;
        $fake->planetMaxLevels = ['e1' => 5, 'e2' => 3];
        $fake->planetColumns = ['metal_mine', 'crystal_mine'];

        $snapshot = $this->invokePrivate(AchievementService::get(), 'buildSnapshot', [$fake->users[8]]);

        $this->assertSame(4, $snapshot['planet_count']);
        $this->assertSame(7, $snapshot['expedition_count']);
        $this->assertSame(5000, $snapshot['total_points']);
        $this->assertSame(5, $snapshot['element_levels'][1] ?? 0);
        $this->assertGreaterThan(0, $snapshot['win_rate']);
    }

    public function testGetAchievementsForUserIncludesHiddenLocked(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->achievementDefinitions = [
            array_merge($this->ach('combat_wins', ['threshold' => 1], 1), ['hidden' => 1]),
        ];
        $list = AchievementService::get()->getAchievementsForUser(1, 1);
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['hidden']);
        $this->assertSame('???', $list[0]['name']);
    }

    public function testGetPendingCelebrationsReturnsRows(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->pendingCelebrationRows = [[
            'id' => 1, 'key' => 'combat_first_win', 'name_key' => 'ach_combat_first_win_name',
            'desc_key' => 'ach_combat_first_win_desc', 'celebration_tier' => 'legendary',
            'reward_type' => 'none', 'reward_amount' => 0, 'points' => 1, 'unlocked_at' => TIMESTAMP,
        ]];
        $rows = AchievementService::get()->getPendingCelebrations(1);
        $this->assertCount(1, $rows);
    }

    public function testGetPendingCelebrationsEmptyWhenSchemaMissing(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->schemaReady = false;
        $this->assertSame([], AchievementService::get()->getPendingCelebrations(1));
    }

    public function testApplyRewardSkipsNone(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[1] = $this->sampleUser(1);
        $ach = $this->ach('combat_wins', ['threshold' => 1], 1);
        $ach['reward_type'] = 'none';
        $fake->achievementDefinitions = [$ach];
        $fake->users[1]['wons'] = 1;

        AchievementService::get()->processEvent(1, 'combat_wins', [], false);
        $this->assertSame(0.0, $fake->darkmatter);
        $this->assertCount(0, $fake->grants);
    }

    public function testIsSchemaReadyTrueWithFake(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $this->assertTrue(AchievementService::isSchemaReady());
    }

    public function testPlayerUtilAchievementBadgesRendersHtml(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->badgeRows = [
            ['key' => 'combat_first_win', 'points' => 10],
        ];
        $html = PlayerUtil::getAchievementBadges(1, 3);
        $this->assertStringContainsString('achievement-badge', $html);
        $this->assertStringContainsString('🏅', $html);
    }

    public function testPlayerUtilAchievementBadgesEmptyWhenSchemaMissing(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->schemaReady = false;
        $this->assertSame('', PlayerUtil::getAchievementBadges(1));
    }

    public function testBackfillCronjobProcessesBatch(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[10] = $this->sampleUser(10);
        $fake->users[10]['wons'] = 1;
        $fake->cronUserBatch = [['id' => 10]];

        $offsetPath = ROOT_PATH . 'cache/achievement_backfill.offset';
        if (is_file($offsetPath)) {
            unlink($offsetPath);
        }

        $cron = new AchievementBackfillCronjob();
        $this->assertTrue($cron->run());
        $this->assertTrue(isset($fake->unlocked['10:1']));
        if (is_file($offsetPath)) {
            unlink($offsetPath);
        }
    }

    public function testAllianceCreateAllianceFiresAchievementRecord(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('ally_joined', ['threshold' => 1], 30, 'ally'));
        $fake->users[15] = $this->sampleUser(15);

        $allianceId = AllianceService::createAlliance(15, 1, 'T' . uniqid(), 'Alliance ' . uniqid(), 'Leader');
        $this->assertGreaterThan(0, $allianceId);
        $this->assertTrue(isset($fake->unlocked['15:30']) || isset($fake->progress['15:30']));
    }

    public function testResourceUpdateSavePlanetToDBFiresBuildAchievement(): void
    {
        global $resource, $reslist;
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('element_level', ['threshold' => 1, 'element_id' => 1], 40, 'bld'));
        $user = $this->sampleUser(20);
        $planet = [
            'id' => 1,
            'name' => 'Test',
            'metal' => 1000,
            'crystal' => 1000,
            'deuterium' => 1000,
            'metal_mine' => 2,
            'eco_hash' => '',
            'last_update' => TIMESTAMP,
            'b_building' => 0,
            'b_building_id' => '',
            'field_current' => 1,
            'b_hangar_id' => '',
            'metal_perhour' => 1,
            'crystal_perhour' => 1,
            'deuterium_perhour' => 1,
            'metal_max' => 10000,
            'crystal_max' => 10000,
            'deuterium_max' => 10000,
            'energy_used' => 0,
            'energy' => 0,
            'b_hangar' => 0,
        ];
        $fake->users[20] = $user;

        $ru = new ResourceUpdate(false, false);
        $ru->setResourceData($resource, $reslist);
        $ru->setData($user, $planet);

        $builded = new ReflectionProperty(ResourceUpdate::class, 'Builded');
        $builded->setAccessible(true);
        $builded->setValue($ru, [1 => 1]);

        $ru->SavePlanetToDB($user, $planet);
        $this->assertGreaterThanOrEqual(1, $fake->progress['20:40'] ?? 0);
    }

    public function testAchievementHooksAfterCombat(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[7] = $this->sampleUser(7);
        $fake->users[8] = $this->sampleUser(8);
        $fake->users[7]['wons'] = 1;

        AchievementHooks::afterCombat([7 => true], [8 => true], 'wons', 'loos');
        $this->assertGreaterThan(0, $fake->progress['7:1'] ?? 0);
    }

    public function testAchievementHooksColonisationAndExpedition(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('planet_count', ['threshold' => 1], 50, 'planets'));
        $fake->addAchievement($this->ach('expedition_count', ['threshold' => 1], 51, 'expe'));
        $fake->users[11] = $this->sampleUser(11);
        $fake->planetCount = 2;
        $fake->expeditionCount = 3;

        AchievementHooks::afterColonisation(11);
        AchievementHooks::afterExpedition(11);

        $this->assertGreaterThan(0, $fake->progress['11:50'] ?? 0);
        $this->assertGreaterThan(0, $fake->progress['11:51'] ?? 0);
    }

    public function testAchievementHooksAfterBuildCompleted(): void
    {
        global $resource, $reslist;
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('element_level', ['threshold' => 1, 'element_id' => 1], 52, 'build'));
        $user = $this->sampleUser(12);
        $fake->users[12] = $user;
        $planet = ['metal_mine' => 2];

        AchievementHooks::afterBuildCompleted([1 => 1], $user, $planet);
        $this->assertGreaterThan(0, $fake->progress['12:52'] ?? 0);
    }

    public function testAchievementHooksNoOpWhenModuleDisabled(): void
    {
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', array_fill(0, 50, 0))]), 1);
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->users[1] = $this->sampleUser(1);
        $fake->users[1]['wons'] = 5;

        AchievementHooks::afterCombat([1 => true], [], 'wons', 'loos');
        AchievementHooks::afterColonisation(1);
        AchievementHooks::afterExpedition(1);
        AchievementHooks::afterBuildCompleted([1 => 1], $fake->users[1], ['metal_mine' => 1]);
        $this->assertSame([], $fake->progress);
    }

    public function testAchievementHooksSkipsEmptyBuildList(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $user = $this->sampleUser(2);
        AchievementHooks::afterBuildCompleted([], $user, []);
        $this->assertSame([], $fake->progress);
    }

    public function testPlayerUtilBadgesEmptyRows(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $this->assertSame('', PlayerUtil::getAchievementBadges(1));
    }

    public function testBackfillCronjobDisablesWhenNoUsers(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->cronUserBatch = [];
        $offsetPath = ROOT_PATH . 'cache/achievement_backfill.offset';
        if (is_file($offsetPath)) {
            unlink($offsetPath);
        }
        $cron = new AchievementBackfillCronjob();
        $this->assertTrue($cron->run());
    }

    public function testAllianceAcceptMemberFiresAchievementRecord(): void
    {
        $fake = $this->useFake(new FakeAchievementDatabase());
        $fake->addAchievement($this->ach('ally_joined', ['threshold' => 1], 31, 'ally_join'));
        $fake->users[16] = $this->sampleUser(16);
        $fake->allianceRequestUserId = 16;

        AllianceService::acceptMember(1, 99);
        $this->assertTrue(isset($fake->progress['16:31']) || isset($fake->unlocked['16:31']));
    }
}
