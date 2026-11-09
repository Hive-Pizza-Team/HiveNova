<?php

namespace HiveNova\Core;

/**
 * Inbox category resolution and overview counters for the messages page.
 */
class MessageInboxService
{
	public const CATEGORY_ALL = 100;
	public const CATEGORY_OUTBOX = 999;

	/** @var list<int> */
	public const CATEGORIES = [0, 1, 2, 3, 4, 5, 15, 50, 99, self::CATEGORY_ALL, self::CATEGORY_OUTBOX];

	/**
	 * Inbox types that can hold unread mail (excludes All / Outbox).
	 *
	 * @var list<int>
	 */
	public const INBOX_TYPES = [0, 1, 2, 3, 4, 5, 15, 50, 99];

	public static function isValidCategory(int $category): bool
	{
		return in_array($category, self::CATEGORIES, true);
	}

	/**
	 * Default / unknown category → first unread inbox type, otherwise All.
	 *
	 * @param array<int|string, mixed> $unreadByCategory
	 */
	public static function resolveCategory(int $requested, array $unreadByCategory = []): int
	{
		if (self::isValidCategory($requested)) {
			return $requested;
		}

		foreach (self::INBOX_TYPES as $type) {
			if ((int) ($unreadByCategory[$type] ?? 0) > 0) {
				return $type;
			}
		}

		return self::CATEGORY_ALL;
	}

	/**
	 * Build unread/total maps for overview tiles.
	 *
	 * Sums only real inbox types into All (never double-counts All/Outbox slots).
	 * Casts SQL aggregates to int so unlabeled "0/1" cannot come from string/NULL drift.
	 *
	 * @param list<array<string, mixed>> $categoryRows Rows with message_type, message_unread, count
	 * @return array{unread: array<int, int>, total: array<int, int>}
	 */
	public static function buildCategoryStats(array $categoryRows, int $outboxTotal): array
	{
		$unread = [];
		$total = [];
		foreach (self::CATEGORIES as $type) {
			$unread[$type] = 0;
			$total[$type] = 0;
		}

		foreach ($categoryRows as $row) {
			$type = (int) ($row['message_type'] ?? -1);
			if (!in_array($type, self::INBOX_TYPES, true)) {
				continue;
			}
			$unread[$type] = (int) ($row['message_unread'] ?? 0);
			$total[$type] = (int) ($row['count'] ?? 0);
		}

		foreach (self::INBOX_TYPES as $type) {
			$unread[self::CATEGORY_ALL] += $unread[$type];
			$total[self::CATEGORY_ALL] += $total[$type];
		}

		$total[self::CATEGORY_OUTBOX] = max(0, $outboxTotal);

		return [
			'unread' => $unread,
			'total' => $total,
		];
	}
}
