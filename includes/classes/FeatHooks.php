<?php

namespace HiveNova\Core;

class FeatHooks
{
    /**
     * @param array<int, int|float> $builded
     */
    public static function afterBuildCompleted(array $builded, array $user): void
    {
        $userId = (int) ($user['id'] ?? 0);
        $universe = (int) ($user['universe'] ?? 0);
        if ($userId <= 0 || $universe <= 0) {
            return;
        }

        foreach ($builded as $elementId => $count) {
            if (empty($count)) {
                continue;
            }
            $elementId = (int) $elementId;
            if ($elementId === FeatCatalog::GRAVITON_TECH_ID) {
                FeatService::tryClaim($universe, FeatCatalog::FIRST_GRAVITON, $userId);
            }
            if ($elementId === FeatCatalog::HYPERSPACE_TECH_ID) {
                FeatService::tryClaim($universe, FeatCatalog::FIRST_HYPERSPACE, $userId);
            }
            $shipFeat = FeatCatalog::shipFeatKeys()[$elementId] ?? null;
            if ($shipFeat !== null) {
                FeatService::tryClaim($universe, $shipFeat, $userId);
                FeatService::tryClaim($universe, FeatCatalog::FIRST_SHIP, $userId);
            }
        }
    }

    /**
     * @param array<int|string, mixed> $userAttack
     * @param array<int|string, mixed> $userDefend
     */
    public static function afterCombat(
        array $userAttack,
        array $userDefend,
        bool $attackerWon,
        bool $defenderWon,
        int $universe,
        int $attackerShipCount,
        int $defenderDefenseCount,
        bool $attackerHadDeathstar,
        bool $defenderHadDeathstar,
    ): void {
        $attackerId = (int) (array_key_first($userAttack) ?? 0);
        $defenderId = (int) (array_key_first($userDefend) ?? 0);

        if ($attackerWon && $defenderDefenseCount > 0 && $attackerId > 0) {
            FeatService::tryClaim($universe, FeatCatalog::RAID_DEFENSES, $attackerId);
        }
        if ($defenderWon && $attackerShipCount >= FeatCatalog::DEFEND_SHIP_THRESHOLD && $defenderId > 0) {
            FeatService::tryClaim($universe, FeatCatalog::DEFEND_100_SHIPS, $defenderId);
        }
        if ($defenderWon && $attackerHadDeathstar && $attackerId > 0) {
            FeatService::tryClaim($universe, FeatCatalog::LOSE_DEATHSTAR, $attackerId);
        }
        if ($attackerWon && $defenderHadDeathstar && $attackerId > 0) {
            FeatService::tryClaim($universe, FeatCatalog::DEFEAT_DEATHSTAR, $attackerId);
        }
    }

    public static function afterMoonCreated(int $universe, int $ownerId, int $attackerId): void
    {
        if ($ownerId > 0) {
            FeatService::tryClaim($universe, FeatCatalog::FIRST_MOON, $ownerId);
        }
        if ($attackerId > 0 && $attackerId !== $ownerId) {
            FeatService::tryClaim($universe, FeatCatalog::GIVE_MOON, $attackerId);
        }
    }

    public static function afterMoonDestroyed(int $universe, int $attackerId): void
    {
        if ($attackerId > 0) {
            FeatService::tryClaim($universe, FeatCatalog::MOON_DESTRUCTION, $attackerId);
        }
    }

    public static function afterColonisation(int $universe, int $userId): void
    {
        FeatService::tryClaim($universe, FeatCatalog::FIRST_COLONY, $userId);
    }

    public static function afterExpedition(int $universe, int $userId): void
    {
        FeatService::tryClaim($universe, FeatCatalog::FIRST_EXPEDITION, $userId);
    }

    public static function afterAbandonPlanet(int $universe, int $userId, bool $wasHome): void
    {
        FeatService::tryClaim($universe, FeatCatalog::ABANDON_PLANET, $userId);
        if ($wasHome) {
            FeatService::tryClaim($universe, FeatCatalog::ABANDON_HOME, $userId);
        }
    }

    public static function attackerShipCount(string $fleetArray): int
    {
        $units = FleetFunctions::unserialize($fleetArray);
        $count = 0;
        foreach ($units as $elementId => $amount) {
            if ((int) $elementId === 212) {
                continue;
            }
            $count += (int) $amount;
        }

        return $count;
    }

    public static function fleetHasDeathstar(string $fleetArray): bool
    {
        $units = FleetFunctions::unserialize($fleetArray);

        return (int) ($units[FeatCatalog::DEATHSTAR_ID] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $planet
     */
    public static function planetDefenseCount(array $planet): int
    {
        global $reslist, $resource;
        $count = 0;
        foreach ($reslist['defense'] ?? [] as $elementId) {
            $column = $resource[$elementId] ?? null;
            if (is_string($column) && !empty($planet[$column])) {
                $count += (int) $planet[$column];
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $planet
     */
    public static function planetHasDeathstar(array $planet): bool
    {
        global $resource;
        $column = $resource[FeatCatalog::DEATHSTAR_ID] ?? 'dearth_star';

        return (int) ($planet[$column] ?? 0) > 0;
    }
}
