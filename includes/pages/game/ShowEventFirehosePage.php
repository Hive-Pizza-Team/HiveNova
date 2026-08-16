<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\EventFirehoseFeed;
use HiveNova\Core\HTTP;

class ShowEventFirehosePage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		if (defined('AJAX_REQUEST') && AJAX_REQUEST) {
			$this->sendJSON(['events' => $this->loadEvents()]);
			return;
		}

		$this->tplObj->loadscript('event-firehose.js');
		$this->assign([
			'EventList' => $this->loadEvents(),
		]);
		$this->display('page.eventFirehose.default.tpl');
	}

	/**
	 * @return list<array{id: int, time: string, eventType: string, size: string, outcome: string}>
	 */
	protected function loadEvents(): array
	{
		global $USER, $LNG;

		$universe = (int) ($USER['universe'] ?? 0);
		$timezone = (string) ($USER['timezone'] ?? 'UTC');
		$sinceId  = HTTP::_GP('sinceId', 0);

		return EventFirehoseFeed::fetch($universe, $LNG, $timezone, $sinceId);
	}
}
