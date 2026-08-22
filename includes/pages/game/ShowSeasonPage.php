<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
use HiveNova\Core\DatabaseSeasonStore;
use HiveNova\Core\HTTP;
use HiveNova\Core\SeasonService;

class ShowSeasonPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $USER, $LNG;

		$config = Config::get();
		$service = new SeasonService(new DatabaseSeasonStore());
		if ($service->isSeasonal($config) && $service->canEnter($config)) {
			$service->ingestWallet($config);
		}

		$hasHive = $service->hasHive($USER);
		$hasEntry = $service->hasEntry($USER, $config);
		$message = '';
		if (!$service->isSeasonal($config)) {
			$message = $LNG['page_season_not_seasonal'] ?? 'This universe is not a short-lived season.';
		} elseif (!$hasHive) {
			$message = $LNG['page_season_need_hive'] ?? 'Link a Hive account in settings before you can enter.';
		} elseif ($hasEntry) {
			$message = $LNG['page_season_entry_ok'] ?? 'Entry deposit received. You can play this season.';
		} elseif (!$service->canEnter($config)) {
			$message = $LNG['page_season_closed'] ?? 'This season is not accepting entry deposits.';
		} else {
			$message = $LNG['page_season_need_entry'] ?? 'Pay the Pizza entry deposit to play this week.';
		}

		$closesAt = isset($config->season_closes_at) ? (int) $config->season_closes_at : 0;
		$countdown = $closesAt > TIMESTAMP ? max(0, $closesAt - TIMESTAMP) : 0;
		$entryAmount = isset($config->season_entry_pizza) ? (string) $config->season_entry_pizza : '0.100';
		$wallet = isset($config->season_wallet_account) ? (string) $config->season_wallet_account : '';
		$hiveAccount = (string) ($USER['hive_account'] ?? '');
		$memo = $service->entryMemo((int) $config->uni, (int) ($config->season_id ?? 0), (int) $USER['id']);
		$payInstruction = sprintf(
			(string) ($LNG['page_season_pay_instruction'] ?? 'Send %s PIZZA (Hive Engine token) from @%s to @%s with the memo below.'),
			$entryAmount,
			$hiveAccount,
			$wallet
		);

		$this->assign([
			'seasonMessage'   => $message,
			'hasHive'         => $hasHive,
			'hasEntry'        => $hasEntry,
			'canEnter'        => $service->canEnter($config),
			'entryAmount'     => $entryAmount,
			'wallet'          => $wallet,
			'memo'            => $memo,
			'hiveAccount'     => $hiveAccount,
			'payInstruction'  => $payInstruction,
			'seasonId'        => (int) ($config->season_id ?? 0),
			'countdownLabel'  => $countdown > 0 ? pretty_time($countdown) : '',
			'canPlay'         => $service->canPlay($USER, $config),
		]);
		$this->display('page.season.default.tpl');
	}

	function confirm()
	{
		global $USER, $LNG;

		$config = Config::get();
		$service = new SeasonService(new DatabaseSeasonStore());
		$txid = HTTP::_GP('txid', '');
		$result = $service->confirmTx($config, $USER, $txid);
		if ($result['ok']) {
			$this->printMessage($LNG['page_season_entry_ok'] ?? 'Entry deposit received.', [[
				'label' => $LNG['sys_forward'] ?? 'Continue',
				'url'   => 'game.php?page=overview',
			]]);
			return;
		}
		$this->printMessage($LNG['page_season_entry_fail'] ?? 'Could not confirm that Pizza deposit.', [[
			'label' => $LNG['sys_back'] ?? 'Back',
			'url'   => 'game.php?page=season',
		]]);
	}
}
