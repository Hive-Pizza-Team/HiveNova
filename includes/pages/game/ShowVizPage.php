<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
use HiveNova\Core\FleetVizSnapshotService;
use HiveNova\Core\HTTP;

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

	/**
	 * AJAX: fleet arcs for the map (loaded after first paint).
	 */
	public function fleets()
	{
		global $USER;

		$snap = (new FleetVizSnapshotService())->forUniverse((int) $USER['universe']);
		HTTP::sendHeader('Content-Type', 'application/json; charset=UTF-8');
		$this->sendJSON([
			'fleets' => $snap['fleets'],
		]);
	}

	public function show()
	{
		global $USER;

		$config = Config::get($USER['universe']);
		$version = (string) ($config->VERSION ?? '');

		// Dimensions only on first paint — fleets arrive via mode=fleets.
		$vizConfigJson = json_encode([
			'threeSrc'   => './scripts/threejs/three.min.js?v=' . substr($version, -4),
			'maxGalaxy'  => (int) $config->max_galaxy,
			'maxSystem'  => (int) $config->max_system,
			'maxPlanets' => (int) $config->max_planets,
			'fleetsUrl'  => 'game.php?page=viz&mode=fleets&ajax=1',
			'fleets'     => [],
		]);

		$this->assign([
			'vizConfigJson' => $vizConfigJson,
		]);

		$this->display('page.viz.default.tpl');
	}
}
