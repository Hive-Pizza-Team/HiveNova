<?php

namespace HiveNova\Core;

use Throwable;

/**
 * One plaintext Hive memo per long-term-inactive stretch for linked AUTH_USR players.
 */
class InactiveHiveMemoService
{
	public function __construct(
		private readonly HiveTransfer $transfer = new HiveTransfer(),
	) {
	}

	public function isConfigSendable(Config $config): bool
	{
		if ((int) $config->hive_inactive_memo_active !== 1) {
			return false;
		}
		if (!HiveUtil::isAccountValid((string) $config->hive_inactive_memo_account)) {
			return false;
		}
		if (trim((string) $config->hive_inactive_memo_active_key) === '') {
			return false;
		}
		$asset = strtoupper((string) $config->hive_inactive_memo_asset);
		if ($asset !== 'HIVE' && $asset !== 'HBD') {
			return false;
		}
		if ((float) $config->hive_inactive_memo_amount < HiveTransfer::MIN_AMOUNT) {
			return false;
		}

		return true;
	}

	public function run(?Config $config = null): void
	{
		try {
			$config ??= Config::get(ROOT_UNI);
			if (!$this->isConfigSendable($config)) {
				return;
			}
			if ((int) $config->hive_inactive_memo_armed !== 1) {
				$this->arm($config);
				return;
			}
			$this->sendDue($config);
		} catch (Throwable $e) {
			return;
		}
	}

	public static function buildMemo(array $lng, string $gameName, bool $wipeOn): string
	{
		$statusTpl = (string) ($lng['hive_inactive_memo_status'] ?? '');
		if ($statusTpl === '') {
			$statusTpl = 'You are long-term inactive (I) in %s. Log in to keep your empire.';
		}
		$status = sprintf($statusTpl, $gameName);
		if ($wipeOn) {
			$wipe = (string) ($lng['hive_inactive_memo_wipe'] ?? '');
			if ($wipe === '') {
				$wipe = ' If you stay gone, this empire will be removed.';
			}
			$status .= $wipe;
		}

		return $status;
	}

	private function arm(Config $config): void
	{
		$db = Database::get();
		$threshold = TIMESTAMP - INACTIVE_LONG;
		$db->beginTransaction();
		try {
			$db->update(
				'UPDATE %%USERS%% SET `inactive_hive_memo_onlinetime` = `onlinetime`
				WHERE `authlevel` = :auth
				AND `hive_account` != \'\'
				AND `onlinetime` < :threshold
				AND (`inactive_hive_memo_onlinetime` IS NULL OR `inactive_hive_memo_onlinetime` != `onlinetime`)',
				[
					':auth'      => AUTH_USR,
					':threshold' => $threshold,
				]
			);
			$config->hive_inactive_memo_armed = 1;
			$config->save();
			$db->commit();
		} catch (Throwable $e) {
			$db->rollback();
		}
	}

	private function sendDue(Config $config): void
	{
		$db = Database::get();
		$from = (string) $config->hive_inactive_memo_account;
		$wif = (string) $config->hive_inactive_memo_active_key;
		$asset = strtoupper((string) $config->hive_inactive_memo_asset);
		$amount = (float) $config->hive_inactive_memo_amount;
		$threshold = TIMESTAMP - INACTIVE_LONG;

		$players = $db->select(
			'SELECT `id`, `hive_account`, `onlinetime`, `universe`, `lang`, `inactive_mail`
			FROM %%USERS%%
			WHERE `authlevel` = :auth
			AND `hive_account` != \'\'
			AND `onlinetime` < :threshold
			AND (`inactive_hive_memo_onlinetime` IS NULL OR `inactive_hive_memo_onlinetime` != `onlinetime`)',
			[
				':auth'      => AUTH_USR,
				':threshold' => $threshold,
			]
		);

		$langCache = [];
		foreach ($players as $player) {
			try {
				$this->sendOne($config, $player, $from, $wif, $asset, $amount, $langCache);
			} catch (Throwable $e) {
				continue;
			}
		}
	}

	/**
	 * @param array<string, mixed> $player
	 * @param array<string, array<string, string>> $langCache
	 */
	private function sendOne(Config $config, array $player, string $from, string $wif, string $asset, float $amount, array &$langCache): void
	{
		$to = strtolower(trim((string) $player['hive_account']));
		if (!HiveUtil::isAccountValid($to) || $to === $from) {
			return;
		}

		$onlinetime = (int) $player['onlinetime'];
		if (!$this->claim((int) $player['id'], $onlinetime)) {
			return;
		}

		$uniConfig = Config::get((int) $player['universe']);
		$wipeOn = (int) $uniConfig->del_user_automatic !== 0;
		$lang = $this->languageStrings($langCache, (string) ($player['lang'] ?? 'en'));
		$memo = self::buildMemo($lang, (string) $config->game_name, $wipeOn);

		$result = $this->transfer->send($from, $to, $amount, $asset, $memo, $wif);
		if ($result['ok']) {
			return;
		}

		$this->releaseClaim((int) $player['id'], $onlinetime);
	}

	private function claim(int $userId, int $onlinetime): bool
	{
		$db = Database::get();
		$db->update(
			'UPDATE %%USERS%% SET `inactive_hive_memo_onlinetime` = :online
			WHERE `id` = :id
			AND `onlinetime` = :online
			AND (`inactive_hive_memo_onlinetime` IS NULL OR `inactive_hive_memo_onlinetime` != :online)',
			[
				':online' => $onlinetime,
				':id'     => $userId,
			]
		);

		return $db->rowCount() === 1;
	}

	private function releaseClaim(int $userId, int $onlinetime): void
	{
		Database::get()->update(
			'UPDATE %%USERS%% SET `inactive_hive_memo_onlinetime` = NULL
			WHERE `id` = :id AND `inactive_hive_memo_onlinetime` = :online',
			[
				':id'     => $userId,
				':online' => $onlinetime,
			]
		);
	}

	/**
	 * @param array<string, array<string, string>> $langCache
	 * @return array<string, string>
	 */
	private function languageStrings(array &$langCache, string $lang): array
	{
		if ($lang === '') {
			$lang = 'en';
		}
		if (isset($langCache[$lang])) {
			return $langCache[$lang];
		}

		$lng = new Language($lang);
		$lng->includeData(['L18N', 'INGAME', 'CUSTOM']);
		$langCache[$lang] = [
			'hive_inactive_memo_status' => (string) ($lng['hive_inactive_memo_status'] ?? ''),
			'hive_inactive_memo_wipe'   => (string) ($lng['hive_inactive_memo_wipe'] ?? ''),
		];

		return $langCache[$lang];
	}
}
