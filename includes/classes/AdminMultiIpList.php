<?php

namespace HiveNova\Core;

/**
 * Builds Multi-IP admin table groups and resolves owner display names.
 */
final class AdminMultiIpList
{
	/**
	 * Resolve the owner label for a Multi-IP row.
	 * Empty/missing usernames get a clear fallback (never a cryptic "(?)").
	 */
	public static function ownerLabel(?string $username, int $userId, string $missingFormat): string
	{
		$trimmed = trim((string) $username);
		if ($trimmed !== '') {
			return $trimmed;
		}

		return sprintf($missingFormat, $userId);
	}

	/**
	 * Group raw user rows by last IP for the Multi-IP admin table.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @param callable(int): string $formatTimestamp
	 * @return array<string, array<int, array{id: int, username: string, email: string, register_time: string, onlinetime: string, user_lastip: string, isKnown: int}>>
	 */
	public static function groupByIp(array $rows, callable $formatTimestamp, string $missingFormat): array
	{
		$groups = [];

		foreach ($rows as $row) {
			$ip = (string) ($row['user_lastip'] ?? '');
			$id = (int) ($row['id'] ?? 0);

			if (!isset($groups[$ip])) {
				$groups[$ip] = [];
			}

			$groups[$ip][$id] = [
				'id' => $id,
				'username' => self::ownerLabel(
					isset($row['username']) ? (string) $row['username'] : null,
					$id,
					$missingFormat
				),
				'email' => (string) ($row['email'] ?? ''),
				'register_time' => $formatTimestamp((int) ($row['register_time'] ?? 0)),
				'onlinetime' => $formatTimestamp((int) ($row['onlinetime'] ?? 0)),
				'user_lastip' => $ip,
				'isKnown' => (int) ($row['isKnown'] ?? 0),
			];
		}

		return $groups;
	}
}
