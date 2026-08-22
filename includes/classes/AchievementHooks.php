<?php

namespace HiveNova\Core;

/**
 * Thin hooks for achievement events (keeps mission/page classes testable).
 */
class AchievementHooks
{
    /**
     * @param array<int|string, mixed> $userAttack
     * @param array<int|string, mixed> $userDefend
     */
    public static function afterCombat(array $userAttack, array $userDefend, string $attackStatus, string $defendStatus): void
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS)) {
            return;
        }

        AchievementService::recordCombatAfterBattle(array_keys($userAttack), $attackStatus === 'wons');
        AchievementService::recordCombatAfterBattle(array_keys($userDefend), $defendStatus === 'wons');
    }

    /**
     * @param array<int|string, mixed> $userAttack
     * @param array<int|string, mixed> $userDefend
     */
    public static function afterCombatWithFeats(
        array $userAttack,
        array $userDefend,
        string $attackStatus,
        string $defendStatus,
        int $universe,
        int $attackerShipCount,
        int $defenderDefenseCount,
        bool $attackerHadDeathstar,
        bool $defenderHadDeathstar,
    ): void {
        self::afterCombat($userAttack, $userDefend, $attackStatus, $defendStatus);
        FeatHooks::afterCombat(
            $userAttack,
            $userDefend,
            $attackStatus === 'wons',
            $defendStatus === 'wons',
            $universe,
            $attackerShipCount,
            $defenderDefenseCount,
            $attackerHadDeathstar,
            $defenderHadDeathstar
        );
    }

    public static function afterColonisation(int $userId): void
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS)) {
            return;
        }

        AchievementService::record($userId, 'planet_count', [], true);
    }

    public static function afterColonisationInUniverse(int $userId, int $universe): void
    {
        self::afterColonisation($userId);
        FeatHooks::afterColonisation($universe, $userId);
    }

    public static function afterExpedition(int $userId): void
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS)) {
            return;
        }

        AchievementService::record($userId, 'expedition_count', [], true);
    }

    public static function afterExpeditionInUniverse(int $userId, int $universe): void
    {
        self::afterExpedition($userId);
        FeatHooks::afterExpedition($universe, $userId);
    }

    /**
     * @param array<int, int|float> $builded
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     */
    public static function afterBuildCompleted(array $builded, array $user, array $planet): void
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS) || empty($builded)) {
            return;
        }

        AchievementService::recordBuildCompleted((int) $user['id'], $builded, $user, $planet);
        FeatHooks::afterBuildCompleted($builded, $user);
    }
}
