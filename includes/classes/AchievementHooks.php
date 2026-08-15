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

    public static function afterColonisation(int $userId): void
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS)) {
            return;
        }

        AchievementService::record($userId, 'planet_count', [], true);
    }

    public static function afterExpedition(int $userId): void
    {
        if (!isModuleAvailable(MODULE_ACHIEVEMENTS)) {
            return;
        }

        AchievementService::record($userId, 'expedition_count', [], true);
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
    }
}
