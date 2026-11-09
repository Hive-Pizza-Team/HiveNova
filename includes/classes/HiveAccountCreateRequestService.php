<?php

namespace HiveNova\Core;

/**
 * Admin-gated Hive account create requests (issue #638 P1).
 *
 * Players submit a desired @name; ops Approve/Reject. Claim-ticket broadcast
 * on Approve is intentionally out of scope for this slice — Approve only
 * records ops intent so the queue and eligibility path can ship first.
 */
class HiveAccountCreateRequestService
{
	public const STATUS_PENDING = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_CREATED = 'created';

	public const CODE_OK = 'ok';
	public const CODE_NOT_ELIGIBLE = 'not_eligible';
	public const CODE_INVALID_NAME = 'invalid_name';
	public const CODE_NAME_EXISTS = 'name_exists';
	public const CODE_PENDING_EXISTS = 'pending_exists';
	public const CODE_NOT_FOUND = 'not_found';
	public const CODE_NOT_PENDING = 'not_pending';
	public const CODE_EMPTY_REASON = 'empty_reason';

	/** @var callable(array<string, mixed>): bool */
	private $eligibilityChecker;

	/** @var callable(string): bool */
	private $hiveExistsChecker;

	/** @var callable(int, int): ?array */
	private $pendingLookup;

	/** @var callable(array<string, mixed>): int */
	private $inserter;

	/** @var callable(int): ?array */
	private $requestLookup;

	/** @var callable(int, string, int, string): bool */
	private $statusUpdater;

	/** @var callable(int): list<array<string, mixed>> */
	private $pendingLister;

	/**
	 * @param callable(array<string, mixed>): bool|null $eligibilityChecker
	 * @param callable(string): bool|null $hiveExistsChecker
	 * @param callable(int, int): ?array|null $pendingLookup
	 * @param callable(array<string, mixed>): int|null $inserter
	 * @param callable(int): ?array|null $requestLookup
	 * @param callable(int, string, int, string): bool|null $statusUpdater
	 * @param callable(int): list<array<string, mixed>>|null $pendingLister
	 */
	public function __construct(
		?callable $eligibilityChecker = null,
		?callable $hiveExistsChecker = null,
		?callable $pendingLookup = null,
		?callable $inserter = null,
		?callable $requestLookup = null,
		?callable $statusUpdater = null,
		?callable $pendingLister = null
	) {
		$this->eligibilityChecker = $eligibilityChecker ?? static function (array $user): bool {
			return self::defaultIsEligible($user);
		};
		$this->hiveExistsChecker = $hiveExistsChecker ?? static function (string $name): bool {
			return HiveUtil::accountExists($name);
		};
		$this->pendingLookup = $pendingLookup ?? static function (int $userId, int $universeId): ?array {
			$sql = 'SELECT id, user_id, universe, desired_name, status, reject_reason, created_at, reviewed_at, reviewed_by
				FROM %%HIVE_ACCOUNT_CREATE_REQUESTS%%
				WHERE user_id = :userId AND universe = :universe AND status = :status
				ORDER BY id DESC LIMIT 1;';
			$row = Database::get()->selectSingle($sql, [
				':userId' => $userId,
				':universe' => $universeId,
				':status' => self::STATUS_PENDING,
			]);

			return is_array($row) ? $row : null;
		};
		$this->inserter = $inserter ?? static function (array $row): int {
			$sql = 'INSERT INTO %%HIVE_ACCOUNT_CREATE_REQUESTS%%
				SET user_id = :userId,
					universe = :universe,
					desired_name = :desiredName,
					status = :status,
					reject_reason = :rejectReason,
					created_at = :createdAt,
					reviewed_at = 0,
					reviewed_by = 0;';
			Database::get()->insert($sql, [
				':userId' => (int) $row['user_id'],
				':universe' => (int) $row['universe'],
				':desiredName' => (string) $row['desired_name'],
				':status' => (string) $row['status'],
				':rejectReason' => (string) ($row['reject_reason'] ?? ''),
				':createdAt' => (int) $row['created_at'],
			]);

			return (int) Database::get()->lastInsertId();
		};
		$this->requestLookup = $requestLookup ?? static function (int $requestId): ?array {
			$sql = 'SELECT id, user_id, universe, desired_name, status, reject_reason, created_at, reviewed_at, reviewed_by
				FROM %%HIVE_ACCOUNT_CREATE_REQUESTS%% WHERE id = :id LIMIT 1;';
			$row = Database::get()->selectSingle($sql, [':id' => $requestId]);

			return is_array($row) ? $row : null;
		};
		$this->statusUpdater = $statusUpdater ?? static function (
			int $requestId,
			string $status,
			int $adminId,
			string $rejectReason
		): bool {
			$sql = 'UPDATE %%HIVE_ACCOUNT_CREATE_REQUESTS%%
				SET status = :status,
					reject_reason = :rejectReason,
					reviewed_at = :reviewedAt,
					reviewed_by = :reviewedBy
				WHERE id = :id AND status = :pending;';
			Database::get()->update($sql, [
				':status' => $status,
				':rejectReason' => $rejectReason,
				':reviewedAt' => TIMESTAMP,
				':reviewedBy' => $adminId,
				':id' => $requestId,
				':pending' => self::STATUS_PENDING,
			]);

			return true;
		};
		$this->pendingLister = $pendingLister ?? static function (int $universeId): array {
			$sql = 'SELECT r.id, r.user_id, r.universe, r.desired_name, r.status, r.reject_reason, r.created_at,
					u.username, u.email
				FROM %%HIVE_ACCOUNT_CREATE_REQUESTS%% r
				LEFT JOIN %%USERS%% u ON u.id = r.user_id
				WHERE r.universe = :universe AND r.status = :status
				ORDER BY r.created_at ASC, r.id ASC;';
			$rows = Database::get()->select($sql, [
				':universe' => $universeId,
				':status' => self::STATUS_PENDING,
			]);

			return is_array($rows) ? $rows : [];
		};
	}

	/**
	 * @param array<string, mixed> $user
	 */
	public static function defaultIsEligible(array $user): bool
	{
		if (!HiveAccountUpgradeService::isPasswordPlayer($user)) {
			return false;
		}

		return HiveAccountUpgradeService::linkedHiveAccount($user) === '';
	}

	/**
	 * @param array<string, mixed> $user
	 */
	public function isEligible(array $user): bool
	{
		return (bool) ($this->eligibilityChecker)($user);
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{ok: bool, code: string, request_id: ?int, desired_name: ?string}
	 */
	public function submit(array $user, int $universeId, string $desiredName, int $now): array
	{
		if (!$this->isEligible($user)) {
			return $this->fail(self::CODE_NOT_ELIGIBLE);
		}

		$userId = (int) ($user['id'] ?? 0);
		if ($userId <= 0 || $universeId <= 0) {
			return $this->fail(self::CODE_NOT_ELIGIBLE);
		}

		$name = HiveAccountUpgradeService::normalizeAccount($desiredName);
		if ($name === '' || !HiveUtil::isAccountValid($name)) {
			return $this->fail(self::CODE_INVALID_NAME);
		}

		if (($this->hiveExistsChecker)($name)) {
			return $this->fail(self::CODE_NAME_EXISTS);
		}

		if (($this->pendingLookup)($userId, $universeId) !== null) {
			return $this->fail(self::CODE_PENDING_EXISTS);
		}

		$requestId = (int) ($this->inserter)([
			'user_id' => $userId,
			'universe' => $universeId,
			'desired_name' => $name,
			'status' => self::STATUS_PENDING,
			'reject_reason' => '',
			'created_at' => $now,
		]);

		return [
			'ok' => true,
			'code' => self::CODE_OK,
			'request_id' => $requestId,
			'desired_name' => $name,
		];
	}

	/**
	 * @return array{ok: bool, code: string, request: ?array<string, mixed>}
	 */
	public function approve(int $requestId, int $adminId): array
	{
		$row = ($this->requestLookup)($requestId);
		if ($row === null) {
			return ['ok' => false, 'code' => self::CODE_NOT_FOUND, 'request' => null];
		}
		if (($row['status'] ?? '') !== self::STATUS_PENDING) {
			return ['ok' => false, 'code' => self::CODE_NOT_PENDING, 'request' => $row];
		}

		($this->statusUpdater)($requestId, self::STATUS_APPROVED, $adminId, '');
		$row['status'] = self::STATUS_APPROVED;
		$row['reviewed_by'] = $adminId;

		return ['ok' => true, 'code' => self::CODE_OK, 'request' => $row];
	}

	/**
	 * @return array{ok: bool, code: string, request: ?array<string, mixed>}
	 */
	public function reject(int $requestId, int $adminId, string $reason): array
	{
		$reason = trim($reason);
		if ($reason === '') {
			return ['ok' => false, 'code' => self::CODE_EMPTY_REASON, 'request' => null];
		}

		$row = ($this->requestLookup)($requestId);
		if ($row === null) {
			return ['ok' => false, 'code' => self::CODE_NOT_FOUND, 'request' => null];
		}
		if (($row['status'] ?? '') !== self::STATUS_PENDING) {
			return ['ok' => false, 'code' => self::CODE_NOT_PENDING, 'request' => $row];
		}

		($this->statusUpdater)($requestId, self::STATUS_REJECTED, $adminId, $reason);
		$row['status'] = self::STATUS_REJECTED;
		$row['reject_reason'] = $reason;
		$row['reviewed_by'] = $adminId;

		return ['ok' => true, 'code' => self::CODE_OK, 'request' => $row];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listPending(int $universeId): array
	{
		return ($this->pendingLister)($universeId);
	}

	/**
	 * @return ?array<string, mixed>
	 */
	public function findPendingForUser(int $userId, int $universeId): ?array
	{
		return ($this->pendingLookup)($userId, $universeId);
	}

	/**
	 * @return array{ok: bool, code: string, request_id: ?int, desired_name: ?string}
	 */
	private function fail(string $code): array
	{
		return [
			'ok' => false,
			'code' => $code,
			'request_id' => null,
			'desired_name' => null,
		];
	}
}
