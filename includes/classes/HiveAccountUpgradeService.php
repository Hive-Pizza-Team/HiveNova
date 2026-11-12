<?php

namespace HiveNova\Core;

/**
 * In-product Hive upgrade helpers for password players (issue #638).
 *
 * P0: detect upgrade need, prove ownership (Keychain signature or HiveSigner
 * access token), bind @hive → Moon uid. Sponsored create stays P1 / admin-gated.
 */
class HiveAccountUpgradeService
{
	public const ERR_INVALID_ACCOUNT = 'invalid_account';
	public const ERR_INVALID_SIGNATURE = 'invalid_signature';
	public const ERR_INVALID_TOKEN = 'invalid_token';
	public const ERR_INVALID_STATE = 'invalid_state';
	public const ERR_ALREADY_LINKED = 'already_linked';
	public const ERR_TAKEN = 'taken';
	public const ERR_EMPTY = 'empty';
	public const ERR_NOT_PASSWORD_PLAYER = 'not_password_player';

	public const METHOD_KEYCHAIN = 'keychain';
	public const METHOD_HIVESIGNER = 'hivesigner';

	public const CREATE_STATUS_PENDING = 'admin_gated_pending';

	public const DEFAULT_HIVESIGNER_AUTHORIZE = 'https://hivesigner.com/oauth2/authorize';
	public const DEFAULT_HIVESIGNER_ME = 'https://hivesigner.com/api/me';
	public const DEFAULT_HIVESIGNER_SCOPE = 'login';

	/** @var callable(string, string): bool */
	private $isSignValid;

	/** @var callable(string, int): int */
	private $countLinked;

	/** @var callable(int, string): void */
	private $bindAccount;

	/** @var callable(string): ?string */
	private $fetchHiveSignerUser;

	/** @var array{clientId: string, authorizeUrl: string, meUrl: string, scope: string} */
	private array $hiveSigner;

	/**
	 * @param callable(string, string): bool|null $isSignValid
	 * @param callable(string, int): int|null $countLinked
	 * @param callable(int, string): void|null $bindAccount
	 * @param callable(string): ?string|null $fetchHiveSignerUser
	 * @param array{clientId?: string, authorizeUrl?: string, meUrl?: string, scope?: string}|null $hiveSigner
	 */
	public function __construct(
		?callable $isSignValid = null,
		?callable $countLinked = null,
		?callable $bindAccount = null,
		?callable $fetchHiveSignerUser = null,
		?array $hiveSigner = null,
	) {
		$this->isSignValid = $isSignValid ?? static function (string $account, string $signature): bool {
			return HiveUtil::isSignValid($account, $signature);
		};
		$this->countLinked = $countLinked ?? static function (string $account, int $universe): int {
			$sql = 'SELECT COUNT(*) as state FROM %%USERS%% WHERE `hive_account` = :hiveAccount AND `universe` = :universe;';
			return (int) Database::get()->selectSingle($sql, [
				':hiveAccount' => $account,
				':universe'    => $universe,
			], 'state');
		};
		$this->bindAccount = $bindAccount ?? static function (int $userId, string $account): void {
			$sql = 'UPDATE %%USERS%% SET hive_account = :hiveaccount WHERE id = :userID;';
			Database::get()->update($sql, [
				':hiveaccount' => $account,
				':userID'      => $userId,
			]);
			if (function_exists('isModuleAvailable') && isModuleAvailable(MODULE_ACHIEVEMENTS)) {
				AchievementService::record(
					$userId,
					'hive_account_valid',
					['valid' => 1],
					true
				);
			}
		};
		$this->fetchHiveSignerUser = $fetchHiveSignerUser ?? [$this, 'requestHiveSignerUser'];
		$this->hiveSigner = [
			'clientId'     => (string) ($hiveSigner['clientId'] ?? (defined('HIVESIGNER_CLIENT_ID') ? HIVESIGNER_CLIENT_ID : '')),
			'authorizeUrl' => (string) ($hiveSigner['authorizeUrl'] ?? (defined('HIVESIGNER_AUTHORIZE_URL') ? HIVESIGNER_AUTHORIZE_URL : self::DEFAULT_HIVESIGNER_AUTHORIZE)),
			'meUrl'        => (string) ($hiveSigner['meUrl'] ?? (defined('HIVESIGNER_ME_URL') ? HIVESIGNER_ME_URL : self::DEFAULT_HIVESIGNER_ME)),
			'scope'        => (string) ($hiveSigner['scope'] ?? (defined('HIVESIGNER_SCOPE') ? HIVESIGNER_SCOPE : self::DEFAULT_HIVESIGNER_SCOPE)),
		];
	}

	/**
	 * Password / email players (not native Keychain registrations).
	 *
	 * @param array<string, mixed> $user
	 */
	public static function isPasswordPlayer(array $user): bool
	{
		$email = strtolower(trim((string) ($user['email'] ?? '')));
		if ($email === '') {
			return true;
		}

		return !str_ends_with($email, '@hive.blog');
	}

	/**
	 * @param array<string, mixed> $user
	 */
	public static function linkedHiveAccount(array $user): string
	{
		$account = strtolower(trim((string) ($user['hive_account'] ?? '')));
		if ($account === '' || !HiveUtil::isAccountValid($account)) {
			return '';
		}

		return $account;
	}

	/**
	 * @param array<string, mixed> $user
	 */
	public static function needsUpgrade(array $user): bool
	{
		return self::isPasswordPlayer($user) && self::linkedHiveAccount($user) === '';
	}

	public static function normalizeAccount(string $account): string
	{
		return strtolower(trim($account));
	}

	public static function ownershipMessage(string $account): string
	{
		return self::normalizeAccount($account).' is my account.';
	}

	public function isHiveSignerConfigured(): bool
	{
		return trim($this->hiveSigner['clientId']) !== '';
	}

	/**
	 * Soft prompt for Hive-gated surfaces. Escape hatch always present.
	 *
	 * @param array<string, mixed> $user
	 * @return array{
	 *     show: bool,
	 *     linked: string,
	 *     settingsUrl: string,
	 *     canHiveSigner: bool,
	 *     createStatus: string,
	 *     keepPlaying: bool
	 * }
	 */
	public function softPrompt(array $user, string $settingsUrl = 'game.php?page=settings'): array
	{
		$linked = self::linkedHiveAccount($user);
		$needs = self::needsUpgrade($user);

		return [
			'show'          => $needs,
			'linked'        => $linked,
			'settingsUrl'   => $settingsUrl,
			'canHiveSigner' => $this->isHiveSignerConfigured(),
			'createStatus'  => self::CREATE_STATUS_PENDING,
			'keepPlaying'   => true,
		];
	}

	/**
	 * Settings panel state for password players.
	 *
	 * @param array<string, mixed> $user
	 * @return array{
	 *     needsUpgrade: bool,
	 *     isPasswordPlayer: bool,
	 *     linkedAccount: string,
	 *     canHiveSigner: bool,
	 *     hiveSignerUrl: string,
	 *     createStatus: string,
	 *     createAvailable: bool
	 * }
	 */
	public function upgradePanel(array $user, string $callbackUrl, string $oauthState = ''): array
	{
		$linked = self::linkedHiveAccount($user);
		$password = self::isPasswordPlayer($user);

		return [
			'needsUpgrade'     => $password && $linked === '',
			'isPasswordPlayer' => $password,
			'linkedAccount'    => $linked,
			'canHiveSigner'    => $this->isHiveSignerConfigured(),
			'hiveSignerUrl'    => $this->buildHiveSignerAuthorizeUrl($callbackUrl, $oauthState),
			'createStatus'     => self::CREATE_STATUS_PENDING,
			'createAvailable'  => false,
		];
	}

	public static function createOAuthState(): string
	{
		return bin2hex(random_bytes(16));
	}

	public static function isValidOAuthState(?string $expected, string $provided): bool
	{
		return is_string($expected)
			&& $expected !== ''
			&& $provided !== ''
			&& hash_equals($expected, $provided);
	}

	public function buildHiveSignerAuthorizeUrl(string $redirectUri, string $state = ''): string
	{
		if (!$this->isHiveSignerConfigured()) {
			return '';
		}

		// Implicit token response so the browser receives access_token in the
		// URL hash without requiring a HiveSigner client_secret exchange.
		$params = [
			'client_id'     => $this->hiveSigner['clientId'],
			'redirect_uri'  => $redirectUri,
			'response_type' => 'token',
			'scope'         => $this->hiveSigner['scope'],
		];
		if ($state !== '') {
			$params['state'] = $state;
		}
		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		$base = rtrim($this->hiveSigner['authorizeUrl'], '?&');

		return $base.(str_contains($base, '?') ? '&' : '?').$query;
	}

	/**
	 * Bind after Keychain (or equivalent) posting-key signature proof.
	 *
	 * @param array<string, mixed> $user
	 * @return array{ok: bool, error?: string, account?: string, method?: string}
	 */
	public function linkWithSignature(array $user, string $hiveAccount, string $signature, int $universe): array
	{
		$account = self::normalizeAccount($hiveAccount);
		if ($account === '' || $signature === '') {
			return ['ok' => false, 'error' => self::ERR_EMPTY];
		}
		if (!HiveUtil::isAccountValid($account)) {
			return ['ok' => false, 'error' => self::ERR_INVALID_ACCOUNT];
		}

		$current = self::linkedHiveAccount($user);
		if ($current !== '' && $current === $account) {
			return ['ok' => true, 'account' => $account, 'method' => self::METHOD_KEYCHAIN];
		}
		if ($current !== '') {
			return ['ok' => false, 'error' => self::ERR_ALREADY_LINKED];
		}

		if (!($this->isSignValid)($account, $signature)) {
			return ['ok' => false, 'error' => self::ERR_INVALID_SIGNATURE];
		}

		return $this->bindIfAvailable($user, $account, $universe, self::METHOD_KEYCHAIN);
	}

	/**
	 * Bind after HiveSigner OAuth access token verification.
	 *
	 * @param array<string, mixed> $user
	 * @return array{ok: bool, error?: string, account?: string, method?: string}
	 */
	public function linkWithHiveSignerToken(array $user, string $accessToken, int $universe): array
	{
		$token = trim($accessToken);
		if ($token === '') {
			return ['ok' => false, 'error' => self::ERR_EMPTY];
		}

		$current = self::linkedHiveAccount($user);
		if ($current !== '') {
			return ['ok' => false, 'error' => self::ERR_ALREADY_LINKED];
		}

		$username = ($this->fetchHiveSignerUser)($token);
		if ($username === null || $username === '') {
			return ['ok' => false, 'error' => self::ERR_INVALID_TOKEN];
		}

		$account = self::normalizeAccount($username);
		if (!HiveUtil::isAccountValid($account)) {
			return ['ok' => false, 'error' => self::ERR_INVALID_ACCOUNT];
		}

		return $this->bindIfAvailable($user, $account, $universe, self::METHOD_HIVESIGNER);
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{ok: bool, error?: string, account?: string, method?: string}
	 */
	private function bindIfAvailable(array $user, string $account, int $universe, string $method): array
	{
		$linked = ($this->countLinked)($account, $universe);
		if ($linked > 0) {
			return ['ok' => false, 'error' => self::ERR_TAKEN];
		}

		($this->bindAccount)((int) $user['id'], $account);

		return [
			'ok'      => true,
			'account' => $account,
			'method'  => $method,
		];
	}

	/**
	 * Default HiveSigner /api/me lookup. Overridable in unit tests.
	 */
	public function requestHiveSignerUser(string $accessToken): ?string
	{
		$authHeader = str_starts_with(strtolower($accessToken), 'bearer ')
			? $accessToken
			: 'Bearer '.$accessToken;

		$handle = curl_init();
		if ($handle === false) {
			return null;
		}

		curl_setopt_array($handle, [
			CURLOPT_URL            => $this->hiveSigner['meUrl'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => '{}',
			CURLOPT_HTTPHEADER     => [
				'Authorization: '.$authHeader,
				'Content-Type: application/json',
				'Accept: application/json',
			],
		]);

		$body = curl_exec($handle);
		$code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);

		if (!is_string($body) || $body === '' || $code < 200 || $code >= 300) {
			return null;
		}

		$data = json_decode($body, true);
		if (!is_array($data)) {
			return null;
		}

		$user = $data['user'] ?? $data['username'] ?? null;
		if (!is_string($user) || trim($user) === '') {
			return null;
		}

		return self::normalizeAccount($user);
	}
}
