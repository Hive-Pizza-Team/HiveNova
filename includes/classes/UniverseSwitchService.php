<?php

namespace HiveNova\Core;

/**
 * Lists sibling player accounts across universes and issues one-time switch tokens.
 *
 * Accounts are linked when they share a valid Hive account or the same permanent email
 * (email_2). Sessions are cookie-scoped per /uniN/ path, so switching logs out the
 * current universe and redeems a short-lived token under the target universe URL.
 */
class UniverseSwitchService
{
	public const TOKEN_TTL_SECONDS = 90;

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
		if ($email !== '') {
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
		if ($fromHive !== '' && $fromHive === $toHive) {
			return true;
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
			return null;
		}

		return $this->decodeTokenPayload($raw);
	}

	/**
	 * @return array{fromUserId: int, toUserId: int, toUniverse: int}|null
	 */
	public function consumeToken(string $token, string $cacheDir): ?array
	{
		$payload = $this->peekToken($token, $cacheDir);
		if ($payload === null) {
			return null;
		}

		$path = $this->tokenPath($cacheDir, $token);
		if (is_file($path)) {
			@unlink($path);
		}

		return $payload;
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
				return $m[1] . 'uni' . $universe . '.' . $m[2] . ($m[3] ?? '') . '/' . $query;
			}

			return $trimmed . '/' . $query;
		}

		return $trimmed . '/uni' . $universe . '/' . $query;
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
