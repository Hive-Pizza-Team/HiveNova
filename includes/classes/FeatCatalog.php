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

    public const FIRST_SMALL_CARGO = 'feat_first_small_cargo';
    public const FIRST_LARGE_CARGO = 'feat_first_large_cargo';
    public const FIRST_LIGHT_FIGHTER = 'feat_first_light_fighter';
    public const FIRST_HEAVY_FIGHTER = 'feat_first_heavy_fighter';
    public const FIRST_CRUISER = 'feat_first_cruiser';
    public const FIRST_BATTLESHIP = 'feat_first_battleship';
    public const FIRST_COLONY_SHIP = 'feat_first_colony_ship';
    public const FIRST_RECYCLER = 'feat_first_recycler';
    public const FIRST_SPY_PROBE = 'feat_first_spy_probe';
    public const FIRST_BOMBER = 'feat_first_bomber';
    public const FIRST_SOLAR_SATELLITE = 'feat_first_solar_satellite';
    public const FIRST_DESTROYER = 'feat_first_destroyer';
    public const FIRST_BATTLE_CRUISER = 'feat_first_battle_cruiser';
    public const FIRST_BLACK_MOON = 'feat_first_black_moon';
    public const FIRST_BATTLE_TRANSPORTER = 'feat_first_battle_transporter';
    public const FIRST_AVATAR = 'feat_first_avatar';
    public const FIRST_BATTLE_RECYCLER = 'feat_first_battle_recycler';
    public const FIRST_PIZZABITS_COLLECTOR = 'feat_first_pizzabits_collector';

    public const HYPERSPACE_TECH_ID = 114;
    public const GRAVITON_TECH_ID = 199;
    public const DEATHSTAR_ID = 214;

    public const DEFEND_SHIP_THRESHOLD = 100;

    /**
     * @return array<int, string> elementId => featKey
     */
    public static function shipFeatKeys(): array
    {
        return [
            202 => self::FIRST_SMALL_CARGO,
            203 => self::FIRST_LARGE_CARGO,
            204 => self::FIRST_LIGHT_FIGHTER,
            205 => self::FIRST_HEAVY_FIGHTER,
            206 => self::FIRST_CRUISER,
            207 => self::FIRST_BATTLESHIP,
            208 => self::FIRST_COLONY_SHIP,
            209 => self::FIRST_RECYCLER,
            210 => self::FIRST_SPY_PROBE,
            211 => self::FIRST_BOMBER,
            212 => self::FIRST_SOLAR_SATELLITE,
            213 => self::FIRST_DESTROYER,
            214 => self::FIRST_DEATHSTAR,
            215 => self::FIRST_BATTLE_CRUISER,
            216 => self::FIRST_BLACK_MOON,
            217 => self::FIRST_BATTLE_TRANSPORTER,
            218 => self::FIRST_AVATAR,
            219 => self::FIRST_BATTLE_RECYCLER,
            220 => self::FIRST_PIZZABITS_COLLECTOR,
        ];
    }

    public static function isShipFeat(string $key): bool
    {
        return in_array($key, self::shipFeatKeys(), true);
    }

    public static function isHidden(string $key): bool
    {
        return (bool) (self::definition($key)['hidden'] ?? false);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::FIRST_SHIP,
            self::FIRST_SMALL_CARGO,
            self::FIRST_LARGE_CARGO,
            self::FIRST_LIGHT_FIGHTER,
            self::FIRST_HEAVY_FIGHTER,
            self::FIRST_CRUISER,
            self::FIRST_BATTLESHIP,
            self::FIRST_COLONY_SHIP,
            self::FIRST_RECYCLER,
            self::FIRST_SPY_PROBE,
            self::FIRST_BOMBER,
            self::FIRST_SOLAR_SATELLITE,
            self::FIRST_DESTROYER,
            self::FIRST_DEATHSTAR,
            self::FIRST_BATTLE_CRUISER,
            self::FIRST_BLACK_MOON,
            self::FIRST_BATTLE_TRANSPORTER,
            self::FIRST_AVATAR,
            self::FIRST_BATTLE_RECYCLER,
            self::FIRST_PIZZABITS_COLLECTOR,
            self::FIRST_COLONY,
            self::FIRST_EXPEDITION,
            self::FIRST_GRAVITON,
            self::FIRST_HYPERSPACE,
            self::FIRST_MOON,
            self::GIVE_MOON,
            self::MOON_DESTRUCTION,
            self::LOSE_DEATHSTAR,
            self::DEFEAT_DEATHSTAR,
            self::RAID_DEFENSES,
            self::DEFEND_100_SHIPS,
            self::ABANDON_PLANET,
            self::ABANDON_HOME,
        ];
    }

    /**
     * @return array{category: string, name_key: string, desc_key: string, sort_order: int, hidden: bool}
     */
    public static function definition(string $key): array
    {
        $map = [
            self::FIRST_SHIP => ['category' => 'fleet', 'sort_order' => 10],
            self::FIRST_SMALL_CARGO => ['category' => 'fleet', 'sort_order' => 11],
            self::FIRST_LARGE_CARGO => ['category' => 'fleet', 'sort_order' => 12],
            self::FIRST_LIGHT_FIGHTER => ['category' => 'fleet', 'sort_order' => 13],
            self::FIRST_HEAVY_FIGHTER => ['category' => 'fleet', 'sort_order' => 14],
            self::FIRST_CRUISER => ['category' => 'fleet', 'sort_order' => 15],
            self::FIRST_BATTLESHIP => ['category' => 'fleet', 'sort_order' => 16],
            self::FIRST_COLONY_SHIP => ['category' => 'fleet', 'sort_order' => 17],
            self::FIRST_RECYCLER => ['category' => 'fleet', 'sort_order' => 18],
            self::FIRST_SPY_PROBE => ['category' => 'fleet', 'sort_order' => 19],
            self::FIRST_BOMBER => ['category' => 'fleet', 'sort_order' => 20],
            self::FIRST_SOLAR_SATELLITE => ['category' => 'fleet', 'sort_order' => 21],
            self::FIRST_DESTROYER => ['category' => 'fleet', 'sort_order' => 22],
            self::FIRST_DEATHSTAR => ['category' => 'fleet', 'sort_order' => 23],
            self::FIRST_BATTLE_CRUISER => ['category' => 'fleet', 'sort_order' => 24],
            self::FIRST_BLACK_MOON => ['category' => 'fleet', 'sort_order' => 25, 'hidden' => true],
            self::FIRST_BATTLE_TRANSPORTER => ['category' => 'fleet', 'sort_order' => 26, 'hidden' => true],
            self::FIRST_AVATAR => ['category' => 'fleet', 'sort_order' => 27, 'hidden' => true],
            self::FIRST_BATTLE_RECYCLER => ['category' => 'fleet', 'sort_order' => 28, 'hidden' => true],
            self::FIRST_PIZZABITS_COLLECTOR => ['category' => 'fleet', 'sort_order' => 29, 'hidden' => true],
            self::FIRST_COLONY => ['category' => 'empire', 'sort_order' => 40],
            self::FIRST_EXPEDITION => ['category' => 'exploration', 'sort_order' => 50],
            self::FIRST_HYPERSPACE => ['category' => 'research', 'sort_order' => 60],
            self::FIRST_GRAVITON => ['category' => 'research', 'sort_order' => 70],
            self::FIRST_MOON => ['category' => 'empire', 'sort_order' => 80],
            self::GIVE_MOON => ['category' => 'combat', 'sort_order' => 90],
            self::MOON_DESTRUCTION => ['category' => 'combat', 'sort_order' => 100],
            self::LOSE_DEATHSTAR => ['category' => 'combat', 'sort_order' => 110],
            self::DEFEAT_DEATHSTAR => ['category' => 'combat', 'sort_order' => 120],
            self::RAID_DEFENSES => ['category' => 'combat', 'sort_order' => 130],
            self::DEFEND_100_SHIPS => ['category' => 'combat', 'sort_order' => 140],
            self::ABANDON_PLANET => ['category' => 'empire', 'sort_order' => 150],
            self::ABANDON_HOME => ['category' => 'empire', 'sort_order' => 160],
        ];

        $meta = $map[$key] ?? ['category' => 'empire', 'sort_order' => 999];

        return [
            'category'   => $meta['category'],
            'name_key'   => $key . '_name',
            'desc_key'   => $key . '_desc',
            'sort_order' => $meta['sort_order'],
            'hidden'     => (bool) ($meta['hidden'] ?? false),
        ];
    }

    /**
     * @param array<string, bool> $hasShipByFeatKey featKey => someone in universe owns that ship
     */
    public static function initialStatus(
        string $key,
        bool $trackingFromStart,
        bool $hasGraviton,
        bool $hasHyperspace,
        bool $hasMoon,
        array $hasShipByFeatKey = [],
    ): string {
        if ($trackingFromStart) {
            return self::STATUS_OPEN;
        }

        if (array_key_exists($key, $hasShipByFeatKey)) {
            return $hasShipByFeatKey[$key] ? self::STATUS_UNKNOWN : self::STATUS_OPEN;
        }

        return match ($key) {
            self::FIRST_GRAVITON => $hasGraviton ? self::STATUS_UNKNOWN : self::STATUS_OPEN,
            self::FIRST_HYPERSPACE => $hasHyperspace ? self::STATUS_UNKNOWN : self::STATUS_OPEN,
            self::FIRST_MOON => $hasMoon ? self::STATUS_UNKNOWN : self::STATUS_OPEN,
            default => self::STATUS_UNKNOWN,
        };
    }
}
