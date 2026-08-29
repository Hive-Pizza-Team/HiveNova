<?php

namespace HiveNova\Core;

/**
 * Captures referral link params on the public lobby and carries them into registration.
 *
 * Settings share links use index.php?ref={userid}. Visitors stay on the lobby; cookies
 * plus register CTAs preserve tracking until signup.
 */
class ReferralCaptureService
{
	public const COOKIE_REF = 'ref';
	public const COOKIE_REF_UNI = 'ref_uni';
	public const COOKIE_TTL_SECONDS = 2592000; // 30 days

	/** @var callable(string, string, int): void */
	private $cookieWriter;

	/**
	 * @param callable(string $name, string $value, int $expire): void|null $cookieWriter
	 */
	public function __construct(?callable $cookieWriter = null)
	{
		$this->cookieWriter = $cookieWriter ?? static function (string $name, string $value, int $expire): void {
			HTTP::sendCookie($name, $value, $expire);
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
	 * Lobby capture: prefer query `ref` / `referralID`, else cookies. Persist on success.
	 *
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int}
	 */
	public function capture(DatabaseInterface $db, bool $refActive, array $request, array $cookies): array
	{
		return $this->resolve($db, $refActive, $request, $cookies, null);
	}

	/**
	 * Register show/send: prefer `referralID` / `ref` query, else cookies.
	 * When $universeId is set, require the referrer to exist in that universe.
	 *
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int}
	 */
	public function resolveForRegister(
		DatabaseInterface $db,
		bool $refActive,
		array $request,
		array $cookies,
		?int $universeId = null,
	): array {
		return $this->resolve($db, $refActive, $request, $cookies, $universeId);
	}

	/**
	 * @param array<string, mixed> $request
	 * @param array<string, mixed> $cookies
	 * @return array{id: int, name: string, universe: int}
	 */
	private function resolve(
		DatabaseInterface $db,
		bool $refActive,
		array $request,
		array $cookies,
		?int $universeId,
	): array {
		if (!$refActive) {
			return $this->emptyReferral();
		}

		$fromRequest = $this->requestReferralId($request);
		$id = $fromRequest;
		$fromCookie = false;
		if ($id <= 0) {
			$id = (int) ($cookies[self::COOKIE_REF] ?? 0);
			$fromCookie = $id > 0;
		}
		if ($id <= 0) {
			return $this->emptyReferral();
		}

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
		} else {
			$referrer = $this->lookupReferrer($db, $id);
		}

		if ($referrer === null) {
			if ($fromRequest > 0) {
				$this->clearCookies();
			}

			return $this->emptyReferral();
		}

		$this->persistCookies($referrer['id'], $referrer['universe']);

		return $referrer;
	}

	/**
	 * @return array{id: int, name: string, universe: int}
	 */
	private function emptyReferral(): array
	{
		return ['id' => 0, 'name' => '', 'universe' => 0];
	}

	/**
	 * @param array<string, mixed> $request
	 */
	private function requestReferralId(array $request): int
	{
		$id = (int) ($request['ref'] ?? 0);
		if ($id > 0) {
			return $id;
		}

		return (int) ($request['referralID'] ?? 0);
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

	private function persistCookies(int $referralId, int $universeId): void
	{
		$expire = TIMESTAMP + self::COOKIE_TTL_SECONDS;
		($this->cookieWriter)(self::COOKIE_REF, (string) $referralId, $expire);
		($this->cookieWriter)(self::COOKIE_REF_UNI, (string) $universeId, $expire);
	}

	private function clearCookies(): void
	{
		$expire = TIMESTAMP - 3600;
		($this->cookieWriter)(self::COOKIE_REF, '', $expire);
		($this->cookieWriter)(self::COOKIE_REF_UNI, '', $expire);
	}
}
