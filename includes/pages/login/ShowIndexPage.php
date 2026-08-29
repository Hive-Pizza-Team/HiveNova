<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseSeasonStore;
use HiveNova\Core\Config;
use HiveNova\Core\FleetVizSnapshotService;
use HiveNova\Core\GameAssetPrefetchService;
use HiveNova\Core\HTTP;
use HiveNova\Core\LobbyActivityFeed;
use HiveNova\Core\ReferralCaptureService;
use HiveNova\Core\SeasonService;
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

class ShowIndexPage extends AbstractLoginPage
{
	function __construct() 
	{
		parent::__construct();
		$this->setWindow('light');
	}

	/**
	 * AJAX poll endpoint for the public lobby activity feed.
	 * index.php?page=index&mode=activity&ajax=1&sinceId=0
	 */
	function activity()
	{
		global $LNG;

		$sinceId = HTTP::_GP('sinceId', 0);
		$uniFilter = HTTP::_GP('uni', 0);
		$payload = $this->buildActivityPayload($LNG, $sinceId, $uniFilter);
		$this->sendJSON($payload);
	}
	
	function show()
	{
		global $LNG;

		$config				= Config::get();
		$referralCapture	= new ReferralCaptureService();
		$referral			= $referralCapture->capture(
			Database::get(),
			ReferralCaptureService::requestBag(),
			$_COOKIE
		);
		$referralRegUni		= $referralCapture->registrationUniverseId($referral);
		$registerUrl		= $referralCapture->registerUrl(
			(int) $referral['id'],
			(int) $referral['universe']
		);

		$universeSelect	= array();
		$universeStats	= array();
		$universeNames	= array();
		$liveUniverseCount = 0;

		$db = Database::get();
		$seasonService = new SeasonService(new DatabaseSeasonStore());

		foreach(array_reverse(Universe::availableUniverses()) as $uniId)
		{
			$uniConfig = Config::get($uniId);
			$universeSelect[$uniId]	= $uniConfig->uni_name.($uniConfig->game_disable == 0 ? $LNG['uni_closed'] : '');
			$universeNames[$uniId]	= (string) $uniConfig->uni_name;
			if ((int) $uniConfig->game_disable === 1) {
				$liveUniverseCount++;
			}

			$sql = 'SELECT COUNT(*) as cnt FROM %%FLEETS%% WHERE fleet_universe = :uniId;';
			$fleetCount = $db->selectSingle($sql, array(':uniId' => $uniId), 'cnt');

			$sql = 'SELECT MIN(register_time) as started FROM %%USERS%% WHERE universe = :uniId AND register_time > 0;';
			$startedAt = (int) $db->selectSingle($sql, array(':uniId' => $uniId), 'started');

			$sql = 'SELECT COUNT(*) as cnt FROM (
				SELECT galaxy, `system`
				FROM %%PLANETS%%
				WHERE universe = :uniId
					AND planet_type = 1
					AND galaxy >= 1 AND galaxy <= :maxGalaxy
					AND `system` >= 1 AND `system` <= :maxSystem
				GROUP BY galaxy, `system`
			) AS occupied_systems;';
			$occupiedSystems = (int) $db->selectSingle($sql, array(
				':uniId'      => $uniId,
				':maxGalaxy'  => (int) $uniConfig->max_galaxy,
				':maxSystem'  => (int) $uniConfig->max_system,
			), 'cnt');

			$totalSystems = calculate_universe_system_capacity(
				(int) $uniConfig->max_galaxy,
				(int) $uniConfig->max_system
			);
			$vacantSystems = calculate_universe_vacant_systems($occupiedSystems, $totalSystems);
			$seasonPanel = $seasonService->loginPanel($uniConfig);

			$universeStats[$uniId] = array(
				'name'                => $uniConfig->uni_name,
				'open'                => (int) $uniConfig->game_disable === 1,
				'reg_open'            => (int) $uniConfig->reg_closed === 0,
				'game_speed'          => $uniConfig->game_speed / 2500,
				'fleet_speed'         => $uniConfig->fleet_speed / 2500,
				'resource_multiplier' => (int) $uniConfig->resource_multiplier,
				'galaxy_size'         => sprintf($LNG['uni_info_galaxy_format'], $uniConfig->max_galaxy, $uniConfig->max_system),
				'debris_percent'      => (int) $uniConfig->Fleet_Cdr,
				'moon_chance'         => (int) $uniConfig->moon_chance,
				'age'                 => format_universe_age_label($startedAt),
				'vacancy_pct'         => universe_vacancy_percent($vacantSystems, $totalSystems),
				'vacancy_level'       => universe_vacancy_level($vacantSystems, $totalSystems),
				'vacancy_label'       => format_universe_vacancy_label($vacantSystems, $totalSystems),
				'players'             => (int) $uniConfig->users_amount,
				'fleets'              => (int) $fleetCount,
				'seasonal'            => $seasonPanel['seasonal'],
				'season_id'           => $seasonPanel['season_id'],
				'season_number'       => ($seasonPanel['seasonal'] && $seasonPanel['season_id'] > 0)
					? sprintf($LNG['uni_info_season_number'], $seasonPanel['season_id'])
					: '',
				'season_can_enter'    => $seasonPanel['can_enter'],
				'closes_at'           => $seasonPanel['closes_at'],
				'wipe_live'           => $seasonPanel['wipe_live'],
				'wipe_urgent'         => $seasonPanel['wipe_urgent'],
				'wipe_label'          => $seasonPanel['seasonal']
					? format_universe_season_wipe_label($seasonPanel['status'], $seasonPanel['wipe_seconds'])
					: '',
				'entry_label'         => $seasonPanel['seasonal']
					? sprintf($LNG['uni_info_entry_pizza'], number_format((float) $seasonPanel['entry_pizza'], 2, '.', ''))
					: '',
				'entry_wallet'        => $seasonPanel['wallet'],
			);
		}

		$Code	= HTTP::_GP('code', 0);
		$loginErrorMessage	= '';
		if(isset($LNG['login_error_'.$Code]))
		{
			$loginErrorMessage	= $LNG['login_error_'.$Code];
		}

		$verkey = array(
			'capaktiv'	=> $config->capaktiv ?? 0,
			'cappublic'	=> $config->cappublic ?? '',
			'capprivate'	=> $config->capprivate ?? '',
		);
		$prefetchUrls = (new GameAssetPrefetchService())->listUrls();
		$defaultEmailUniverse = $this->getDefaultEmailUniverseId();
		$defaultHiveUniverse = $this->getDefaultHiveUniverseId();
		if ($referralRegUni > 0) {
			$defaultEmailUniverse = $referralRegUni;
			$defaultHiveUniverse = $referralRegUni;
		}
		$activityEvents = LobbyActivityFeed::fetch(
			array_keys($universeNames),
			$LNG,
			'UTC',
			$universeNames
		);
		$feedTitleKey = $liveUniverseCount === 1 ? 'lobby_feed_title_one' : 'lobby_feed_title_other';
		$feedTitle = sprintf(
			(string) ($LNG[$feedTitleKey] ?? '%s universes are live'),
			number_format($liveUniverseCount)
		);
		$lobbyVizConfig = (new FleetVizSnapshotService())->forOpenUniverses();
		$lobbyVizConfigJson = json_encode($lobbyVizConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$lobbyVizUniNames = [];
		foreach (($lobbyVizConfig['universes'] ?? []) as $uniRow) {
			$name = trim((string) ($uniRow['name'] ?? ''));
			if ($name !== '') {
				$lobbyVizUniNames[] = $name;
			}
		}
		$lobbyVizCaptionTitle = (string) ($LNG['lobby_viz_caption_title'] ?? 'Live fleet map');
		if ($lobbyVizUniNames !== []) {
			$lobbyVizCaptionTitle = sprintf(
				(string) ($LNG['lobby_viz_caption_title_uni'] ?? 'Live fleet map · %s'),
				implode(', ', $lobbyVizUniNames)
			);
		}

		$this->assign(array(
			'universeSelect'		=> $universeSelect,
			'defaultUniverse'		=> $defaultEmailUniverse,
			'defaultEmailUniverse'	=> $defaultEmailUniverse,
			'defaultHiveUniverse'	=> $defaultHiveUniverse,
			'universeStats'			=> $universeStats,
			'liveUniverseCount'		=> $liveUniverseCount,
			'lobbyFeedTitle'		=> $feedTitle,
			'code'					=> $loginErrorMessage,
			'verkey'			=> $verkey,
			'descHeader'			=> sprintf($LNG['loginWelcome'], $config->game_name),
			'descText'				=> sprintf($LNG['loginServerDesc'], $config->game_name),
			'gameInformations'      => array_filter(explode("\n", (string) $LNG['gameInformations']), 'strlen'),
			'loginInfo'				=> sprintf($LNG['loginInfo'], '<a href="index.php?page=rules">'.$LNG['menu_rules'].'</a>'),
			'prefetchUrls'			=> $prefetchUrls,
			'activityEvents'		=> $activityEvents,
			'activityPollUrl'		=> 'index.php?page=index&mode=activity&ajax=1',
			'lobbyVizConfigJson'		=> $lobbyVizConfigJson,
			'lobbyVizCaptionTitle'	=> $lobbyVizCaptionTitle,
			'lobbyHook'				=> (string) ($LNG['lobby_hook'] ?? 'Come get'),
			'lobbyHookEm'			=> (string) ($LNG['lobby_hook_em'] ?? 'MOONed'),
			'lobbyVizMtime'			=> (string) (@filemtime(ROOT_PATH . 'scripts/login/lobby-viz.js') ?: 0),
			'lobbyCssMtime'			=> (string) (@filemtime(ROOT_PATH . 'styles/resource/css/login/lobby.css') ?: 0),
			'registerUrl'			=> $registerUrl,
		));

		if ($loginErrorMessage) {
			AbstractLoginPage::printMessage($loginErrorMessage, array(array(
				'label'	=> $LNG['sys_back'],
				'url'	=> 'index.php')), array('index.php', 5), true);
		}
		
		$this->display('page.index.default.tpl');
	}

	/**
	 * @param object $LNG
	 * @return array{events: list<array<string, mixed>>}
	 */
	private function buildActivityPayload($LNG, int $sinceId, int $uniFilter): array
	{
		$universeNames = [];
		$universeIds = [];
		foreach (Universe::availableUniverses() as $uniId) {
			$uniId = (int) $uniId;
			$universeNames[$uniId] = (string) Config::get($uniId)->uni_name;
			if ($uniFilter <= 0 || $uniFilter === $uniId) {
				$universeIds[] = $uniId;
			}
		}

		return [
			'events' => LobbyActivityFeed::fetch(
				$universeIds,
				$LNG,
				'UTC',
				$universeNames,
				$sinceId
			),
		];
	}
}
