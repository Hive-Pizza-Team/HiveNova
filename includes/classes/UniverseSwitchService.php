<?php

namespace HiveNova\Core;

/**
 * Lists sibling player accounts across universes and issues one-time switch tokens.
 *
 * Accounts are linked when they share a valid Hive account, or (only when neither
 * side has a Hive account) the same permanent email (email_2). Sessions are
 * cookie-scoped per /uniN/ path, so switching logs out the current universe and
 * redeems a short-lived token under the target universe URL.
 */
class UniverseSwitchService
{
	public const TOKEN_TTL_SECONDS = 90;
	public const CSRF_SESSION_KEY = 'universe_switch_csrf';

	/**
	 * @param array<string, mixed> $user Current player row (id, universe, hive_account, email_2)
	 * @param list<int> $availableUniverses
	 * @param callable(int): string $universeNameResolver
	 * @return list<array{universeId: int, universeName: string, userId: int, username: string, isCurrent: bool}>
	 */
	public function listLinkedAccounts(
		array $user,
		DatabaseInterface $db,
		array $availableUniverses,
		callable $universeNameResolver,
	): array {
		$currentId = (int) ($user['id'] ?? 0);
		$currentUniverse = (int) ($user['universe'] ?? 0);
		if ($currentId <= 0 || $currentUniverse <= 0) {
			return [];
		}

		$hive = $this->normalizedHive((string) ($user['hive_account'] ?? ''));
		$email = $this->normalizedEmail((string) ($user['email_2'] ?? ''));
		if ($hive === '' && $email === '') {
			return [[
				'universeId'   => $currentUniverse,
				'universeName' => (string) $universeNameResolver($currentUniverse),
				'userId'       => $currentId,
				'username'     => (string) ($user['username'] ?? ''),
				'isCurrent'    => true,
			]];
		}

		$params = [':userId' => $currentId];
		$clauses = [];
		if ($hive !== '') {
			$clauses[] = 'LOWER(TRIM(hive_account)) = :hive';
			$params[':hive'] = $hive;
		}
		if ($email !== '' && $hive === '') {
			// Email matching only when the current account has no Hive identity.
			$clauses[] = 'LOWER(TRIM(email_2)) = :email';
			$params[':email'] = $email;
		}

		$sql = 'SELECT id, universe, username, hive_account, email_2, bana
			FROM %%USERS%%
			WHERE id = :userId OR (' . implode(' OR ', $clauses) . ')';
		$rows = $db->select($sql, $params);
		if (!is_array($rows)) {
			$rows = [];
		}

		$byUniverse = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$universeId = (int) ($row['universe'] ?? 0);
			$userId = (int) ($row['id'] ?? 0);
			if ($universeId <= 0 || $userId <= 0 || !in_array($universeId, $availableUniverses, true)) {
				continue;
			}
			if ((int) ($row['bana'] ?? 0) === 1 && $userId !== $currentId) {
				continue;
			}
			if (!$this->accountsLinked($user, $row) && $userId !== $currentId) {
				continue;
			}
			// Prefer the current user's own row when multiple matches exist in one uni.
			if (isset($byUniverse[$universeId]) && $userId !== $currentId) {
				continue;
			}
			$byUniverse[$universeId] = [
				'universeId'   => $universeId,
				'universeName' => (string) $universeNameResolver($universeId),
				'userId'       => $userId,
				'username'     => (string) ($row['username'] ?? ''),
				'isCurrent'    => $universeId === $currentUniverse,
			];
		}

		if (!isset($byUniverse[$currentUniverse])) {
			$byUniverse[$currentUniverse] = [
				'universeId'   => $currentUniverse,
				'universeName' => (string) $universeNameResolver($currentUniverse),
				'userId'       => $currentId,
				'username'     => (string) ($user['username'] ?? ''),
				'isCurrent'    => true,
			];
		}

		ksort($byUniverse);

		return array_values($byUniverse);
	}

	/**
	 * @param array<string, mixed> $fromUser
	 * @param array<string, mixed> $toUser
	 */
	public function accountsLinked(array $fromUser, array $toUser): bool
	{
		$fromHive = $this->normalizedHive((string) ($fromUser['hive_account'] ?? ''));
		$toHive = $this->normalizedHive((string) ($toUser['hive_account'] ?? ''));

		// Any Hive identity on either side: require an exact Hive match; never
		// fall through to email (email_2 is not proof of the same owner).
		if ($fromHive !== '' || $toHive !== '') {
			return $fromHive !== '' && $fromHive === $toHive;
		}

		$fromEmail = $this->normalizedEmail((string) ($fromUser['email_2'] ?? ''));
		$toEmail = $this->normalizedEmail((string) ($toUser['email_2'] ?? ''));

		return $fromEmail !== '' && $fromEmail === $toEmail;
	}

	/**
	 * @param array{userId: int, universe: int}|null
	 */
	public function findLinkedTarget(
		array $fromUser,
		int $targetUniverse,
		DatabaseInterface $db,
		array $availableUniverses,
	): ?array {
		if ($targetUniverse <= 0 || !in_array($targetUniverse, $availableUniverses, true)) {
			return null;
		}
		if ($targetUniverse === (int) ($fromUser['universe'] ?? 0)) {
			return null;
		}

		$accounts = $this->listLinkedAccounts(
			$fromUser,
			$db,
			$availableUniverses,
			static fn (int $id): string => (string) $id
		);

		foreach ($accounts as $account) {
			if ((int) $account['universeId'] === $targetUniverse && !$account['isCurrent']) {
				return [
					'userId'   => (int) $account['userId'],
					'universe' => (int) $account['universeId'],
				];
			}
		}

		return null;
	}

	/**
	 * Fully validate a switch target before dropping the source session.
	 *
	 * @param list<int> $availableUniverses
	 * @return array{userId: int, universe: int}|null
	 */
	public function validateSwitchTarget(
		array $fromUser,
		int $targetUniverse,
		DatabaseInterface $db,
		array $availableUniverses,
		bool $targetUniverseOpen,
	): ?array {
		if (!$targetUniverseOpen) {
			return null;
		}

		$target = $this->findLinkedTarget($fromUser, $targetUniverse, $db, $availableUniverses);
		if ($target === null) {
			return null;
		}

		$row = $db->selectSingle(
			'SELECT id, universe, username, hive_account, email_2, bana
			FROM %%USERS%% WHERE id = :userId',
			[':userId' => (int) $target['userId']]
		);
		if (!is_array($row) || empty($row)) {
			return null;
		}
		if ((int) ($row['bana'] ?? 0) === 1) {
			return null;
		}
		if ((int) ($row['universe'] ?? 0) !== (int) $target['universe']) {
			return null;
		}
		if (!$this->accountsLinked($fromUser, $row)) {
			return null;
		}

		return [
			'userId'   => (int) $row['id'],
			'universe' => (int) $row['universe'],
		];
	}

	/**
	 * Confirm token payload still refers to linked, allowed accounts at redeem time.
	 *
	 * @param array{fromUserId: int, toUserId: int, toUniverse: int} $payload
	 * @param array<string, mixed>|null $fromUser
	 * @param array<string, mixed>|null $toUser
	 */
	public function canRedeemPayload(
		array $payload,
		?array $fromUser,
		?array $toUser,
		bool $targetUniverseOpen,
	): bool {
		if (!$targetUniverseOpen) {
			return false;
		}
		if (!is_array($fromUser) || !is_array($toUser) || empty($fromUser) || empty($toUser)) {
			return false;
		}
		if ((int) ($toUser['bana'] ?? 0) === 1) {
			return false;
		}
		if ((int) ($toUser['id'] ?? 0) !== (int) ($payload['toUserId'] ?? 0)) {
			return false;
		}
		if ((int) ($toUser['universe'] ?? 0) !== (int) ($payload['toUniverse'] ?? 0)) {
			return false;
		}
		if ((int) ($fromUser['id'] ?? 0) !== (int) ($payload['fromUserId'] ?? 0)) {
			return false;
		}

		return $this->accountsLinked($fromUser, $toUser);
	}

	public function issueToken(int $fromUserId, int $toUserId, int $toUniverse, string $cacheDir): string
	{
		$token = bin2hex(random_bytes(16));
		$payload = [
			'fromUserId' => $fromUserId,
			'toUserId'   => $toUserId,
			'toUniverse' => $toUniverse,
			'exp'        => time() + self::TOKEN_TTL_SECONDS,
		];
		$path = $this->tokenPath($cacheDir, $token);
		$dir = dirname($path);
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}
		file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX);

		return $token;
	}

	/**
	 * Read a switch token without consuming it (wrong-universe hits must not burn it).
	 *
	 * @return array{fromUserId: int, toUserId: int, toUniverse: int}|null
	 */
	public function peekToken(string $token, string $cacheDir): ?array
	{
		if (!$this->isValidTokenFormat($token)) {
			return null;
		}

		$path = $this->tokenPath($cacheDir, $token);
		if (!is_file($path)) {
			return null;
		}

		$raw = file_get_contents($path);
		if ($raw === false || $raw === '') {
			@unlink($path);
			return null;
		}

		$payload = $this->decodeTokenPayload($raw);
		if ($payload === null) {
			@unlink($path);
			return null;
		}

		return $payload;
	}

	/**
	 * Atomically claim and consume a switch token.
	 *
	 * @return array{fromUserId: int, toUserId: int, toUniverse: int}|null
	 */
	public function consumeToken(string $token, string $cacheDir): ?array
	{
		if (!$this->isValidTokenFormat($token)) {
			return null;
		}

		$path = $this->tokenPath($cacheDir, $token);
		$claimPath = $path . '.claim.' . bin2hex(random_bytes(8));
		if (!@rename($path, $claimPath)) {
			return null;
		}

		$raw = file_get_contents($claimPath);
		@unlink($claimPath);
		if ($raw === false || $raw === '') {
			return null;
		}

		return $this->decodeTokenPayload($raw);
	}

	/**
	 * Remove expired/invalid universe-switch token files from the cache directory.
	 */
	public function sweepExpiredTokens(string $cacheDir): int
	{
		$pattern = rtrim($cacheDir, '/\\') . '/universe_switch_*.json';
		$removed = 0;
		foreach (glob($pattern) ?: [] as $path) {
			if (!is_file($path)) {
				continue;
			}
			$raw = file_get_contents($path);
			if ($raw === false || $raw === '' || $this->decodeTokenPayload($raw) === null) {
				if (@unlink($path)) {
					$removed++;
				}
			}
		}

		return $removed;
	}

	/**
	 * @return array{fromUserId: int, toUserId: int, toUniverse: int}|null
	 */
	private function decodeTokenPayload(string $raw): ?array
	{
		try {
			$data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}

		if (!is_array($data)) {
			return null;
		}

		$exp = (int) ($data['exp'] ?? 0);
		$toUserId = (int) ($data['toUserId'] ?? 0);
		$toUniverse = (int) ($data['toUniverse'] ?? 0);
		$fromUserId = (int) ($data['fromUserId'] ?? 0);
		if ($exp < time() || $toUserId <= 0 || $toUniverse <= 0 || $fromUserId <= 0) {
			return null;
		}

		return [
			'fromUserId' => $fromUserId,
			'toUserId'   => $toUserId,
			'toUniverse' => $toUniverse,
		];
	}

	public function buildRedeemUrl(string $httpBase, int $universe, string $token, bool $wildcast = false): string
	{
		$query = 'index.php?page=universeSwitch&token=' . rawurlencode($token);
		$trimmed = rtrim($httpBase, '/');
		if ($wildcast) {
			if (preg_match('#^(https?://)([^/]+)(/.*)?$#', $trimmed, $m)) {
				$host = self::stripWildcastHost($m[2]);
				return $m[1] . 'uni' . $universe . '.' . $host . ($m[3] ?? '') . '/' . $query;
			}

			return $trimmed . '/' . $query;
		}

		return $trimmed . '/uni' . $universe . '/' . $query;
	}

	/**
	 * Strip a leading uniN label from a host (UNIS_WILDCAST), matching AbstractLoginPage.
	 */
	public static function stripWildcastHost(string $host): string
	{
		$hostParts = explode('.', $host);
		if ($hostParts !== [] && preg_match('/uni[0-9]+/', $hostParts[0])) {
			array_shift($hostParts);
		}

		return implode('.', $hostParts);
	}

	public function issueCsrfToken(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE && session_status() !== PHP_SESSION_DISABLED) {
			@session_start();
		}
		$token = bin2hex(random_bytes(16));
		$_SESSION[self::CSRF_SESSION_KEY] = $token;

		return $token;
	}

	public function validateCsrfToken(?string $token): bool
	{
		$expected = $_SESSION[self::CSRF_SESSION_KEY] ?? '';
		if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
			return false;
		}

		return hash_equals($expected, $token);
	}

	public function isValidTokenFormat(string $token): bool
	{
		return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
	}

	public function normalizedHive(string $hive): string
	{
		$hive = strtolower(trim($hive));
		if ($hive === '' || !HiveUtil::isAccountValid($hive)) {
			return '';
		}

		return $hive;
	}

	public function normalizedEmail(string $email): string
	{
		$email = strtolower(trim($email));
		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return '';
		}

		return $email;
	}

	private function tokenPath(string $cacheDir, string $token): string
	{
		return rtrim($cacheDir, '/\\') . '/universe_switch_' . $token . '.json';
	}
}
