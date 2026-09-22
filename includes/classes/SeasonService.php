<?php

namespace HiveNova\Core;

class SeasonService
{
	public const STATUS_IDLE = 'idle';
	public const STATUS_RUNNING = 'running';
	public const STATUS_PAYING = 'paying';
	public const STATUS_PAYOUT_HOLD = 'payout_hold';
	public const STATUS_BLOG_HOLD = 'blog_hold';
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
		private readonly HiveCommentPoster $poster = new HiveCommentPoster(),
		private readonly SeasonReportComposer $composer = new SeasonReportComposer(),
		private readonly ?Uni3ClaimStore $claims = null,
	) {
	}

	public static function createDefault(): self
	{
		return new self(new DatabaseSeasonStore(), claims: new DatabaseUni3ClaimStore());
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

	/**
	 * Destination for Settings → Deposit $PIZZA (pizzabit top-ups).
	 * Short-lived universes with a configured fee wallet use that wallet; otherwise moon.deposit.
	 */
	public function depositWallet(Config $config): string
	{
		if (!$this->isSeasonal($config)) {
			return 'moon.deposit';
		}

		$wallet = strtolower(trim((string) ($config->season_wallet_account ?? '')));
		return $wallet !== '' ? $wallet : 'moon.deposit';
	}

	public function canPlay(array $user, Config $config): bool
	{
		// Seasonal universes allow email/password play. Prizes stay Hive-gated.
		return true;
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
	 *   entry_pizza: string,
	 *   wallet: string
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
			'wallet'       => $seasonal ? (string) ($config->season_wallet_account ?? '') : '',
		];
	}

	/**
	 * Overview season wipe clock. Only while the season is running with time left.
	 *
	 * @return array{show: bool, closes_at: int, wipe_seconds: int}
	 */
	public function overviewWipeCountdown(Config $config): array
	{
		$panel = $this->loginPanel($config);
		if (!$panel['wipe_live']) {
			return ['show' => false, 'closes_at' => 0, 'wipe_seconds' => 0];
		}

		return [
			'show'         => true,
			'closes_at'    => $panel['closes_at'],
			'wipe_seconds' => $panel['wipe_seconds'],
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
		foreach ($eligible as $row) {
			$scale = 10 ** HiveEngineTransfer::PRECISION;
			$amount = floor($budget * ((int) $row['points']) / $totalPoints * $scale) / $scale;
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
			$allocated += $amount;
		}

		if ($out !== []) {
			$last = count($out) - 1;
			$allocated -= $out[$last]['pizza_amount'];
			$remainder = round($budget - $allocated, HiveEngineTransfer::PRECISION);
			if ($remainder >= HiveEngineTransfer::MIN_AMOUNT) {
				$out[$last]['pizza_amount'] = $remainder;
			} else {
				array_pop($out);
			}
		}

		return $out;
	}

	public function tick(array $lng = []): void
	{
		foreach (Universe::availableUniverses() as $uni) {
			try {
				$this->tickUniverse(Config::get((int) $uni), $lng);
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

		$this->expireUnclaimed($config);

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

		if ($status === self::STATUS_BLOG_HOLD) {
			return $this->publishAndWipe($config, $lng);
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
		if ($parsed !== null) {
			$transfer = HiveEngineClient::parseTransfer($parsed);
			if ($transfer === null) {
				return ['ok' => false, 'reason' => 'not_transfer'];
			}

			return $this->acceptTransfer($config, $user, $transfer, false);
		}

		$this->ingestWallet($config);
		if ($this->hasEntry($user, $config)) {
			return ['ok' => true, 'reason' => ''];
		}

		return ['ok' => false, 'reason' => 'missing_tx'];
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
			'pizza_amount'  => round($transfer['quantity'], HiveEngineTransfer::PRECISION),
			'trx_id'        => $transfer['trx_id'],
			'created_at'    => $this->now(),
		]);
		if ($ok) {
			Uni3PilotLog::record(Uni3PilotLog::ENTRY_PAID, $uni, $seasonId, $userId, $transfer['trx_id']);
		}

		return $ok ? ['ok' => true, 'reason' => ''] : ['ok' => false, 'reason' => 'insert_failed'];
	}

	public function closeWeek(Config $config, array $lng = []): string
	{
		$uni = (int) $config->uni;
		$seasonId = (int) $config->season_id;
		$this->ingestWallet($config, true);

		$ranking = $this->store->rankingRows($uni, $seasonId);
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
		$house = round($pool * $cutPct / 100, HiveEngineTransfer::PRECISION);
		$budget = round($pool - $house, HiveEngineTransfer::PRECISION);
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

		$gate = new Uni3ClaimGate();
		$pending = [];
		foreach ($payouts as $payout) {
			$origin = $this->linkOrigin($uni, $seasonId, (int) $payout['user_id']);
			$status = $gate->payoutStatusForOrigin($origin);
			$pending[] = [
				'universe'      => $uni,
				'season_id'     => $seasonId,
				'user_id'       => $payout['user_id'],
				'hive_account'  => $payout['hive_account'],
				'rank'          => $payout['rank'],
				'points'        => $payout['points'],
				'pizza_amount'  => $payout['pizza_amount'],
				'trx_id'        => '',
				'status'        => $status,
			];
			$this->claims?->upsertMedal([
				'universe'     => $uni,
				'season_id'    => $seasonId,
				'user_id'      => (int) $payout['user_id'],
				'hive_account' => (string) $payout['hive_account'],
				'tier'         => Uni3ClaimGate::MEDAL_TIER,
				'status'       => Uni3ClaimGate::MEDAL_PENDING,
				'points'       => (int) $payout['points'],
				'claimed_at'   => 0,
			]);
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
		$wif = ConfigSecret::resolve(ConfigSecret::ENV_SEASON_WALLET_KEY, $config->season_wallet_active_key ?? '');
		$failed = false;

		$gameName = trim((string) ($config->game_name ?? ''));
		if ($gameName === '') {
			$gameName = 'HiveNova';
		}
		$memo = sprintf('%s season %d prize', $gameName, $seasonId);

		foreach ($open as $payout) {
			$result = $this->transfer->send(
				$from,
				$payout['hive_account'],
				(float) $payout['pizza_amount'],
				self::TOKEN,
				$memo,
				$wif
			);
			if ($result['ok']) {
				$this->store->markPayout($payout['id'], 'sent', $result['trx_id']);
				$this->markMedalClaimed($uni, $seasonId, (int) $payout['user_id'], (string) $payout['hive_account']);
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

		$payoutTpl = (string) ($lng['season_discord_payouts'] ?? 'Season %d Pizza payouts have been sent.');
		DiscordWebhookService::notifySeasonReminder($uni, sprintf($payoutTpl, $seasonId));

		return $this->publishAndWipe($config, $lng);
	}

	/**
	 * Publish the season Hive blog (idempotent), then wipe and start the next week.
	 */
	public function publishAndWipe(Config $config, array $lng = []): string
	{
		$uni = (int) $config->uni;
		$seasonId = (int) $config->season_id;
		$week = $this->store->getWeek($uni, $seasonId) ?? [];

		if (trim((string) ($week['blog_trx_id'] ?? '')) !== '') {
			$this->performWipe($config, $lng);

			return 'wiped';
		}

		$author = strtolower(trim((string) ($config->season_blog_account ?? '')));
		$wif = ConfigSecret::resolve(ConfigSecret::ENV_SEASON_BLOG_KEY, $config->season_blog_posting_key ?? '');
		if ($author === '' || $wif === '' || !HiveUtil::isAccountValid($author)) {
			return $this->enterBlogHold($config, $uni, $seasonId);
		}

		$startsAt = (int) ($week['starts_at'] ?? $config->season_starts_at ?? 0);
		$closesAt = (int) ($week['closes_at'] ?? $config->season_closes_at ?? 0);
		$gameName = trim((string) ($config->game_name ?? ''));
		if ($gameName === '') {
			$gameName = 'HiveNova';
		}

		$report = $this->composer->compose(
			[
				'universe'         => $uni,
				'season_id'        => $seasonId,
				'starts_at'        => $startsAt,
				'closes_at'        => $closesAt,
				'pool_pizza'       => (float) ($week['pool_pizza'] ?? 0),
				'house_cut_pizza'  => (float) ($week['house_cut_pizza'] ?? 0),
				'payout_budget'    => (float) ($week['payout_budget'] ?? 0),
				'entrants'         => $this->store->countEntries($uni, $seasonId),
				'game_name'        => $gameName,
			],
			$this->store->reportRanking($uni, $seasonId, SeasonReportComposer::RANKING_LIMIT),
			$this->store->reportFeats($uni, $startsAt, $closesAt),
			$this->store->reportHallOfFame($uni, SeasonReportComposer::HOF_LIMIT)
		);

		$result = $this->poster->post(
			$author,
			$report['permlink'],
			$report['title'],
			$report['body'],
			$report['tags'],
			$wif
		);
		if (!$result['ok']) {
			return $this->enterBlogHold($config, $uni, $seasonId);
		}

		$this->store->updateWeek($uni, $seasonId, [
			'status'        => 'paid',
			'blog_permlink' => $report['permlink'],
			'blog_trx_id'   => $result['trx_id'],
		]);

		$blogUrl = 'https://peakd.com/@' . $author . '/' . $report['permlink'];
		$blogTpl = (string) ($lng['season_discord_blog'] ?? 'Season %d recap posted: %s');
		DiscordWebhookService::notifySeasonReminder($uni, sprintf($blogTpl, $seasonId, $blogUrl));

		$this->performWipe($config, $lng);

		return 'wiped';
	}

	/**
	 * Close the universe, log everyone out, wipe progress, start the next week, reopen.
	 */
	private function performWipe(Config $config, array $lng = []): void
	{
		$uni = (int) $config->uni;
		$seasonId = (int) $config->season_id;

		$config->game_disable = 0;
		$config->close_reason = (string) ($lng['season_wipe_close_reason'] ?? 'Season wipe in progress. Back shortly.');
		$config->save();

		$startTpl = (string) ($lng['season_discord_wipe_start'] ?? 'Season %d wipe starting — universe closed.');
		DiscordWebhookService::notifySeasonReminder($uni, sprintf($startTpl, $seasonId));

		$this->store->logoutUniverse($uni);
		$this->store->wipeProgress($uni, $config);
		$this->startWeek($config, $lng);

		$config->game_disable = 1;
		$config->close_reason = '';
		$config->save();

		$doneTpl = (string) ($lng['season_discord_wipe_done'] ?? 'Season wipe complete — universe online. Season %d has started.');
		DiscordWebhookService::notifySeasonReminder($uni, sprintf($doneTpl, (int) $config->season_id));
	}

	private function enterBlogHold(Config $config, int $uni, int $seasonId): string
	{
		$config->season_status = self::STATUS_BLOG_HOLD;
		$config->save();
		$this->store->updateWeek($uni, $seasonId, ['status' => self::STATUS_BLOG_HOLD]);

		return self::STATUS_BLOG_HOLD;
	}

	public function fireReminders(Config $config, array $lng = []): void
	{
		$flags = $this->reminderFlags($config);
		$now = $this->now();
		$closes = (int) ($config->season_closes_at ?? 0);
		$preclose = max(60, (int) ($config->season_preclose_seconds ?? self::DEFAULT_PRECLOSE));
		$inPreclose = $now >= ($closes - $preclose) && $now < $closes;

		if (!in_array('start', $flags, true)) {
			$this->broadcastReminder($config, 'start', $lng);
			return;
		}

		// Preclose wins over daily — never send a "N day(s)" notice once hours remain.
		if (!in_array('preclose', $flags, true) && $inPreclose) {
			$this->broadcastReminder($config, 'preclose', $lng);
			return;
		}

		// Key by displayed day-count, not UTC calendar date. The season cron runs
		// every 15 minutes; near UTC midnight a date-based key would re-fire within
		// minutes and repeat the same "N day(s)" text.
		$daysLeft = max(0, (int) ceil(($closes - $now) / 86400));
		$dailyKey = 'daily:' . $daysLeft;
		if (
			!in_array($dailyKey, $flags, true)
			&& !in_array('preclose', $flags, true)
			&& !$inPreclose
			&& $now < $closes
			&& $daysLeft > 0
		) {
			$this->broadcastReminder($config, 'daily', $lng);
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
			'start'    => 'Season %d has started. You can play now. Link Hive and pay the Pizza entry before the wipe to be prize-eligible.',
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
		if ($kind === 'daily') {
			// Replace prior daily:N; each remaining-day count still fires once because
			// fireReminders only sends when daily:$daysLeft is absent.
			$flags = array_values(array_filter($flags, static function ($flag) {
				return !str_starts_with($flag, 'daily:');
			}));
			$flags[] = 'daily:' . $daysLeft;
		} else {
			$flags[] = $kind;
		}
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

		DiscordWebhookService::notifySeasonReminder($uni, $text);
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{show: bool, dismissible: bool, hard: bool, mode: string, season_id: int}
	 */
	public function prizeLockState(array $user, Config $config, bool $dismissed): array
	{
		$seasonId = (int) ($config->season_id ?? 0);
		$gate = new Uni3ClaimGate();
		$pending = $this->hasPendingClaim($user, $config);
		$banner = $gate->banner(
			$this->isSeasonal($config),
			$gate->prizeLocked($this->hasHive($user), $this->hasEntry($user, $config)),
			$pending,
			$dismissed
		);

		return $banner + ['season_id' => $seasonId];
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{
	 *   show: bool,
	 *   can_claim: bool,
	 *   reason: string,
	 *   season_id: int,
	 *   seconds_left: int,
	 *   pizza_amount: float,
	 *   medal_status: string,
	 *   metadata: array<string, int|string|bool>,
	 *   window_open: bool
	 * }
	 */
	public function claimDesk(Config $config, array $user): array
	{
		$empty = [
			'show'          => false,
			'can_claim'     => false,
			'reason'        => '',
			'season_id'     => 0,
			'seconds_left'  => 0,
			'pizza_amount'  => 0.0,
			'medal_status'  => '',
			'metadata'      => [],
			'window_open'   => false,
		];
		if (!$this->isSeasonal($config)) {
			return $empty;
		}
		$target = $this->claimTargetSeasonId($config);
		if ($target < 1) {
			return $empty;
		}

		$gate = new Uni3ClaimGate();
		$uni = (int) $config->uni;
		$userId = (int) ($user['id'] ?? 0);
		$week = $this->store->getWeek($uni, $target) ?? [];
		$closes = (int) ($week['closes_at'] ?? 0);
		$open = $gate->claimWindowOpen($closes, $this->now());
		$expired = $gate->claimWindowExpired($closes, $this->now());
		$linked = $this->hasHive($user);
		$entry = $this->store->findEntry($uni, $target, $userId);
		$paid = $entry !== null;
		$payout = $this->store->findPayout($uni, $target, $userId);
		$medal = $this->claims?->findMedal($uni, $target, $userId);
		$status = (string) ($payout['status'] ?? '');
		$points = (int) ($payout['points'] ?? ($medal['points'] ?? 0));
		$decision = $this->claimDecision($linked, $paid, $status, $open, $expired, $payout !== null);
		$relevant = $paid || $payout !== null || $medal !== null || ($linked && ($open || $expired));

		$metadata = [];
		if ($linked && $paid) {
			$metadata = $gate->medalMetadata(
				$uni,
				$target,
				(string) ($user['hive_account'] ?? ''),
				(string) ($user['username'] ?? ''),
				$points,
				(string) ($medal['status'] ?? Uni3ClaimGate::MEDAL_PENDING)
			);
		}

		return [
			'show'         => $relevant && ($open || $expired || $status !== ''),
			'can_claim'    => $decision['can_claim'],
			'reason'       => $decision['reason'],
			'season_id'    => $target,
			'seconds_left' => $open ? max(0, $closes + Uni3ClaimGate::CLAIM_WINDOW_SECONDS - $this->now()) : 0,
			'pizza_amount' => (float) ($payout['pizza_amount'] ?? 0),
			'medal_status' => (string) ($medal['status'] ?? ''),
			'metadata'     => $metadata,
			'window_open'  => $open,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{ok: bool, reason: string}
	 */
	public function claim(Config $config, array $user): array
	{
		$desk = $this->claimDesk($config, $user);
		if ($desk['reason'] === 'already') {
			return ['ok' => true, 'reason' => 'already'];
		}
		if (!$desk['can_claim']) {
			return ['ok' => false, 'reason' => $desk['reason'] !== '' ? $desk['reason'] : 'not_eligible'];
		}

		$uni = (int) $config->uni;
		$seasonId = (int) $desk['season_id'];
		$userId = (int) ($user['id'] ?? 0);
		$payout = $this->store->findPayout($uni, $seasonId, $userId);
		if ($payout === null) {
			return ['ok' => false, 'reason' => 'not_eligible'];
		}

		Uni3PilotLog::record(Uni3PilotLog::CLAIM_STARTED, $uni, $seasonId, $userId, (string) $payout['hive_account']);
		$token = 'claim:' . bin2hex(random_bytes(8));
		$this->store->compareAndSetPayout((int) $payout['id'], Uni3ClaimGate::PAYOUT_PENDING_CLAIM, Uni3ClaimGate::PAYOUT_CLAIMING, $token);
		$locked = $this->store->findPayout($uni, $seasonId, $userId);
		if ($locked === null || (string) ($locked['trx_id'] ?? '') !== $token) {
			return ['ok' => false, 'reason' => 'in_progress'];
		}

		$from = (string) ($config->season_wallet_account ?? '');
		$wif = ConfigSecret::resolve(ConfigSecret::ENV_SEASON_WALLET_KEY, $config->season_wallet_active_key ?? '');
		$gameName = trim((string) ($config->game_name ?? ''));
		if ($gameName === '') {
			$gameName = 'HiveNova';
		}
		$memo = sprintf('%s season %d prize', $gameName, $seasonId);
		$result = $this->transfer->send(
			$from,
			(string) $payout['hive_account'],
			(float) $payout['pizza_amount'],
			self::TOKEN,
			$memo,
			$wif
		);
		if (!$result['ok']) {
			$this->store->compareAndSetPayout((int) $payout['id'], Uni3ClaimGate::PAYOUT_CLAIMING, Uni3ClaimGate::PAYOUT_PENDING_CLAIM, '');
			return ['ok' => false, 'reason' => 'send_failed'];
		}

		$this->store->markPayout((int) $payout['id'], Uni3ClaimGate::PAYOUT_SENT, $result['trx_id']);
		$this->markMedalClaimed($uni, $seasonId, $userId, (string) $payout['hive_account']);
		Uni3PilotLog::record(Uni3PilotLog::CLAIM_COMPLETED, $uni, $seasonId, $userId, $result['trx_id']);

		return ['ok' => true, 'reason' => ''];
	}

	public function expireUnclaimed(Config $config): int
	{
		if (!$this->isSeasonal($config) || $this->claims === null) {
			return 0;
		}
		$gate = new Uni3ClaimGate();
		$uni = (int) $config->uni;
		$current = (int) ($config->season_id ?? 0);
		$seasons = [];
		if ($current > 1) {
			$seasons[] = $current - 1;
		}
		if ($current > 0) {
			$seasons[] = $current;
		}
		$forfeited = 0;
		foreach (array_unique($seasons) as $seasonId) {
			$week = $this->store->getWeek($uni, $seasonId);
			if ($week === null) {
				continue;
			}
			if (!$gate->claimWindowExpired((int) ($week['closes_at'] ?? 0), $this->now())) {
				continue;
			}
			foreach ($this->store->payoutsWithStatus($uni, $seasonId, Uni3ClaimGate::PAYOUT_PENDING_CLAIM) as $payout) {
				$this->store->markPayout((int) $payout['id'], Uni3ClaimGate::PAYOUT_FORFEITED, '');
				$this->claims->markMedal(
					$uni,
					$seasonId,
					(int) $payout['user_id'],
					Uni3ClaimGate::MEDAL_FORFEITED,
					$this->now(),
					(string) $payout['hive_account']
				);
				Uni3PilotLog::record(
					Uni3PilotLog::CLAIM_FORFEITED,
					$uni,
					$seasonId,
					(int) $payout['user_id'],
					(string) $payout['hive_account']
				);
				$forfeited++;
			}
		}

		return $forfeited;
	}

	private function linkOrigin(int $universe, int $seasonId, int $userId): string
	{
		if ($this->claims === null) {
			return Uni3ClaimGate::ORIGIN_KEYCHAIN;
		}
		$link = $this->claims->findHiveLinkByUser($universe, $seasonId, $userId);
		$origin = (string) ($link['origin'] ?? '');
		if ($origin === Uni3ClaimGate::ORIGIN_EMAIL) {
			return Uni3ClaimGate::ORIGIN_EMAIL;
		}

		return Uni3ClaimGate::ORIGIN_KEYCHAIN;
	}

	private function claimTargetSeasonId(Config $config): int
	{
		$status = (string) ($config->season_status ?? '');
		$id = (int) ($config->season_id ?? 0);
		if (in_array($status, [self::STATUS_PAYING, self::STATUS_PAYOUT_HOLD, self::STATUS_BLOG_HOLD], true) && $id > 0) {
			return $id;
		}
		if ($status === self::STATUS_RUNNING && $id > 1) {
			return $id - 1;
		}

		return 0;
	}

	/**
	 * @param array<string, mixed> $user
	 */
	private function hasPendingClaim(array $user, Config $config): bool
	{
		$target = $this->claimTargetSeasonId($config);
		if ($target < 1) {
			return false;
		}
		$week = $this->store->getWeek((int) $config->uni, $target);
		$closes = (int) ($week['closes_at'] ?? 0);
		if (!(new Uni3ClaimGate())->claimWindowOpen($closes, $this->now())) {
			return false;
		}
		$payout = $this->store->findPayout((int) $config->uni, $target, (int) ($user['id'] ?? 0));

		return $payout !== null && (string) ($payout['status'] ?? '') === Uni3ClaimGate::PAYOUT_PENDING_CLAIM;
	}

	/**
	 * @return array{can_claim: bool, reason: string}
	 */
	private function claimDecision(bool $linked, bool $paid, string $status, bool $windowOpen, bool $windowExpired, bool $hasPayout): array
	{
		if (!$linked) {
			return ['can_claim' => false, 'reason' => 'unlinked'];
		}
		if (!$paid) {
			return ['can_claim' => false, 'reason' => 'unpaid'];
		}
		if ($status === Uni3ClaimGate::PAYOUT_FORFEITED || ($windowExpired && $status === Uni3ClaimGate::PAYOUT_PENDING_CLAIM)) {
			return ['can_claim' => false, 'reason' => 'forfeited'];
		}
		if ($status === Uni3ClaimGate::PAYOUT_SENT) {
			return ['can_claim' => false, 'reason' => 'already'];
		}
		if ($status === Uni3ClaimGate::PAYOUT_PENDING || $status === Uni3ClaimGate::PAYOUT_FAILED) {
			return ['can_claim' => false, 'reason' => 'auto'];
		}
		if ($status === Uni3ClaimGate::PAYOUT_CLAIMING) {
			return ['can_claim' => false, 'reason' => 'in_progress'];
		}
		if ($status === Uni3ClaimGate::PAYOUT_PENDING_CLAIM && $windowOpen) {
			return ['can_claim' => true, 'reason' => ''];
		}
		if (!$hasPayout) {
			return ['can_claim' => false, 'reason' => 'not_eligible'];
		}
		if (!$windowOpen) {
			return ['can_claim' => false, 'reason' => 'window_closed'];
		}

		return ['can_claim' => false, 'reason' => 'not_eligible'];
	}

	private function markMedalClaimed(int $universe, int $seasonId, int $userId, string $hive): void
	{
		$this->claims?->markMedal($universe, $seasonId, $userId, Uni3ClaimGate::MEDAL_CLAIMED, $this->now(), $hive);
	}
}
