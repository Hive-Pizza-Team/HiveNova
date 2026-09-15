<?php

namespace HiveNova\Core;

/**
 * Early gates for the register live username Ajax endpoint.
 *
 * Rate-limit first (fail-closed, skip DB/Hive), then require an
 * open-for-registration universe — same rules as ShowRegisterPage::send().
 */
class RegisterUsernameCheckAccess
{
	public const REASON_RATE_LIMITED = 'rate_limited';
	public const REASON_CLOSED = 'closed';
	public const HTTP_TOO_MANY_REQUESTS = 429;

	/**
	 * @param callable(int):bool|null $isOpenForRegistration
	 * @return array{
	 *     allow: bool,
	 *     universeId: int,
	 *     reason: ?string,
	 *     httpStatus: int,
	 *     retryAfter: int
	 * }
	 */
	public static function evaluate(
		string $ip,
		int $requestedUniverseId,
		int $fallbackUniverseId,
		?string $sessionId = null,
		?RegisterUsernameCheckLimiter $limiter = null,
		?callable $isOpenForRegistration = null,
		?int $now = null
	): array {
		$limiter ??= new RegisterUsernameCheckLimiter();
		$limited = $limiter->consume($ip, $sessionId, $now);
		if (!$limited['allowed']) {
			return [
				'allow' => false,
				'universeId' => 0,
				'reason' => self::REASON_RATE_LIMITED,
				'httpStatus' => self::HTTP_TOO_MANY_REQUESTS,
				'retryAfter' => max(1, (int) $limited['retryAfter']),
			];
		}

		$universeId = $requestedUniverseId > 0 ? $requestedUniverseId : $fallbackUniverseId;
		$isOpen = $isOpenForRegistration ?? [LoginUniverseDefaults::class, 'isOpenForRegistration'];
		if ($universeId <= 0 || !((bool) $isOpen($universeId))) {
			return [
				'allow' => false,
				'universeId' => $universeId,
				'reason' => self::REASON_CLOSED,
				'httpStatus' => 200,
				'retryAfter' => 0,
			];
		}

		return [
			'allow' => true,
			'universeId' => $universeId,
			'reason' => null,
			'httpStatus' => 200,
			'retryAfter' => 0,
		];
	}

	/**
	 * Run $lookup only after rate-limit and open-universe gates pass.
	 *
	 * @param callable(int):mixed $lookup
	 * @param callable(int):bool|null $isOpenForRegistration
	 * @return array{
	 *     allow: bool,
	 *     universeId: int,
	 *     reason: ?string,
	 *     httpStatus: int,
	 *     retryAfter: int,
	 *     result: mixed
	 * }
	 */
	public static function runLookup(
		callable $lookup,
		string $ip,
		int $requestedUniverseId,
		int $fallbackUniverseId,
		?string $sessionId = null,
		?RegisterUsernameCheckLimiter $limiter = null,
		?callable $isOpenForRegistration = null,
		?int $now = null
	): array {
		$gate = self::evaluate(
			$ip,
			$requestedUniverseId,
			$fallbackUniverseId,
			$sessionId,
			$limiter,
			$isOpenForRegistration,
			$now
		);
		$gate['result'] = null;
		if ($gate['allow']) {
			$gate['result'] = $lookup($gate['universeId']);
		}

		return $gate;
	}

	/**
	 * JSON the frontend can ignore safely (ok: false, no taken/available reasons).
	 *
	 * @return array{
	 *     ok: false,
	 *     available: false,
	 *     reason: string,
	 *     suggestions: list<string>,
	 *     message: string,
	 *     hiveOwn: false
	 * }
	 */
	public static function denyPayload(string $reason, string $message = ''): array
	{
		return [
			'ok' => false,
			'available' => false,
			'reason' => $reason,
			'suggestions' => [],
			'message' => $message,
			'hiveOwn' => false,
		];
	}
}
