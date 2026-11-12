<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\Session;
use HiveNova\Core\Universe;
use HiveNova\Core\UniverseSwitchService;

/**
 * In-game listing of linked universe accounts with one-click switch.
 */
class ShowUniverseSwitchPage extends AbstractGamePage
{
	public static $requireModule = 0;

	private UniverseSwitchService $switchService;

	function __construct()
	{
		parent::__construct();
		$this->switchService = new UniverseSwitchService();
	}

	function show()
	{
		global $USER, $LNG;

		$accounts = $this->switchService->listLinkedAccounts(
			$USER,
			Database::get(),
			Universe::availableUniverses(),
			static function (int $universeId): string {
				return (string) Config::get($universeId)->uni_name;
			}
		);

		foreach ($accounts as &$account) {
			$account['universeIdLabel'] = sprintf(
				(string) ($LNG['us_universe_id'] ?? 'Universe %s'),
				(string) $account['universeId']
			);
			$account['confirmMessage'] = sprintf(
				(string) ($LNG['us_confirm'] ?? 'Leave this universe session and enter %s as %s?'),
				(string) $account['universeName'],
				(string) $account['username']
			);
		}
		unset($account);

		$switchable = array_values(array_filter(
			$accounts,
			static fn (array $row): bool => !$row['isCurrent']
		));

		$this->assign([
			'accounts'   => $accounts,
			'switchable' => $switchable,
			'hasLinks'   => $switchable !== [],
			'csrfToken'  => $this->switchService->issueCsrfToken(),
		]);
		$this->display('page.universeSwitch.default.tpl');
	}

	function switchUniverse()
	{
		global $USER, $LNG;

		$invalidButtons = [[
			'label' => $LNG['lm_universe_switch'],
			'url'   => 'game.php?page=universeSwitch',
		]];

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
			|| !$this->switchService->validateCsrfToken(HTTP::_GP('csrf', ''))
		) {
			$this->printMessage(
				$LNG['cm_invalid_token'] ?? ($LNG['us_invalid'] ?? 'Invalid security token'),
				$invalidButtons
			);
			return;
		}

		$targetUniverse = HTTP::_GP('universe', 0);
		$targetOpen = false;
		try {
			$targetOpen = (int) Config::get($targetUniverse)->game_disable === 1;
		} catch (\Throwable) {
			$targetOpen = false;
		}

		$target = $this->switchService->validateSwitchTarget(
			$USER,
			$targetUniverse,
			Database::get(),
			Universe::availableUniverses(),
			$targetOpen
		);

		if ($target === null) {
			$this->printMessage($LNG['us_invalid'], $invalidButtons);
			return;
		}

		$token = $this->switchService->issueToken(
			(int) $USER['id'],
			(int) $target['userId'],
			(int) $target['universe'],
			CACHE_PATH
		);

		Session::load()->delete();

		$base = PROTOCOL . HTTP_HOST . HTTP_BASE;
		$url = $this->switchService->buildRedeemUrl(
			$base,
			(int) $target['universe'],
			$token,
			(bool) UNIS_WILDCAST
		);

		HTTP::redirectTo($url, true);
	}
}
