<?php

namespace HiveNova\Core;

class SeasonService
{
	public const STATUS_IDLE = 'idle';
	public const STATUS_RUNNING = 'running';
	public const STATUS_PAYING = 'paying';
	public const STATUS_PAYOUT_HOLD = 'payout_hold';
	public const TOKEN = 'PIZZA';
	public const DEFAULT_LENGTH = 604800;
	public const DEFAULT_PRECLOSE = 14400;

	/** @var list<string> */
	public const ALLOWED_PAGES = ['season', 'logout', 'settings'];

	/** @var callable|null fn(int $userId, string $subject, string $text, int $universe): void */
	private $messageSender = null;

	public function __construct(
		private readonly SeasonStore $store,
		private readonly HiveEngineTransfer $transfer = new HiveEngineTransfer(),
		private readonly HiveEngineClient $engine = new HiveEngineClient(),
		private readonly ?int $now = null,
	) {
	}

	public function setMessageSender(?callable $sender): void
	{
		$this->messageSender = $sender;
	}

	public function now(): int
	{
		return $this->now ?? (defined('TIMESTAMP') ? TIMESTAMP : time());
	}

	public function isSeasonal(Config $config): bool
	{
		return isset($config->season_mode) && (int) $config->season_mode === 1;
	}

	public function canPlay(array $user, Config $config): bool
	{
		if (!$this->isSeasonal($config)) {
			return true;
		}
		if ((int) ($user['authlevel'] ?? 0) > AUTH_USR) {
			return true;
		}

		return $this->hasHive($user) && $this->hasEntry($user, $config);
	}

	public function isAllowedPage(string $page): bool
	{
		return in_array(strtolower($page), self::ALLOWED_PAGES, true);
	}

	public function mustRedirect(array $user, Config $config, string $page): bool
	{
		if ($this->canPlay($user, $config)) {
			return false;
		}

		return !$this->isAllowedPage($page);
	}

	public function hasHive(array $user): bool
	{
		return HiveUtil::isAccountValid((string) ($user['hive_account'] ?? ''));
	}

	public function hasEntry(array $user, Config $config): bool
	{
		$seasonId = (int) ($config->season_id ?? 0);
		if ($seasonId < 1) {
			return false;
		}
		$userId = (int) ($user['id'] ?? 0);
		if ($userId < 1) {
			return false;
		}

		return $this->store->findEntry((int) $config->uni, $seasonId, $userId) !== null;
	}

	public function canEnter(Config $config): bool
	{
		if (!$this->isSeasonal($config)) {
			return false;
		}
		if ((string) ($config->season_status ?? '') !== self::STATUS_RUNNING) {
			return false;
		}

		return $this->now() < (int) ($config->season_closes_at ?? 0);
	}

	/**
	 * Login-card facts for a universe. No store reads.
	 *
	 * @return array{
	 *   seasonal: bool,
	 *   season_id: int,
	 *   status: string,
	 *   can_enter: bool,
	 *   closes_at: int,
	 *   wipe_seconds: int,
	 *   wipe_live: bool,
	 *   wipe_urgent: bool,
	 *   entry_pizza: string
	 * }
	 */
	public function loginPanel(Config $config): array
	{
		$seasonal = $this->isSeasonal($config);
		$status = $seasonal ? (string) ($config->season_status ?? self::STATUS_IDLE) : '';
		$closesAt = $seasonal ? (int) ($config->season_closes_at ?? 0) : 0;
		$remaining = $seasonal ? max(0, $closesAt - $this->now()) : 0;
		$preclose = max(60, (int) ($config->season_preclose_seconds ?? self::DEFAULT_PRECLOSE));

		return [
			'seasonal'     => $seasonal,
			'season_id'    => $seasonal ? (int) ($config->season_id ?? 0) : 0,
			'status'       => $status,
			'can_enter'    => $seasonal && $this->canEnter($config),
			'closes_at'    => $closesAt,
			'wipe_seconds' => $remaining,
			'wipe_live'    => $seasonal && $status === self::STATUS_RUNNING && $remaining > 0,
			'wipe_urgent'  => $seasonal && $status === self::STATUS_RUNNING && $remaining > 0 && $remaining <= $preclose,
			'entry_pizza'  => $seasonal ? (string) ($config->season_entry_pizza ?? '0') : '',
		];
	}

	public function entryMemo(int $universe, int $seasonId, int $userId): string
	{
		return sprintf('hn-s%d-%d-%d', $universe, $seasonId, $userId);
	}

	/**
	 * @return array{universe: int, season_id: int, user_id: int}|null
	 */
	public function parseMemo(string $memo): ?array
	{
		if (!preg_match('/^hn-s(\d+)-(\d+)-(\d+)$/', trim($memo), $m)) {
			return null;
		}

		return [
			'universe'  => (int) $m[1],
			'season_id' => (int) $m[2],
			'user_id'   => (int) $m[3],
		];
	}

	/**
	 * @return list<array{user_id: int, hive_account: string, rank: int, points: int, pizza_amount: float}>
	 */
	public function allocatePayouts(array $eligible, float $budget): array
	{
		$totalPoints = 0;
		foreach ($eligible as $row) {
			$totalPoints += (int) $row['points'];
		}
		if ($totalPoints <= 0 || $budget < HiveEngineTransfer::MIN_AMOUNT) {
			return [];
		}

		$out = [];
		$allocated = 0.0;
		$last = count($eligible) - 1;
		foreach ($eligible as $i => $row) {
			if ($i === $last) {
				$amount = round($budget - $allocated, 3);
			} else {
				$amount = floor($budget * ((int) $row['points']) / $totalPoints * 1000) / 1000;
				$allocated += $amount;
			}
			if ($amount < HiveEngineTransfer::MIN_AMOUNT) {
				continue;
			}
			$out[] = [
				'user_id'      => (int) $row['user_id'],
				'hive_account' => (string) $row['hive_account'],
				'rank'         => (int) $row['rank'],
				'points'       => (int) $row['points'],
				'pizza_amount' => $amount,
			];
		}

		return $out;
	}

	public function tick(): void
	{
		foreach (Universe::availableUniverses() as $uni) {
			try {
				$this->tickUniverse(Config::get((int) $uni));
			} catch (\Throwable $e) {
				continue;
			}
		}
	}

	public function tickUniverse(Config $config, array $lng = []): string
	{
		if (!$this->isSeasonal($config)) {
			return 'skip';
		}

		$status = (string) ($config->season_status ?? self::STATUS_IDLE);
		if ($status === self::STATUS_IDLE || (int) ($config->season_id ?? 0) < 1) {
			$this->startWeek($config, $lng);
			return 'started';
		}

		$this->ingestWallet($config);
		$this->fireReminders($config, $lng);

		if ($status === self::STATUS_RUNNING && $this->now() >= (int) $config->season_closes_at) {
			return $this->closeWeek($config, $lng);
		}

		if ($status === self::STATUS_PAYING || $status === self::STATUS_PAYOUT_HOLD) {
			return $this->retryPayouts($config, $lng);
		}

		return $status;
	}

	public function startWeek(Config $config, array $lng = []): void
	{
		$uni = (int) $config->uni;
		$seasonId = (int) ($config->season_id ?? 0) + 1;
		$length = max(3600, (int) ($config->season_length_seconds ?? self::DEFAULT_LENGTH));
		$now = $this->now();
		$config->season_id = $seasonId;
		$config->season_starts_at = $now;
		$config->season_closes_at = $now + $length;
		$config->season_status = self::STATUS_RUNNING;
		$config->season_last_reminder = '';
		$config->save();

		$this->store->upsertWeek([
			'universe'         => $uni,
			'season_id'        => $seasonId,
			'starts_at'        => $now,
			'closes_at'        => $now + $length,
			'status'           => self::STATUS_RUNNING,
			'pool_pizza'       => 0,
			'house_cut_pizza'  => 0,
			'payout_budget'    => 0,
		]);

		$this->broadcastReminder($config, 'start', $lng);
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{ok: bool, reason: string}
	 */
	public function confirmTx(Config $config, array $user, string $txid): array
	{
		$parsed = $this->engine->getTransaction($txid);
		if ($parsed === null) {
			return ['ok' => false, 'reason' => 'missing_tx'];
		}
		$transfer = HiveEngineClient::parseTransfer($parsed);
		if ($transfer === null) {
			return ['ok' => false, 'reason' => 'not_transfer'];
		}

		return $this->acceptTransfer($config, $user, $transfer, false);
	}

	public function ingestWallet(Config $config, bool $closing = false): int
	{
		$status = (string) ($config->season_status ?? '');
		if (!$closing && $status !== self::STATUS_RUNNING) {
			return 0;
		}
		$wallet = (string) ($config->season_wallet_account ?? '');
		if (!HiveUtil::isAccountValid($wallet)) {
			return 0;
		}
		$accepted = 0;
		foreach ($this->engine->accountHistory($wallet, self::TOKEN) as $row) {
			$transfer = HiveEngineClient::parseTransfer(is_array($row) ? $row : []);
			if ($transfer === null) {
				continue;
			}
			$memo = $this->parseMemo($transfer['memo']);
			if ($memo === null) {
				continue;
			}
			if ($memo['universe'] !== (int) $config->uni || $memo['season_id'] !== (int) $config->season_id) {
				continue;
			}
			$user = $this->store->findUser($memo['user_id']);
			if ($user === null || (int) ($user['universe'] ?? 0) !== (int) $config->uni) {
				continue;
			}
			$result = $this->acceptTransfer($config, $user, $transfer, $closing);
			if ($result['ok']) {
				$accepted++;
			}
		}

		return $accepted;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array{from: string, to: string, quantity: float, symbol: string, memo: string, trx_id: string, timestamp: int} $transfer
	 * @return array{ok: bool, reason: string}
	 */
	public function acceptTransfer(Config $config, array $user, array $transfer, bool $closing = false): array
	{
		if (!$closing && !$this->canEnter($config)) {
			return ['ok' => false, 'reason' => 'closed'];
		}
		if ($closing && (string) ($config->season_status ?? '') === self::STATUS_PAYOUT_HOLD) {
			return ['ok' => false, 'reason' => 'closed'];
		}
		$wallet = strtolower((string) ($config->season_wallet_account ?? ''));
		if ($wallet === '' || $transfer['to'] !== $wallet) {
			return ['ok' => false, 'reason' => 'wrong_wallet'];
		}
		if ($transfer['symbol'] !== self::TOKEN) {
			return ['ok' => false, 'reason' => 'wrong_token'];
		}
		$min = (float) ($config->season_entry_pizza ?? 0);
		if ($transfer['quantity'] + 0.0000001 < $min) {
			return ['ok' => false, 'reason' => 'too_small'];
		}
		$memo = $this->parseMemo($transfer['memo']);
		if ($memo === null) {
			return ['ok' => false, 'reason' => 'bad_memo'];
		}
		$uni = (int) $config->uni;
		$seasonId = (int) $config->season_id;
		if ($memo['universe'] !== $uni || $memo['season_id'] !== $seasonId) {
			return ['ok' => false, 'reason' => 'wrong_season'];
		}
		$userId = (int) ($user['id'] ?? 0);
		if ($userId < 1 || $memo['user_id'] !== $userId) {
			return ['ok' => false, 'reason' => 'wrong_user'];
		}
		$hive = strtolower(trim((string) ($user['hive_account'] ?? '')));
		if (!HiveUtil::isAccountValid($hive) || $hive !== $transfer['from']) {
			return ['ok' => false, 'reason' => 'hive_mismatch'];
		}
		$ts = (int) $transfer['timestamp'];
		if ($ts > 0 && $ts >= (int) $config->season_closes_at) {
			return ['ok' => false, 'reason' => 'after_close'];
		}
		if ($this->store->findEntry($uni, $seasonId, $userId) !== null) {
			return ['ok' => false, 'reason' => 'already'];
		}
		if ($transfer['trx_id'] !== '' && $this->store->hasTrx($uni, $transfer['trx_id'])) {
			return ['ok' => false, 'reason' => 'dup_trx'];
		}

		$ok = $this->store->insertEntry([
			'universe'      => $uni,
			'season_id'     => $seasonId,
			'user_id'       => $userId,
			'hive_account'  => $hive,
			'pizza_amount'  => round($transfer['quantity'], 3),
			'trx_id'        => $transfer['trx_id'],
			'created_at'    => $this->now(),
		]);

		return $ok ? ['ok' => true, 'reason' => ''] : ['ok' => false, 'reason' => 'insert_failed'];
	}

	public function closeWeek(Config $config, array $lng = []): string
	{
		$uni = (int) $config->uni;
		$seasonId = (int) $config->season_id;
		$this->ingestWallet($config, true);

		$ranking = $this->store->rankingRows($uni);
		$snapshots = [];
		foreach ($ranking as $row) {
			$snapshots[] = [
				'user_id'      => $row['user_id'],
				'hive_account' => $row['hive_account'],
				'rank'         => $row['rank'],
				'points'       => $row['points'],
			];
		}
		$this->store->replaceSnapshots($uni, $seasonId, $snapshots);

		$minPoints = (int) ($config->season_min_points ?? 0);
		$eligible = [];
		foreach ($ranking as $row) {
			if ($row['points'] < $minPoints) {
				continue;
			}
			if (!HiveUtil::isAccountValid($row['hive_account'])) {
				continue;
			}
			$eligible[] = $row;
		}

		$pool = $this->store->sumPool($uni, $seasonId);
		$cutPct = max(0.0, min(100.0, (float) ($config->season_house_cut_percent ?? 0)));
		$house = round($pool * $cutPct / 100, 3);
		$budget = round($pool - $house, 3);
		$payouts = $this->allocatePayouts($eligible, $budget);

		$this->store->upsertWeek([
			'universe'         => $uni,
			'season_id'        => $seasonId,
			'starts_at'        => (int) $config->season_starts_at,
			'closes_at'        => (int) $config->season_closes_at,
			'status'           => self::STATUS_PAYING,
			'pool_pizza'       => $pool,
			'house_cut_pizza'  => $house,
			'payout_budget'    => $budget,
		]);

		$pending = [];
		foreach ($payouts as $payout) {
			$pending[] = [
				'universe'      => $uni,
				'season_id'     => $seasonId,
				'user_id'       => $payout['user_id'],
				'hive_account'  => $payout['hive_account'],
				'rank'          => $payout['rank'],
				'points'        => $payout['points'],
				'pizza_amount'  => $payout['pizza_amount'],
				'trx_id'        => '',
				'status'        => 'pending',
			];
		}
		$this->store->insertPayouts($pending);

		$config->season_status = self::STATUS_PAYING;
		$config->save();

		$this->broadcastReminder($config, 'close', $lng);

		return $this->retryPayouts($config, $lng);
	}

	public function retryPayouts(Config $config, array $lng = []): string
	{
		$uni = (int) $config->uni;
		$seasonId = (int) $config->season_id;
		$open = $this->store->openPayouts($uni, $seasonId);
		$from = (string) ($config->season_wallet_account ?? '');
		$wif = (string) ($config->season_wallet_active_key ?? '');
		$failed = false;

		foreach ($open as $payout) {
			$result = $this->transfer->send(
				$from,
				$payout['hive_account'],
				(float) $payout['pizza_amount'],
				self::TOKEN,
				sprintf('HiveNova season %d prize', $seasonId),
				$wif
			);
			if ($result['ok']) {
				$this->store->markPayout($payout['id'], 'sent', $result['trx_id']);
			} else {
				$this->store->markPayout($payout['id'], 'failed', '');
				$failed = true;
			}
		}

		if ($failed || $this->store->openPayouts($uni, $seasonId) !== []) {
			$config->season_status = self::STATUS_PAYOUT_HOLD;
			$config->save();
			$this->store->updateWeek($uni, $seasonId, ['status' => self::STATUS_PAYOUT_HOLD]);

			return self::STATUS_PAYOUT_HOLD;
		}

		$this->store->updateWeek($uni, $seasonId, ['status' => 'paid']);
		$this->store->wipeProgress($uni, $config);
		$this->startWeek($config, $lng);

		return 'wiped';
	}

	public function fireReminders(Config $config, array $lng = []): void
	{
		$flags = $this->reminderFlags($config);
		$now = $this->now();
		$closes = (int) ($config->season_closes_at ?? 0);
		$preclose = max(60, (int) ($config->season_preclose_seconds ?? self::DEFAULT_PRECLOSE));

		if (!in_array('start', $flags, true)) {
			$this->broadcastReminder($config, 'start', $lng);
			return;
		}

		$day = gmdate('Y-m-d', $now);
		$dailyKey = 'daily:' . $day;
		if (!in_array($dailyKey, $flags, true) && $now < $closes) {
			$this->broadcastReminder($config, 'daily', $lng);
		}

		if (!in_array('preclose', $flags, true) && $now >= ($closes - $preclose) && $now < $closes) {
			$this->broadcastReminder($config, 'preclose', $lng);
		}
	}

	/**
	 * @return list<string>
	 */
	public function reminderFlags(Config $config): array
	{
		$raw = trim((string) ($config->season_last_reminder ?? ''));
		if ($raw === '') {
			return [];
		}

		return array_values(array_filter(explode('|', $raw)));
	}

	public function broadcastReminder(Config $config, string $kind, array $lng = []): void
	{
		$uni = (int) $config->uni;
		$closes = (int) ($config->season_closes_at ?? 0);
		$daysLeft = max(0, (int) ceil(($closes - $this->now()) / 86400));
		$hoursLeft = max(0, (int) ceil(($closes - $this->now()) / 3600));

		$defaults = [
			'start'    => 'Season %d has started. Hive signup and a Pizza entry are required to play.',
			'daily'    => 'Season %d countdown: %d day(s) until close.',
			'preclose' => 'Season %d closes in about %d hour(s).',
			'close'    => 'Season %d is closing. Rankings are locked for Pizza payouts.',
		];
		$keys = [
			'start'    => 'season_news_start',
			'daily'    => 'season_news_daily',
			'preclose' => 'season_news_preclose',
			'close'    => 'season_news_close',
		];
		$tpl = (string) ($lng[$keys[$kind] ?? ''] ?? $defaults[$kind] ?? '');
		$text = match ($kind) {
			'daily'    => sprintf($tpl, (int) $config->season_id, $daysLeft),
			'preclose' => sprintf($tpl, (int) $config->season_id, $hoursLeft),
			default    => sprintf($tpl, (int) $config->season_id),
		};

		if (isset($config->OverviewNewsFrame)) {
			$config->OverviewNewsFrame = 1;
			$config->OverviewNewsText = $text;
		}

		$flags = $this->reminderFlags($config);
		$flags = array_values(array_filter($flags, static function ($flag) {
			return !str_starts_with($flag, 'daily:');
		}));
		$flags[] = $kind === 'daily' ? 'daily:' . gmdate('Y-m-d', $this->now()) : $kind;
		$config->season_last_reminder = implode('|', array_unique($flags));
		$config->save();

		$sender = $this->messageSender;
		$subject = $lng['season_msg_subject'] ?? 'Season';
		foreach ($this->store->playersInUniverse($uni) as $player) {
			if ($sender !== null) {
				$sender((int) $player['id'], $subject, $text, $uni);
				continue;
			}
			PlayerUtil::sendMessage((int) $player['id'], 0, 'Season', 50, $subject, $text, $this->now(), null, 1, $uni);
		}
	}
}
