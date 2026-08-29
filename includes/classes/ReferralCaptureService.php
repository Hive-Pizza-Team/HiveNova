<?php

namespace HiveNova\Core;

/**
 * Captures referral link params on the public lobby and carries them into registration.
 *
 * Settings share links use index.php?ref={userid}. Visitors stay on the lobby; cookies
 * plus register CTAs preserve tracking until signup.
 *
 * Public codes may be aliases (multi-uni maps) or raw user ids. Cookie `ref` always stores
 * the public code; the resolved user id is per selected universe.
 *
 * ref_active is evaluated for the referrer's (or target) universe — not Universe::current()
 * on the multi-uni lobby, which often has referrals disabled while live unis do not.
 */
class ReferralCaptureService
{
	public const COOKIE_REF = 'ref';
	public const COOKIE_REF_UNI = 'ref_uni';
	public const COOKIE_TTL_SECONDS = 2592000; // 30 days

	/**
	 * Public referral codes that map to different user ids per universe.
	 *
	 * @var array<int, array<int, int>> publicCode => [universeId => userId]
	 */
	private const ALIASES = [
		1 => [1 => 1, 3 => 712],
	];

	/** @var callable(string, string, int): void */
	private $cookieWriter;

	/** @var callable(int): bool */
	private $refActiveChecker;

	/**
	 * @param callable(string $name, string $value, int $expire): void|null $cookieWriter
	 * @param callable(int $universeId): bool|null $refActiveChecker
	 */
	public function __construct(?callable $cookieWriter = null, ?callable $refActiveChecker = null)
	{
		$this->cookieWriter = $cookieWriter ?? static function (string $name, string $value, int $expire): void {
			HTTP::sendCookie($name, $value, $expire);
		};
		$this->refActiveChecker = $refActiveChecker ?? static function (int $universeId): bool {
			return (int) (Config::get($universeId)->ref_active ?? 0) === 1;
		};
	}

	/**
	 * @return array{ref: int, referralID: int}
	 */
	public static function requestBag(): array
	{
		return [
			'ref'        => HTTP::_GP('ref', 0),
			'referralID' => HTTP::_GP('referralID', 0),
		];
	}

	public static function isAlias(int $code): bool
	{
		return $code > 0 && isset(self::ALIASES[$code]);
	}

	/**
	 * Resolved user id for an alias in a universe, or 0 if unmapped.
	 */
	public static function aliasUserId(int $code, int $universeId): int
	{
		if ($code <= 0 || $universeId <= 0 || !isset(self::ALIASES[$code][$universeId])) {
			return 0;
		}

		return (int) self::ALIASES[$code][$universeId];
	}

	/**
	 * Public code priority: query ref, then cookie, then referralID.
	 * Cookie wins over form referralID so resolved ids do not rewrite alias cookies.
	 *
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 */
	public static function publicCodeFrom(array $request, array $cookies): int
	{
		$ref = (int) ($request['ref'] ?? 0);
		if ($ref > 0) {
			return $ref;
		}

		$cookie = (int) ($cookies[self::COOKIE_REF] ?? 0);
		if ($cookie > 0) {
			return $cookie;
		}

		return (int) ($request['referralID'] ?? 0);
	}

	/**
	 * True when any available universe has referral links enabled.
	 */
	public static function anyUniverseHasReferralsActive(): bool
	{
		foreach (Universe::availableUniverses() as $uniId) {
			if ((int) (Config::get((int) $uniId)->ref_active ?? 0) === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the register CTA URL, optionally with referral + universe context.
	 */
	public function registerUrl(int $referralId = 0, int $universeId = 0): string
	{
		if ($referralId <= 0) {
			return 'index.php?page=register';
		}

		$query = [
			'page'       => 'register',
			'referralID' => $referralId,
		];
		if ($universeId > 0) {
			$query['uni'] = $universeId;
		}

		return 'index.php?' . http_build_query($query);
	}

	/**
	 * If the referrer's universe is open for registration, return its id; else 0.
	 *
	 * @param array{id?: int, universe?: int} $referral
	 */
	public function registrationUniverseId(array $referral, ?object $config = null): int
	{
		$universeId = (int) ($referral['universe'] ?? 0);
		if ($universeId <= 0 || (int) ($referral['id'] ?? 0) <= 0) {
			return 0;
		}

		$config ??= Config::get($universeId);
		if (!self::isUniverseOpenForRegistration($config)) {
			return 0;
		}

		return $universeId;
	}

	public static function isUniverseOpenForRegistration(object $config): bool
	{
		return (int) ($config->game_disable ?? 0) === 1
			&& (int) ($config->reg_closed ?? 0) === 0;
	}

	/**
	 * Lobby capture: prefer query `ref`, else cookies, else `referralID`. Persist on success.
	 *
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int, code: int}
	 */
	public function capture(DatabaseInterface $db, array $request, array $cookies): array
	{
		return $this->resolve($db, $request, $cookies, null);
	}

	/**
	 * Register show/send. When $universeId is set, resolve for that universe only.
	 *
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int, code: int}
	 */
	public function resolveForRegister(
		DatabaseInterface $db,
		array $request,
		array $cookies,
		?int $universeId = null,
	): array {
		return $this->resolve($db, $request, $cookies, $universeId);
	}

	/**
	 * Per-universe resolved referrers for a public code (JS dropdown sync).
	 *
	 * @return array<int, array{id: int, name: string}>
	 */
	public function resolveByUniverse(DatabaseInterface $db, int $publicCode): array
	{
		if ($publicCode <= 0) {
			return [];
		}

		$map = [];
		$universeIds = self::isAlias($publicCode)
			? array_keys(self::ALIASES[$publicCode])
			: Universe::availableUniverses();

		foreach ($universeIds as $uniId) {
			$uniId = (int) $uniId;
			if ($uniId <= 0 || !$this->isRefActive($uniId)) {
				continue;
			}

			$userId = self::isAlias($publicCode)
				? self::aliasUserId($publicCode, $uniId)
				: $publicCode;
			if ($userId <= 0) {
				continue;
			}

			$referrer = $this->lookupReferrerInUniverse($db, $userId, $uniId);
			if ($referrer === null) {
				continue;
			}

			$map[$uniId] = [
				'id'   => $referrer['id'],
				'name' => $referrer['name'],
			];
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int, code: int}
	 */
	private function resolve(
		DatabaseInterface $db,
		array $request,
		array $cookies,
		?int $universeId,
	): array {
		$publicCode = self::publicCodeFrom($request, $cookies);
		if ($publicCode <= 0) {
			return $this->emptyReferral();
		}

		$fromRefQuery = (int) ($request['ref'] ?? 0) > 0;
		$fromCookie = !$fromRefQuery && (int) ($cookies[self::COOKIE_REF] ?? 0) > 0;
		$clearOnMiss = $fromRefQuery
			|| (!$fromCookie && (int) ($request['referralID'] ?? 0) > 0);

		if (self::isAlias($publicCode)) {
			$referrer = $this->resolveAlias($db, $publicCode, $universeId);
		} else {
			$referrer = $this->resolveRawId($db, $publicCode, $universeId, $fromCookie, $cookies);
		}

		if ($referrer === null) {
			if ($clearOnMiss) {
				$this->clearCookies();
			}

			return $this->emptyReferral();
		}

		$activeUni = ($universeId !== null && $universeId > 0)
			? $universeId
			: (int) $referrer['universe'];
		if (!$this->isRefActive($activeUni)) {
			return $this->emptyReferral();
		}

		$this->persistCookies($publicCode, (int) $referrer['universe']);

		$referrer['code'] = $publicCode;

		return $referrer;
	}

	/**
	 * @return array{id: int, name: string, universe: int}|null
	 */
	private function resolveAlias(DatabaseInterface $db, int $publicCode, ?int $universeId): ?array
	{
		if ($universeId !== null && $universeId > 0) {
			$userId = self::aliasUserId($publicCode, $universeId);
			if ($userId <= 0) {
				return null;
			}

			return $this->lookupReferrerInUniverse($db, $userId, $universeId);
		}

		foreach (self::ALIASES[$publicCode] as $uniId => $userId) {
			$uniId = (int) $uniId;
			$userId = (int) $userId;
			if ($uniId <= 0 || $userId <= 0 || !$this->isRefActive($uniId)) {
				continue;
			}

			$referrer = $this->lookupReferrerInUniverse($db, $userId, $uniId);
			if ($referrer !== null) {
				return $referrer;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int}|null
	 */
	private function resolveRawId(
		DatabaseInterface $db,
		int $id,
		?int $universeId,
		bool $fromCookie,
		array $cookies,
	): ?array {
		$scopeUni = ($universeId !== null && $universeId > 0) ? $universeId : 0;
		if ($scopeUni <= 0 && $fromCookie) {
			$scopeUni = (int) ($cookies[self::COOKIE_REF_UNI] ?? 0);
		}

		if ($scopeUni > 0) {
			$referrer = $this->lookupReferrerInUniverse($db, $id, $scopeUni);
			// Cookie uni can go stale; fall back to global id lookup when scoping came from cookie only.
			if ($referrer === null && $fromCookie && ($universeId === null || $universeId <= 0)) {
				$referrer = $this->lookupReferrer($db, $id);
			}

			return $referrer;
		}

		return $this->lookupReferrer($db, $id);
	}

	private function isRefActive(int $universeId): bool
	{
		if ($universeId <= 0) {
			return false;
		}

		return (bool) ($this->refActiveChecker)($universeId);
	}

	/**
	 * @return array{id: int, name: string, universe: int, code: int}
	 */
	private function emptyReferral(): array
	{
		return ['id' => 0, 'name' => '', 'universe' => 0, 'code' => 0];
	}

	/**
	 * @return array{id: int, name: string, universe: int}|null
	 */
	private function lookupReferrer(DatabaseInterface $db, int $referralId): ?array
	{
		$row = $db->selectSingle(
			'SELECT id, username, universe FROM %%USERS%% WHERE id = :referralID;',
			[':referralID' => $referralId]
		);

		return $this->mapReferrerRow($row);
	}

	/**
	 * @return array{id: int, name: string, universe: int}|null
	 */
	private function lookupReferrerInUniverse(DatabaseInterface $db, int $referralId, int $universeId): ?array
	{
		$row = $db->selectSingle(
			'SELECT id, username, universe FROM %%USERS%% WHERE id = :referralID AND universe = :universe;',
			[
				':referralID' => $referralId,
				':universe'   => $universeId,
			]
		);

		return $this->mapReferrerRow($row);
	}

	/**
	 * @param array<string, mixed>|false|null $row
	 * @return array{id: int, name: string, universe: int}|null
	 */
	private function mapReferrerRow($row): ?array
	{
		if (!is_array($row) || empty($row['id'])) {
			return null;
		}

		return [
			'id'       => (int) $row['id'],
			'name'     => (string) ($row['username'] ?? ''),
			'universe' => (int) ($row['universe'] ?? 0),
		];
	}

	private function persistCookies(int $publicCode, int $universeId): void
	{
		$expire = TIMESTAMP + self::COOKIE_TTL_SECONDS;
		($this->cookieWriter)(self::COOKIE_REF, (string) $publicCode, $expire);
		($this->cookieWriter)(self::COOKIE_REF_UNI, (string) $universeId, $expire);
	}

	private function clearCookies(): void
	{
		$expire = TIMESTAMP - 3600;
		($this->cookieWriter)(self::COOKIE_REF, '', $expire);
		($this->cookieWriter)(self::COOKIE_REF_UNI, '', $expire);
	}
}
