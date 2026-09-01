<?php

namespace HiveNova\Core;

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Repository\PlanetRepository;
use HiveNova\Repository\UserRepository;

class FleetTargetInfoService
{
    /**
     * @return array{
     *     coords: string,
     *     locationName: ?string,
     *     ownerUsername: ?string,
     *     allyTag: ?string,
     *     typeLabel: ?string
     * }
     */
    public static function resolve(
        int $galaxy,
        int $system,
        int $planet,
        int $targetType,
        int $universe
    ): array {
        global $LNG;

        $coords = $galaxy . ':' . $system . ':' . $planet;
        $empty = [
            'coords'          => $coords,
            'locationName'    => null,
            'ownerUsername'   => null,
            'allyTag'         => null,
            'typeLabel'       => null,
        ];

        $maxPlanets = (int) Config::get()->max_planets;
        if ($planet === $maxPlanets + 1) {
            return array_merge($empty, [
                'typeLabel' => $LNG['type_mission_15'] ?? 'Expedition',
            ]);
        }
        if ($planet === $maxPlanets + 2) {
            return array_merge($empty, [
                'typeLabel' => $LNG['type_mission_16'] ?? 'Market',
            ]);
        }

        $planetType = $targetType === 2 ? 1 : $targetType;
        $planetRow = PlanetRepository::getPlanetByCoords($galaxy, $system, $planet, $planetType, $universe);
        if ($planetRow === null) {
            return array_merge($empty, [
                'typeLabel' => $LNG['fl_target_uninhabited'] ?? 'Uninhabited',
            ]);
        }

        $owner = !empty($planetRow['id_owner'])
            ? UserRepository::getUserById((int) $planetRow['id_owner'])
            : null;

        $allyTag = null;
        if (is_array($owner) && !empty($owner['ally_id'])) {
            $allyTag = Database::get()->selectSingle(
                'SELECT ally_tag FROM %%ALLIANCE%% WHERE id = :id;',
                [':id' => (int) $owner['ally_id']],
                'ally_tag'
            ) ?: null;
        }

        if ($targetType === 2) {
            return [
                'coords'          => $coords,
                'locationName'    => null,
                'ownerUsername'   => is_array($owner) ? ($owner['username'] ?? null) : null,
                'allyTag'         => $allyTag,
                'typeLabel'       => $LNG['type_planet_2'] ?? 'Debris Field',
            ];
        }

        return [
            'coords'          => $coords,
            'locationName'    => $planetRow['name'] ?? null,
            'ownerUsername'   => is_array($owner) ? ($owner['username'] ?? null) : null,
            'allyTag'         => $allyTag,
            'typeLabel'       => $targetType === 3 ? ($LNG['type_planet_3'] ?? 'Moon') : null,
        ];
    }

    /**
     * @param array{
     *     coords: string,
     *     locationName: ?string,
     *     ownerUsername: ?string,
     *     allyTag: ?string,
     *     typeLabel: ?string
     * } $info
     */
    public static function formatLabel(array $info): string
    {
        $parts = [];

        if (!empty($info['locationName'])) {
            $parts[] = (string) $info['locationName'];
        } elseif (!empty($info['typeLabel'])) {
            $parts[] = (string) $info['typeLabel'];
        }

        if (!empty($info['ownerUsername'])) {
            $owner = (string) $info['ownerUsername'];
            if (!empty($info['allyTag'])) {
                $owner .= ' (' . (string) $info['allyTag'] . ')';
            }
            $parts[] = $owner;
        }

        $coords = '[' . $info['coords'] . ']';

        return $parts !== [] ? implode(' — ', $parts) . ' ' . $coords : $coords;
    }
}
