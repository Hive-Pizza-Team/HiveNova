<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\IncomingHostileFleetQuery;

class ShowAttackAlertPage extends AbstractGamePage
{
	public static $requireModule = 0;

	public function __construct()
	{
		parent::__construct();
	}

	public function show()
	{
		global $USER;

		$this->sendJSON([
			'count' => IncomingHostileFleetQuery::countForUser((int) $USER['id']),
		]);
	}
}
