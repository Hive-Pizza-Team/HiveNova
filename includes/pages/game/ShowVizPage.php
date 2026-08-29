<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
use HiveNova\Core\FleetVizSnapshotService;

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

		$snap = (new FleetVizSnapshotService())->forUniverse((int) $USER['universe']);
		$config = Config::get($USER['universe']);
		$version = (string) ($config->VERSION ?? '');

		$vizConfigJson = json_encode([
			'threeSrc'   => './scripts/threejs/three.min.js?v=' . substr($version, -4),
			'maxGalaxy'  => $snap['maxGalaxy'],
			'maxSystem'  => $snap['maxSystem'],
			'maxPlanets' => $snap['maxPlanets'],
			'fleets'     => $snap['fleets'],
		]);

		$this->assign([
			'vizConfigJson' => $vizConfigJson,
		]);

		$this->display('page.viz.default.tpl');
	}
}
