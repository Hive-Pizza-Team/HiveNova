<?php

namespace HiveNova\Core;

/**
 * Inbox category resolution for the messages page.
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
	 * Default / unknown category → All messages so the list is not blank.
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
}
