<?php

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package HiveNova
 * @author HiveTrending
 * @copyright 2025 Hive Pizza Team
 * @license MIT
 * @version 1.8.0
 * @link https://github.com/Hive-Pizza-Team/HiveNova
 */

class ShowVizPage extends AbstractGamePage
{
    function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        global $USER;
        $config    = Config::get($USER['universe']);

        // startGroup,startCircle,startPoint,endGroup,endCircle,endPoint,duration,color
        $fleetData = Database::get()->select(
            'SELECT fleet_start_galaxy as startGroup, 
            CASE
                WHEN fleet_start_system < 6 THEN fleet_start_system + FLOOR(RAND() * 6)
                WHEN fleet_start_system > :maxSystem - 6 THEN fleet_start_system - FLOOR(RAND() * 6)
                ELSE fleet_start_system + FLOOR(RAND() * 11) - 5
            END AS startCircle,

            fleet_start_planet as startPoint,
            fleet_end_galaxy as endGroup,

            CASE
                WHEN fleet_end_system < 6 THEN fleet_end_system + FLOOR(RAND() * 6)
                WHEN fleet_end_system > :maxSystem - 6 THEN fleet_end_system - FLOOR(RAND() * 6)
                ELSE fleet_end_system + FLOOR(RAND() * 11) - 5
            END AS endCircle,

            fleet_end_planet as endPoint,
            (fleet_end_time - fleet_start_time)/100 as duration
			FROM %%FLEETS%%
            ORDER BY fleet_id
            LIMIT 100',
            array(
                #':maxGalaxy'    => $config->max_galaxy,
                ':maxSystem'    => $config->max_system,
                #':maxPlanets'   => $config->max_planets
            )
        );
        $fleetsJson = json_encode($fleetData);

        $this->assign(array(
            'fleetsJson'                => $fleetsJson
        ));

        $this->display('page.viz.default.tpl');
    }
}
