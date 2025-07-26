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
	{ // startGroup,startCircle,startPoint,endGroup,endCircle,endPoint,duration,color
        $fleetData = Database::get()->select(
			'SELECT fleet_start_galaxy as startGroup, 
            fleet_start_system as startCircle,
            fleet_start_planet as startPoint,
            fleet_end_galaxy as endGroup,
            fleet_end_system as endCircle,
            fleet_end_planet as endPoint,
            (fleet_end_time - fleet_start_time)/100 as duration
			FROM %%FLEETS%%'
		);
        $fleetsJson = json_encode($fleetData);

        $this->assign(array(
            'fleetsJson'				=> $fleetsJson
        ));

        $this->display('page.viz.default.tpl');
    }
}