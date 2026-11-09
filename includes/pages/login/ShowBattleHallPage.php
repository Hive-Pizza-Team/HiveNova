<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\BattleHallService;
use HiveNova\Core\Universe;

class ShowBattleHallPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $LNG;

		$universe = (int) Universe::current();
		$hallRaw = (new BattleHallService())->listTopBattles($universe, 100);

		$hallList = [];
		foreach ($hallRaw as $hallRow) {
			$hallList[] = [
				'result'   => $hallRow['result'],
				'time'     => _date($LNG['php_tdformat'], $hallRow['time']),
				'units'    => $hallRow['units'],
				'rid'      => $hallRow['rid'],
				'attacker' => $hallRow['attacker'],
				'defender' => $hallRow['defender'],
				'reportUrl'=> 'index.php?page=battle&rid='.rawurlencode($hallRow['rid']),
			];
		}

		$this->assign([
			'universeSelect' => $this->getUniverseSelector(),
			'hallList'       => $hallList,
			'ctaHref'        => 'index.php?page=register&ref=1&src=battlehall',
		]);
		$this->display('page.battleHall.default.tpl');
	}
}
