<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Encrypted Hive wallet pings for friend requests and private messages.
 * Fail-open: never interrupts in-game mail or buddy requests. Never logs keys.
 */
class SocialHiveMemoService
{
	public const KIND_BUDDY = 'buddy';
	public const KIND_PM = 'pm';
	public const OFFLINE_AFTER = 300;
	public const PM_COOLDOWN = 21600;
	public const MAX_ATTEMPTS = 5;
	public const CLAIM_STALE = 300;
	public const BATCH_LIMIT = 25;

	/** @var callable|null fn(string $account): string */
	private static $memoKeyFetcher = null;

	/** @var callable|null fn(string $wif, string $pub, string $memo): string */
	private static $encryptor = null;

	private static ?int $now = null;

	public function __construct(
		private readonly HiveTransfer $transfer = new HiveTransfer(),
	) {
	}

	public static function setMemoKeyFetcher(?callable $fetcher): void
	{
		self::$memoKeyFetcher = $fetcher;
	}

	public static function setEncryptor(?callable $encryptor): void
	{
		self::$encryptor = $encryptor;
	}

	public static function setNow(?int $now): void
	{
		self::$now = $now;
	}

	public function notifyBuddyRequest(int $recipientId, string $senderName): void
	{
		$this->enqueue(self::KIND_BUDDY, $recipientId, $senderName);
	}

	public function notifyPrivateMessage(int $recipientId, string $senderName): void
	{
		$this->enqueue(self::KIND_PM, $recipientId, $senderName);
	}

	public function isConfigSendable(Config $config): bool
	{
		if (!isset($config->hive_social_memo_active) || (int) $config->hive_social_memo_active !== 1) {
			return false;
		}
		if (!HiveUtil::isAccountValid((string) $config->hive_inactive_memo_account)) {
			return false;
		}
		if (!ConfigSecret::isPresent(ConfigSecret::ENV_INACTIVE_ACTIVE_KEY, $config->hive_inactive_memo_active_key ?? '')) {
			return false;
		}
		if (!ConfigSecret::isPresent(ConfigSecret::ENV_SOCIAL_MEMO_KEY, $config->hive_social_memo_memo_key ?? '')) {
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
			$this->sendDue($config);
		} catch (Throwable $e) {
			return;
		}
	}

	public static function buildMemo(array $lng, string $kind, string $senderName, string $gameName): string
	{
		$key = $kind === self::KIND_BUDDY ? 'hive_social_memo_buddy' : 'hive_social_memo_pm';
		$tpl = (string) ($lng[$key] ?? '');
		if ($tpl === '') {
			$tpl = $kind === self::KIND_BUDDY
				? '%s sent you a friend request in %s. Log in to respond.'
				: '%s sent you a private message in %s. Log in to read it.';
		}

		return '#' . sprintf($tpl, $senderName, $gameName);
	}

	private function enqueue(string $kind, int $recipientId, string $senderName): void
	{
		try {
			if ($kind !== self::KIND_BUDDY && $kind !== self::KIND_PM) {
				return;
			}
			$senderName = trim($senderName);
			if ($recipientId <= 0 || $senderName === '') {
				return;
			}
			if (strlen($senderName) > 32) {
				$senderName = substr($senderName, 0, 32);
			}

			$config = Config::get(ROOT_UNI);
			if (!$this->isConfigSendable($config)) {
				return;
			}

			$db = Database::get();
			$player = $db->selectSingle(
				'SELECT `id`, `hive_account`, `onlinetime`, `lang` FROM %%USERS%% WHERE `id` = :userId',
				[':userId' => $recipientId]
			);
			if (!is_array($player)) {
				return;
			}

			$to = strtolower(trim((string) $player['hive_account']));
			$from = strtolower(trim((string) $config->hive_inactive_memo_account));
			if (!HiveUtil::isAccountValid($to) || $to === $from) {
				return;
			}

			$now = $this->now();
			if ((int) $player['onlinetime'] > $now - self::OFFLINE_AFTER) {
				return;
			}

			if ($kind === self::KIND_PM && $this->pmAlreadyQueuedOrCooling($db, $recipientId, $now)) {
				return;
			}

			$lang = (string) ($player['lang'] ?? 'en');
			if ($lang === '') {
				$lang = 'en';
			}

			$db->insert(
				'INSERT INTO %%HIVE_SOCIAL_MEMO_QUEUE%% (`user_id`, `kind`, `sender_name`, `lang`, `created`)
				VALUES (:userId, :kind, :sender, :lang, :created)',
				[
					':userId'  => $recipientId,
					':kind'    => $kind,
					':sender'  => $senderName,
					':lang'    => $lang,
					':created' => $now,
				]
			);
		} catch (Throwable $e) {
			return;
		}
	}

	private function pmAlreadyQueuedOrCooling(DatabaseInterface $db, int $userId, int $now): bool
	{
		$pending = $db->selectSingle(
			'SELECT `queue_id` FROM %%HIVE_SOCIAL_MEMO_QUEUE%%
			WHERE `user_id` = :userId AND `kind` = :kind AND `sent_at` IS NULL AND `attempts` < :maxAttempts',
			[
				':userId'      => $userId,
				':kind'        => self::KIND_PM,
				':maxAttempts' => self::MAX_ATTEMPTS,
			],
			'queue_id'
		);
		if ($pending !== false && $pending !== null && $pending !== '') {
			return true;
		}

		$lastSent = $db->selectSingle(
			'SELECT MAX(`sent_at`) AS last_sent FROM %%HIVE_SOCIAL_MEMO_QUEUE%%
			WHERE `user_id` = :userId AND `kind` = :kind AND `sent_at` IS NOT NULL',
			[
				':userId' => $userId,
				':kind'   => self::KIND_PM,
			],
			'last_sent'
		);
		if ($lastSent === false || $lastSent === null || $lastSent === '') {
			return false;
		}

		return ($now - (int) $lastSent) < self::PM_COOLDOWN;
	}

	private function sendDue(Config $config): void
	{
		$db = Database::get();
		$now = $this->now();
		$stale = $now - self::CLAIM_STALE;
		$rows = $db->select(
			'SELECT `queue_id`, `user_id`, `kind`, `sender_name`, `lang`
			FROM %%HIVE_SOCIAL_MEMO_QUEUE%%
			WHERE `sent_at` IS NULL AND `attempts` < :maxAttempts
			AND (`claimed` IS NULL OR `claimed` < :stale)
			ORDER BY `queue_id` ASC LIMIT ' . self::BATCH_LIMIT,
			[
				':maxAttempts' => self::MAX_ATTEMPTS,
				':stale'       => $stale,
			]
		);

		$langCache = [];
		foreach ($rows as $row) {
			try {
				$this->sendOne($config, $row, $langCache);
			} catch (Throwable $e) {
				continue;
			}
		}
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, array<string, string>> $langCache
	 */
	private function sendOne(Config $config, array $row, array &$langCache): void
	{
		$queueId = (int) $row['queue_id'];
		$now = $this->now();
		if (!$this->claim($queueId, $now)) {
			return;
		}

		$db = Database::get();
		$player = $db->selectSingle(
			'SELECT `hive_account`, `universe` FROM %%USERS%% WHERE `id` = :userId',
			[':userId' => (int) $row['user_id']]
		);
		$to = is_array($player) ? strtolower(trim((string) $player['hive_account'])) : '';
		$from = strtolower(trim((string) $config->hive_inactive_memo_account));
		if (!HiveUtil::isAccountValid($to) || $to === $from) {
			return;
		}

		$memoPub = $this->memoPublicKey($to);
		if ($memoPub === '') {
			return;
		}

		$memoWif = ConfigSecret::resolve(ConfigSecret::ENV_SOCIAL_MEMO_KEY, $config->hive_social_memo_memo_key ?? '');
		if (!$this->memoWifMatchesAccount($memoWif, $from)) {
			return;
		}

		$lang = $this->languageStrings($langCache, (string) ($row['lang'] ?? 'en'));
		$plaintext = self::buildMemo(
			$lang,
			(string) $row['kind'],
			(string) $row['sender_name'],
			$this->resolveGameName(is_array($player) ? (int) ($player['universe'] ?? 0) : 0, $config)
		);
		$encrypted = $this->encrypt(
			$memoWif,
			$memoPub,
			$plaintext
		);
		if ($encrypted === '' || !str_starts_with($encrypted, '#')) {
			return;
		}

		$result = $this->transfer->send(
			$from,
			$to,
			(float) $config->hive_inactive_memo_amount,
			strtoupper((string) $config->hive_inactive_memo_asset),
			$encrypted,
			ConfigSecret::resolve(ConfigSecret::ENV_INACTIVE_ACTIVE_KEY, $config->hive_inactive_memo_active_key ?? ''),
			true
		);
		if (!$result['ok']) {
			return;
		}

		$db->update(
			'UPDATE %%HIVE_SOCIAL_MEMO_QUEUE%% SET `sent_at` = :now WHERE `queue_id` = :id AND `sent_at` IS NULL',
			[
				':now' => $now,
				':id'  => $queueId,
			]
		);
	}

	private function claim(int $queueId, int $now): bool
	{
		$db = Database::get();
		$db->update(
			'UPDATE %%HIVE_SOCIAL_MEMO_QUEUE%% SET `claimed` = :now, `attempts` = `attempts` + 1
			WHERE `queue_id` = :id AND `sent_at` IS NULL AND (`claimed` IS NULL OR `claimed` < :stale)',
			[
				':now'   => $now,
				':id'    => $queueId,
				':stale' => $now - self::CLAIM_STALE,
			]
		);

		return $db->rowCount() === 1;
	}

	private function memoPublicKey(string $account): string
	{
		if (self::$memoKeyFetcher !== null) {
			return (string) (self::$memoKeyFetcher)($account);
		}

		return HiveUtil::getMemoPublicKey($account);
	}

	private function resolveGameName(int $universe, Config $fallback): string
	{
		if ($universe > 0) {
			try {
				$name = trim((string) Config::get($universe)->game_name);
				if ($name !== '') {
					return $name;
				}
			} catch (Throwable $e) {
			}
		}

		return trim((string) $fallback->game_name);
	}

	private function memoWifMatchesAccount(string $wif, string $account): bool
	{
		$wif = trim($wif);
		if ($wif === '') {
			return false;
		}

		$expected = $this->memoPublicKey($account);
		if ($expected === '') {
			return false;
		}

		try {
			require_once ROOT_PATH . 'vendor/mahdiyari/hive-php/lib/Hive.php';

			return hash_equals((new \Hive\Helpers\PrivateKey($wif))->createPublic()->toString(), $expected);
		} catch (Throwable $e) {
			return false;
		}
	}

	private function encrypt(string $wif, string $pub, string $memo): string
	{
		if (self::$encryptor !== null) {
			return (string) (self::$encryptor)($wif, $pub, $memo);
		}

		return HiveMemo::encodeOrEmpty($wif, $pub, $memo);
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
			'hive_social_memo_buddy' => (string) ($lng['hive_social_memo_buddy'] ?? ''),
			'hive_social_memo_pm'    => (string) ($lng['hive_social_memo_pm'] ?? ''),
		];

		return $langCache[$lang];
	}

	private function now(): int
	{
		return self::$now ?? (defined('TIMESTAMP') ? TIMESTAMP : time());
	}
}
