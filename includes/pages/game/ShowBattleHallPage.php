<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\BattleHallService;
use HiveNova\Core\FeatService;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

class ShowBattleHallPage extends AbstractGamePage
{
	public static $requireModule = MODULE_BATTLEHALL;

	function __construct()
    {
		parent::__construct();
	}

	function show()
	{
		global $USER, $LNG;

		$tab = HTTP::_GP('tab', 'battles');
		if ($tab === 'feats' && isModuleAvailable(MODULE_FEATS)) {
			$LNG->includeData(['INGAME']);
			$feats = FeatService::listForUniverse(Universe::current());
			foreach ($feats as &$feat) {
				$isHiddenUnclaimed = !empty($feat['hidden'])
					&& ($feat['status'] ?? '') !== 'claimed';
				$feat['name'] = $isHiddenUnclaimed
					? ($LNG['feat_hidden_name'] ?? '???')
					: ($LNG[$feat['name_key']] ?? $feat['feat_key']);
				$feat['date'] = $feat['claimed_at'] > 0
					? _date($LNG['php_tdformat'], $feat['claimed_at'], $USER['timezone'])
					: '';
			}
			unset($feat);
			$this->assign([
				'battleHallTab' => 'feats',
				'FeatList'      => $feats,
				'TopKBList'     => [],
			]);
			$this->display('page.battleHall.default.tpl');
			return;
		}

		$top = (new BattleHallService())->listTopBattles((int) Universe::current());

		$TopKBList = [];
		foreach ($top as $data) {
			$TopKBList[] = [
				'result'   => $data['result'],
				'date'     => _date($LNG['php_tdformat'], $data['time'], $USER['timezone']),
				'time'     => TIMESTAMP - $data['time'],
				'units'    => $data['units'],
				'rid'      => $data['rid'],
				'attacker' => $data['attacker'],
				'defender' => $data['defender'],
			];
		}

		$this->assign([
			'TopKBList'     => $TopKBList,
			'battleHallTab' => 'battles',
			'FeatList'      => [],
		]);

		$this->display('page.battleHall.default.tpl');
	}
}
