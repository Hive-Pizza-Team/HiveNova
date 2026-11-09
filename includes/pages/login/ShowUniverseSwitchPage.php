<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\Session;
use HiveNova\Core\Universe;
use HiveNova\Core\UniverseSwitchService;

/**
 * Redeems a one-time universe-switch token under the target /uniN/ path.
 */
class ShowUniverseSwitchPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $LNG;

		$service = new UniverseSwitchService();
		$token = HTTP::_GP('token', '');
		$loginLabel = $LNG['loginButton'] ?? 'Login';
		$invalidButtons = [[
			'label' => $loginLabel,
			'url'   => 'index.php',
		]];

		$payload = $service->peekToken($token, CACHE_PATH);
		if ($payload === null || (int) $payload['toUniverse'] !== (int) Universe::current()) {
			$this->printMessage($LNG['us_invalid'] ?? 'Invalid universe switch link.', $invalidButtons);
			return;
		}

		$payload = $service->consumeToken($token, CACHE_PATH);
		if ($payload === null) {
			$this->printMessage($LNG['us_invalid'] ?? 'Invalid universe switch link.', $invalidButtons);
			return;
		}

		$db = Database::get();
		$user = $db->selectSingle(
			'SELECT id, username, id_planet, bana, universe FROM %%USERS%% WHERE id = :userId',
			[':userId' => (int) $payload['toUserId']]
		);

		if (
			empty($user)
			|| !is_array($user)
			|| (int) ($user['bana'] ?? 0) === 1
			|| (int) ($user['universe'] ?? 0) !== (int) $payload['toUniverse']
		) {
			$this->printMessage($LNG['us_invalid'] ?? 'Invalid universe switch link.', $invalidButtons);
			return;
		}

		$session = Session::create();
		$session->userId = (int) $user['id'];
		$session->planetId = (int) ($user['id_planet'] ?? 0);
		$session->adminAccess = 0;
		Session::regenerateId();
		$session->save();

		HTTP::sendCookie('uni', (string) $payload['toUniverse'], TIMESTAMP + 31536000);

		$uniName = (string) Config::get((int) $payload['toUniverse'])->uni_name;
		$username = (string) ($user['username'] ?? '');
		$message = isset($LNG['us_switched'])
			? sprintf($LNG['us_switched'], $uniName, $username)
			: sprintf('Entered %s as %s.', $uniName, $username);

		$this->printMessage(
			$message,
			[[
				'label' => $LNG['lm_overview'] ?? 'Overview',
				'url'   => 'game.php',
			]],
			['game.php', 2]
		);
	}
}
