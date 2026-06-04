<?php

use HiveNova\Core\AchievementService;
use PHPUnit\Framework\TestCase;

class AchievementServiceTest extends TestCase
{
    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }

    private function sampleAchievement(string $triggerType, array $params): array
    {
        return [
            'id'              => 99,
            'key'             => 'test_ach',
            'trigger_type'    => $triggerType,
            'trigger_params'  => $params,
            'reward_type'     => 'none',
            'reward_amount'   => 0,
            'celebration_tier'=> 'normal',
        ];
    }

    public function testDecodeParamsHandlesInvalidJson(): void
    {
        $service = AchievementService::get();

        $this->assertSame([], $this->invokePrivate($service, 'decodeParams', ['']));
        $this->assertSame(['threshold' => 5], $this->invokePrivate($service, 'decodeParams', ['{"threshold":5}']));
    }

    public function testResolveEventValueUsesUserWonsWhenPayloadEmpty(): void
    {
        $service = AchievementService::get();
        $user = ['id' => 1, 'universe' => 1, 'wons' => 7, 'desunits' => 0, 'ally_id' => 0];
        $ach = $this->sampleAchievement('combat_wins', ['threshold' => 1]);

        $value = $this->invokePrivate($service, 'resolveEventValue', ['combat_wins', [], $user, $ach]);

        $this->assertSame(7, $value);
    }

    public function testResolveEventValueElementLevelRequiresMatchingElement(): void
    {
        $service = AchievementService::get();
        $user = ['id' => 1, 'universe' => 1, 'wons' => 0, 'desunits' => 0, 'ally_id' => 0];
        $ach = $this->sampleAchievement('element_level', ['element_id' => 124, 'level' => 3]);

        $this->assertNull($this->invokePrivate($service, 'resolveEventValue', [
            'element_level',
            ['element_id' => 1, 'level' => 10],
            $user,
            $ach,
        ]));

        $this->assertSame(3, $this->invokePrivate($service, 'resolveEventValue', [
            'element_level',
            ['element_id' => 124, 'level' => 3],
            $user,
            $ach,
        ]));
    }

    public function testResolveEventValueCombatWinRateRequiresPayload(): void
    {
        $service = AchievementService::get();
        $user = ['id' => 1, 'universe' => 1, 'wons' => 10, 'loos' => 0, 'draws' => 0, 'ally_id' => 0];
        $ach = $this->sampleAchievement('combat_win_rate', ['threshold' => 50, 'min_fights' => 20]);

        $this->assertNull($this->invokePrivate($service, 'resolveEventValue', ['combat_win_rate', [], $user, $ach]));
        $this->assertSame(75, $this->invokePrivate($service, 'resolveEventValue', [
            'combat_win_rate',
            ['win_rate' => 75.4],
            $user,
            $ach,
        ]));
    }

    public function testResolveSnapshotValueStatPointsFleetVsTotal(): void
    {
        $service = AchievementService::get();
        $user = ['id' => 1, 'universe' => 1];
        $snapshot = [
            'wons' => 0,
            'desunits' => 0,
            'total_points' => 120000,
            'fleet_points' => 55000,
            'planet_count' => 1,
            'expedition_count' => 0,
            'ally_id' => 0,
            'hive_valid' => 0,
            'account_age_days' => 0,
            'win_rate' => 0,
            'total_fights' => 0,
            'element_levels' => [],
        ];

        $fleetAch = $this->sampleAchievement('stat_points', ['stat' => 'fleet', 'threshold' => 50000]);
        $totalAch = $this->sampleAchievement('stat_points', ['stat' => 'total', 'threshold' => 100000]);

        $this->assertSame(55000, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'stat_points', $snapshot, $user, $fleetAch,
        ]));
        $this->assertSame(120000, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'stat_points', $snapshot, $user, $totalAch,
        ]));
    }

    public function testResolveSnapshotValueCombatWinRateRespectsMinFights(): void
    {
        $service = AchievementService::get();
        $user = ['id' => 1, 'universe' => 1];
        $ach = $this->sampleAchievement('combat_win_rate', ['threshold' => 50, 'min_fights' => 20]);

        $snapshotLowFights = [
            'win_rate' => 80,
            'total_fights' => 5,
            'element_levels' => [],
        ];
        $snapshotOk = [
            'win_rate' => 80,
            'total_fights' => 25,
            'element_levels' => [],
        ];

        $this->assertNull($this->invokePrivate($service, 'resolveSnapshotValue', [
            'combat_win_rate', $snapshotLowFights, $user, $ach,
        ]));
        $this->assertSame(80, $this->invokePrivate($service, 'resolveSnapshotValue', [
            'combat_win_rate', $snapshotOk, $user, $ach,
        ]));
    }

    public function testIsSchemaReadyReturnsBoolean(): void
    {
        $this->assertIsBool(AchievementService::isSchemaReady());
    }
}
