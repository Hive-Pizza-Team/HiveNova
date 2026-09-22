<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
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
		$service = SeasonService::createDefault();
		if ($service->isSeasonal($config) && $service->canEnter($config)) {
			$service->ingestWallet($config);
		}

		$hasHive = $service->hasHive($USER);
		$hasEntry = $service->hasEntry($USER, $config);
		$message = '';
		if (!$service->isSeasonal($config)) {
			$message = $LNG['page_season_not_seasonal'] ?? 'This universe is not a short-lived season.';
		} elseif (!$hasHive) {
			$message = $LNG['page_season_need_hive'] ?? 'Link a Hive account before the wipe if you want prizes or a season medal. You can still play.';
		} elseif ($hasEntry) {
			$message = $LNG['page_season_entry_ok'] ?? 'Entry deposit received. You can play this season.';
		} elseif (!$service->canEnter($config)) {
			$message = $LNG['page_season_closed'] ?? 'This season is not accepting entry deposits. Entry had to be paid before the wipe.';
		} else {
			$message = $LNG['page_season_need_entry'] ?? 'Pay the Pizza entry before the wipe to be prize-eligible. You can still play this season without it.';
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

		$desk = $service->claimDesk($config, $USER);
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
			'claimDesk'       => $desk,
			'claimSeconds'    => ((int) ($desk['seconds_left'] ?? 0)) > 0 ? pretty_time((int) $desk['seconds_left']) : '',
			'claimAmount'     => number_format((float) ($desk['pizza_amount'] ?? 0), 3, '.', ''),
			'claimMeta'       => !empty($desk['metadata']['hive_user'])
				? sprintf(
					(string) ($LNG['page_season_medal_meta'] ?? 'Season %s medal for @%s · %s points. In-game record only.'),
					(string) ($desk['metadata']['season_id'] ?? ''),
					(string) $desk['metadata']['hive_user'],
					number_format((float) ($desk['metadata']['points'] ?? 0), 0, '.', ',')
				)
				: '',
		]);
		$this->display('page.season.default.tpl');
	}

	function claim()
	{
		global $USER, $LNG;

		$config = Config::get();
		$service = SeasonService::createDefault();
		$result = $service->claim($config, $USER);
		$reason = (string) ($result['reason'] ?? '');
		$ok = !empty($result['ok']);
		$key = match ($reason) {
			'', 'already' => 'page_season_claim_ok',
			'forfeited', 'window_closed' => 'page_season_claim_forfeit',
			'unpaid' => 'page_season_claim_unpaid',
			'unlinked' => 'page_season_claim_unlinked',
			'auto' => 'page_season_claim_auto',
			default => 'page_season_claim_fail',
		};
		$text = $LNG[$key] ?? ($ok
			? 'Prize sent to your Hive-Engine wallet. Season medal recorded.'
			: 'Could not claim that season prize.');
		$this->printMessage($text, [[
			'label' => $LNG['sys_back'] ?? 'Back',
			'url'   => 'game.php?page=season',
		]]);
	}

	function confirm()
	{
		global $USER, $LNG;

		$config = Config::get();
		$service = SeasonService::createDefault();
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
