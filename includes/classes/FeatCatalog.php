<?php

namespace HiveNova\Core;

/**
 * Canonical feat keys and live-universe eligibility rules.
 */
class FeatCatalog
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLAIMED = 'claimed';

    public const FIRST_SHIP = 'feat_first_ship';
    public const FIRST_COLONY = 'feat_first_colony';
    public const FIRST_EXPEDITION = 'feat_first_expedition';
    public const FIRST_GRAVITON = 'feat_first_graviton';
    public const FIRST_HYPERSPACE = 'feat_first_hyperspace';
    public const FIRST_MOON = 'feat_first_moon';
    public const GIVE_MOON = 'feat_give_moon';
    public const MOON_DESTRUCTION = 'feat_moon_destruction';
    public const FIRST_DEATHSTAR = 'feat_first_deathstar';
    public const LOSE_DEATHSTAR = 'feat_lose_deathstar';
    public const DEFEAT_DEATHSTAR = 'feat_defeat_deathstar';
    public const RAID_DEFENSES = 'feat_raid_defenses';
    public const DEFEND_100_SHIPS = 'feat_defend_100_ships';
    public const ABANDON_PLANET = 'feat_abandon_planet';
    public const ABANDON_HOME = 'feat_abandon_home';

    public const HYPERSPACE_TECH_ID = 114;
    public const GRAVITON_TECH_ID = 199;
    public const DEATHSTAR_ID = 214;

    public const DEFEND_SHIP_THRESHOLD = 100;

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::FIRST_SHIP,
            self::FIRST_COLONY,
            self::FIRST_EXPEDITION,
            self::FIRST_GRAVITON,
            self::FIRST_HYPERSPACE,
            self::FIRST_MOON,
            self::GIVE_MOON,
            self::MOON_DESTRUCTION,
            self::FIRST_DEATHSTAR,
            self::LOSE_DEATHSTAR,
            self::DEFEAT_DEATHSTAR,
            self::RAID_DEFENSES,
            self::DEFEND_100_SHIPS,
            self::ABANDON_PLANET,
            self::ABANDON_HOME,
        ];
    }

    /**
     * @return array{category: string, name_key: string, desc_key: string, sort_order: int}
     */
    public static function definition(string $key): array
    {
        $map = [
            self::FIRST_SHIP => ['category' => 'fleet', 'sort_order' => 10],
            self::FIRST_COLONY => ['category' => 'empire', 'sort_order' => 20],
            self::FIRST_EXPEDITION => ['category' => 'exploration', 'sort_order' => 30],
            self::FIRST_HYPERSPACE => ['category' => 'research', 'sort_order' => 40],
            self::FIRST_GRAVITON => ['category' => 'research', 'sort_order' => 50],
            self::FIRST_MOON => ['category' => 'empire', 'sort_order' => 60],
            self::GIVE_MOON => ['category' => 'combat', 'sort_order' => 70],
            self::MOON_DESTRUCTION => ['category' => 'combat', 'sort_order' => 80],
            self::FIRST_DEATHSTAR => ['category' => 'fleet', 'sort_order' => 90],
            self::LOSE_DEATHSTAR => ['category' => 'combat', 'sort_order' => 100],
            self::DEFEAT_DEATHSTAR => ['category' => 'combat', 'sort_order' => 110],
            self::RAID_DEFENSES => ['category' => 'combat', 'sort_order' => 120],
            self::DEFEND_100_SHIPS => ['category' => 'combat', 'sort_order' => 130],
            self::ABANDON_PLANET => ['category' => 'empire', 'sort_order' => 140],
            self::ABANDON_HOME => ['category' => 'empire', 'sort_order' => 150],
        ];

        $meta = $map[$key] ?? ['category' => 'empire', 'sort_order' => 999];

        return [
            'category'   => $meta['category'],
            'name_key'   => $key . '_name',
            'desc_key'   => $key . '_desc',
            'sort_order' => $meta['sort_order'],
        ];
    }

    public static function initialStatus(
        string $key,
        bool $trackingFromStart,
        bool $hasGraviton,
        bool $hasHyperspace,
        bool $hasMoon,
    ): string {
        if ($trackingFromStart) {
            return self::STATUS_OPEN;
        }

        return match ($key) {
            self::FIRST_GRAVITON => $hasGraviton ? self::STATUS_UNKNOWN : self::STATUS_OPEN,
            self::FIRST_HYPERSPACE => $hasHyperspace ? self::STATUS_UNKNOWN : self::STATUS_OPEN,
            self::FIRST_MOON => $hasMoon ? self::STATUS_UNKNOWN : self::STATUS_OPEN,
            default => self::STATUS_UNKNOWN,
        };
    }
}
