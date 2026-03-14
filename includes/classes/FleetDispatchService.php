<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\Universe;
use HiveNova\Repository\UserRepository;

/**
 * FleetDispatchService
 *
 * Encapsulates core business logic for fleet dispatch (step 3).
 * Validation methods throw \RuntimeException on failure; the page layer
 * catches them and renders printMessage().
 */
class FleetDispatchService
{
    /**
     * Validate target coordinates, resource transport constraints, ship availability,
     * and deuterium/storage sufficiency.
     *
     * @param array $params  Keys: targetMission, targetGalaxy, targetSystem, targetPlanet,
     *                       targetType, TransportMetal, TransportCrystal, TransportDeuterium,
     *                       WantedResourceAmount, markettype, fleetArray, fleetStorage,
     *                       fleetSpeed, distance, consumption (pre-calculated)
     * @param array $USER
     * @param array $PLANET
     * @throws \RuntimeException
     */
    public static function validateTarget(array $params, array $USER, array $PLANET): void
    {
        global $resource, $LNG;

        $config = Config::get();

        $targetMission      = $params['targetMission'];
        $targetGalaxy       = $params['targetGalaxy'];
        $targetSystem       = $params['targetSystem'];
        $targetPlanet       = $params['targetPlanet'];
        $targetType         = $params['targetType'];
        $TransportMetal     = $params['TransportMetal'];
        $TransportCrystal   = $params['TransportCrystal'];
        $TransportDeuterium = $params['TransportDeuterium'];
        $WantedResourceAmount = $params['WantedResourceAmount'];
        $markettype         = $params['markettype'];
        $fleetArray         = $params['fleetArray'];
        $fleetStorage       = $params['fleetStorage'];
        $consumption        = $params['consumption'];

        // Same planet
        if ($PLANET['galaxy'] == $targetGalaxy && $PLANET['system'] == $targetSystem &&
            $PLANET['planet'] == $targetPlanet && $PLANET['planet_type'] == $targetType) {
            throw new \RuntimeException($LNG['fl_error_same_planet']);
        }

        // Coordinate range
        if ($targetGalaxy < 1 || $targetGalaxy > $config->max_galaxy ||
            $targetSystem < 1 || $targetSystem > $config->max_system ||
            $targetPlanet < 1 || $targetPlanet > ($config->max_planets + 2) ||
            ($targetType !== 1 && $targetType !== 2 && $targetType !== 3)) {
            throw new \RuntimeException($LNG['fl_invalid_target']);
        }

        // Transport must contain resources
        if (($targetMission == 3 || ($targetMission == 16 && $markettype == 0)) &&
            $TransportMetal + $TransportCrystal + $TransportDeuterium < 1) {
            throw new \RuntimeException($LNG['fl_no_noresource']);
        }

        // Market type 1 cannot contain resources
        if ($targetMission == 16 && $markettype == 1 &&
            $TransportMetal + $TransportCrystal + $TransportDeuterium != 0) {
            throw new \RuntimeException($LNG['fl_resources']);
        }

        // Market exchange amount validation
        if ($targetMission == 16 && $WantedResourceAmount < 1) {
            throw new \RuntimeException($LNG['fl_no_noresource_exchange']);
        }

        if ($targetMission == 16 && $WantedResourceAmount > pow(10, 50)) {
            throw new \RuntimeException($LNG['fl_invalid_mission']);
        }

        // Ship availability
        foreach ($fleetArray as $Ship => $Count) {
            if ($Count > $PLANET[$resource[$Ship]]) {
                throw new \RuntimeException($LNG['fl_not_all_ship_avalible']);
            }
        }

        // Deuterium check
        if ($PLANET[$resource[903]] < $consumption) {
            throw new \RuntimeException($LNG['fl_not_enough_deuterium']);
        }

        // Storage check (fleetStorage has already had consumption deducted by caller)
        $fleetResource = [
            901 => min($TransportMetal,     floor($PLANET[$resource[901]])),
            902 => min($TransportCrystal,   floor($PLANET[$resource[902]])),
            903 => min($TransportDeuterium, floor($PLANET[$resource[903]] - $consumption)),
        ];

        $storageNeeded = array_sum($fleetResource);
        if ($storageNeeded > $fleetStorage) {
            throw new \RuntimeException($LNG['fl_not_enough_space']);
        }
    }

    /**
     * Validate fleet slot availability.
     *
     * @param array $USER
     * @param int   $fleetGroup  ACS group ID (non-zero means ACS join, counts differently)
     * @throws \RuntimeException
     */
    public static function validateSlots(array $USER, int $fleetGroup): void
    {
        global $LNG;

        $actualFleets = FleetFunctions::GetCurrentFleets($USER['id']);

        if (FleetFunctions::GetMaxFleetSlots($USER) <= $actualFleets) {
            throw new \RuntimeException($LNG['fl_no_slots']);
        }
    }

    /**
     * Validate that the chosen mission is feasible given target planet/player state.
     *
     * @param array|null $targetPlanetData  Planet row or null/empty if not found
     * @param array      $targetPlayerData  Player row (may be synthetic for colony/expedition/market)
     * @param int        $mission
     * @param array      $USER
     * @param array      $fleetData  Keys: fleetArray, fleetGroup, targetGalaxy, targetSystem,
     *                               targetPlanet, targetType, stayTime, availableMissions
     * @param Config     $config
     * @throws \RuntimeException
     */
    public static function validateMission(
        ?array $targetPlanetData,
        array $targetPlayerData,
        int $mission,
        array $USER,
        array $fleetData,
        Config $config
    ): void {
        global $resource, $LNG;

        $fleetArray        = $fleetData['fleetArray'];
        $fleetGroup        = $fleetData['fleetGroup'];
        $targetType        = $fleetData['targetType'];
        $stayTime          = $fleetData['stayTime'];
        $availableMissions = $fleetData['availableMissions'];

        $db = Database::get();

        // Colonize: target must be empty and must target a planet slot
        if ($mission == 7) {
            if (!empty($targetPlanetData)) {
                throw new \RuntimeException($LNG['fl_target_exists']);
            }

            if ($targetType != 1) {
                throw new \RuntimeException($LNG['fl_only_planets_colonizable']);
            }
        }

        // For colonize / expedition / market the planet not existing is expected
        if ($mission == 7 || $mission == 15 || $mission == 16) {
            // synthetic target already set by caller — nothing to check here
        } else {
            if (!empty($targetPlanetData['destruyed'])) {
                throw new \RuntimeException($LNG['fl_no_target']);
            }

            if (empty($targetPlanetData)) {
                throw new \RuntimeException($LNG['fl_no_target']);
            }
        }

        // Empty target player
        if (empty($targetPlayerData)) {
            throw new \RuntimeException($LNG['fl_empty_target']);
        }

        // Mission not in available list
        if (!in_array($mission, $availableMissions['MissionSelector'])) {
            throw new \RuntimeException($LNG['fl_invalid_mission']);
        }

        // Vacation mode (except espionage = 8)
        if ($mission != 8 && IsVacationMode($targetPlayerData)) {
            throw new \RuntimeException($LNG['fl_target_exists']);
        }

        // Expedition slot (DM mission = 11)
        if ($mission == 11) {
            $activeExpedition = FleetFunctions::GetCurrentFleets($USER['id'], 11, true);
            $maxExpedition    = FleetFunctions::getDMMissionLimit($USER);

            if ($activeExpedition >= $maxExpedition) {
                throw new \RuntimeException($LNG['fl_no_expedition_slot']);
            }
        } elseif ($mission == 15) {
            $activeExpedition = FleetFunctions::GetCurrentFleets($USER['id'], 15, true);
            $maxExpedition    = FleetFunctions::getExpeditionLimit($USER);

            if ($activeExpedition >= $maxExpedition) {
                throw new \RuntimeException($LNG['fl_no_expedition_slot']);
            }
        }

        // Bash protection (attack / ACS / espionage-attack missions)
        if ($mission == 1 || $mission == 2 || $mission == 9) {
            if (FleetFunctions::CheckBash($targetPlanetData['id'])) {
                throw new \RuntimeException($LNG['fl_bash_protection']);
            }
        }

        // Admin attack protection & noob protection
        if ($mission == 1 || $mission == 2 || $mission == 5 || $mission == 6 || $mission == 9) {
            if (Config::get()->adm_attack == 1 && $targetPlayerData['authattack'] > $USER['authlevel']) {
                throw new \RuntimeException($LNG['fl_admin_attack']);
            }

            $sql = 'SELECT total_points FROM %%STATPOINTS%% WHERE id_owner = :userId AND stat_type = :statType';
            $USER += Database::get()->selectSingle($sql, [':userId' => $USER['id'], ':statType' => 1]);

            $IsNoobProtec = CheckNoobProtec($USER, $targetPlayerData, $targetPlayerData);

            if ($IsNoobProtec['NoobPlayer']) {
                throw new \RuntimeException($LNG['fl_player_is_noob']);
            }

            if ($IsNoobProtec['StrongPlayer']) {
                throw new \RuntimeException($LNG['fl_player_is_strong']);
            }
        }

        // Buddy check for espionage (mission 5)
        if ($mission == 5) {
            if ($targetPlayerData['ally_id'] != $USER['ally_id'] || $USER['ally_id'] == 0) {
                $sql = "SELECT COUNT(*) as state FROM %%BUDDY%%
                    WHERE id NOT IN (SELECT id FROM %%BUDDY_REQUEST%% WHERE %%BUDDY_REQUEST%%.id = %%BUDDY%%.id) AND
                    (owner = :ownerID AND sender = :userID) OR (owner = :userID AND sender = :ownerID);";
                $buddy = $db->selectSingle($sql, [
                    ':ownerID' => $targetPlayerData['id'],
                    ':userID'  => $USER['id'],
                ], 'state');

                if ($buddy == 0) {
                    throw new \RuntimeException($LNG['fl_no_same_alliance']);
                }
            }
        }

        // Tech superiority check (mission 17 — space piracy or similar)
        if ($mission == 17) {
            $attack    = $USER[$resource[109]] * 10 + $USER['factor']['Attack'] * 100;
            $defensive = $USER[$resource[110]] * 10 + $USER['factor']['Defensive'] * 100;
            $shield    = $USER[$resource[111]] * 10 + $USER['factor']['Shield'] * 100;

            $targetPlayerData['factor'] = getFactors($targetPlayerData);

            $attack_targ    = $targetPlayerData[$resource[109]] * 10 + $targetPlayerData['factor']['Attack'] * 100;
            $defensive_targ = $targetPlayerData[$resource[110]] * 10 + $targetPlayerData['factor']['Defensive'] * 100;
            $shield_targ    = $targetPlayerData[$resource[111]] * 10 + $targetPlayerData['factor']['Shield'] * 100;

            if ($attack < $attack_targ || $defensive < $defensive_targ || $shield < $shield_targ) {
                throw new \RuntimeException($LNG['fl_stronger_techs']);
            }
        }

        // Stay/hold time validation
        if ($mission == 5 || $mission == 11 || $mission == 15 || $mission == 16) {
            if (!isset($availableMissions['StayBlock'][$stayTime])) {
                throw new \RuntimeException($LNG['fl_hold_time_not_exists']);
            }
        }
    }

    /**
     * Calculate flight duration and fuel consumption.
     *
     * @param array $fleetArray
     * @param int   $distance
     * @param float $fleetSpeed   Speed percentage chosen by player
     * @param array $USER
     * @return array  ['duration' => int, 'consumption' => float]
     */
    public static function calculateMetrics(array $fleetArray, int $distance, float $fleetSpeed, array $USER): array
    {
        $fleetMaxSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
        $SpeedFactor   = FleetFunctions::GetGameSpeedFactor();
        $duration      = FleetFunctions::GetMissionDuration($fleetSpeed, $fleetMaxSpeed, $distance, $SpeedFactor, $USER);
        $consumption   = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $SpeedFactor);

        return [
            'duration'      => $duration,
            'consumption'   => $consumption,
            'fleetMaxSpeed' => $fleetMaxSpeed,
        ];
    }

    /**
     * Perform the fleet dispatch: deduct resources, insert fleet/trade rows, commit.
     * Updates $PLANET by reference to reflect deducted resources.
     *
     * @param array $fleetData  All data needed for DB operations — keys:
     *                          fleetArray, targetMission, USER, PLANET (by ref via param),
     *                          targetPlanetData, targetGalaxy, targetSystem, targetPlanet,
     *                          targetType, fleetResource, fleetStartTime, fleetStayTime,
     *                          fleetEndTime, fleetGroup, consumption,
     *                          markettype, WantedResourceType, WantedResourceAmount,
     *                          maxFlightTime, visibility
     * @param array &$PLANET    Current planet array — updated in-place after deduction
     * @return int              The new fleet_id
     * @throws \RuntimeException  On insufficient resources (re-checked under lock)
     */
    public static function dispatch(array $fleetData, array &$PLANET): int
    {
        global $resource, $LNG;

        $db = Database::get();

        $fleetArray        = $fleetData['fleetArray'];
        $targetMission     = $fleetData['targetMission'];
        $USER              = $fleetData['USER'];
        $targetPlanetData  = $fleetData['targetPlanetData'];
        $targetGalaxy      = $fleetData['targetGalaxy'];
        $targetSystem      = $fleetData['targetSystem'];
        $targetPlanet      = $fleetData['targetPlanet'];
        $targetType        = $fleetData['targetType'];
        $fleetResource     = $fleetData['fleetResource'];
        $fleetStartTime    = $fleetData['fleetStartTime'];
        $fleetStayTime     = $fleetData['fleetStayTime'];
        $fleetEndTime      = $fleetData['fleetEndTime'];
        $fleetGroup        = $fleetData['fleetGroup'];
        $consumption       = $fleetData['consumption'];
        $markettype        = $fleetData['markettype'];
        $WantedResourceType   = $fleetData['WantedResourceType'];
        $WantedResourceAmount = $fleetData['WantedResourceAmount'];
        $maxFlightTime     = $fleetData['maxFlightTime'];
        $visibility        = $fleetData['visibility'];

        $db->beginTransaction();
        try {
            $lockedPlanet = $db->selectSingle(
                'SELECT metal, crystal, deuterium FROM %%PLANETS%% WHERE id = :id FOR UPDATE',
                [':id' => $PLANET['id']]
            );
            $deutNeeded = $fleetResource[903] + $consumption;
            if ($lockedPlanet[$resource[901]] < $fleetResource[901] ||
                $lockedPlanet[$resource[902]] < $fleetResource[902] ||
                $lockedPlanet[$resource[903]] < $deutNeeded) {
                $db->rollback();
                throw new \RuntimeException($LNG['fl_not_enough_resource']);
            }

            $db->update(
                'UPDATE %%PLANETS%% SET
                metal     = metal     - :metal,
                crystal   = crystal   - :crystal,
                deuterium = deuterium - :deuterium
                WHERE id = :planetId',
                [
                    ':metal'     => $fleetResource[901],
                    ':crystal'   => $fleetResource[902],
                    ':deuterium' => $deutNeeded,
                    ':planetId'  => $PLANET['id'],
                ]
            );

            $fleet_id = FleetFunctions::sendFleet(
                $fleetArray, $targetMission,
                $USER['id'], $PLANET['id'],
                $PLANET['galaxy'], $PLANET['system'], $PLANET['planet'], $PLANET['planet_type'],
                $targetPlanetData['id_owner'], $targetPlanetData['id'],
                $targetGalaxy, $targetSystem, $targetPlanet, $targetType,
                $fleetResource,
                $fleetStartTime, $fleetStayTime, $fleetEndTime,
                $fleetGroup, 0
            );

            if ($targetMission == 16) {
                $sql = 'INSERT INTO %%TRADES%% SET
                    transaction_type    = :transaction,
                    seller_fleet_id     = :sellerFleet,
                    filter_visibility   = :visibility,
                    filter_flighttime   = :flightTime,
                    ex_resource_type    = :resType,
                    ex_resource_amount  = :resAmount;';

                $db->insert($sql, [
                    ':transaction' => $markettype,
                    ':sellerFleet' => $fleet_id,
                    ':resType'     => $WantedResourceType,
                    ':resAmount'   => $WantedResourceAmount,
                    ':flightTime'  => $maxFlightTime * 3600,
                    ':visibility'  => $visibility,
                ]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }

        // Update the in-memory planet array to reflect the deduction
        $PLANET[$resource[901]] -= $fleetResource[901];
        $PLANET[$resource[902]] -= $fleetResource[902];
        $PLANET[$resource[903]] -= $deutNeeded;

        return $fleet_id;
    }
}
