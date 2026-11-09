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

	/**
	 * Category tile colors keyed by message_type / synthetic All / Outbox.
	 *
	 * @var array<int, string>
	 */
	public const TITLE_COLORS = [
		0 => '#FFFF00',
		1 => '#FF6699',
		2 => '#FF3300',
		3 => '#FF9900',
		4 => '#773399',
		5 => '#009933',
		15 => '#6495ed',
		50 => '#666600',
		99 => '#007070',
		self::CATEGORY_ALL => '#ABABAB',
		self::CATEGORY_OUTBOX => '#CCCCCC',
	];

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

	/**
	 * Build unread/total maps and Smarty category tiles from GROUP BY rows.
	 *
	 * @param list<array{message_type?: int|string, message_unread?: int|string|null, count?: int|string|null}> $categoryRows
	 * @return array{
	 *   unread: array<int, int>,
	 *   total: array<int, int>,
	 *   categories: array<int, array{color: string, unread: int, total: int}>
	 * }
	 */
	public static function buildCategoryStats(array $categoryRows, int $outboxTotal = 0): array
	{
		$unread = [];
		$total = [];
		foreach (array_keys(self::TITLE_COLORS) as $categoryId) {
			$unread[$categoryId] = 0;
			$total[$categoryId] = 0;
		}

		foreach ($categoryRows as $row) {
			$type = (int) ($row['message_type'] ?? -1);
			if (!isset($total[$type]) || $type === self::CATEGORY_ALL || $type === self::CATEGORY_OUTBOX) {
				continue;
			}
			$unread[$type] = max(0, (int) ($row['message_unread'] ?? 0));
			$total[$type] = max(0, (int) ($row['count'] ?? 0));
		}

		$unread[self::CATEGORY_ALL] = 0;
		$total[self::CATEGORY_ALL] = 0;
		foreach (self::INBOX_TYPES as $type) {
			$unread[self::CATEGORY_ALL] += $unread[$type];
			$total[self::CATEGORY_ALL] += $total[$type];
		}

		$total[self::CATEGORY_OUTBOX] = max(0, $outboxTotal);
		$unread[self::CATEGORY_OUTBOX] = 0;

		$categories = [];
		foreach (self::TITLE_COLORS as $categoryId => $color) {
			$categories[$categoryId] = [
				'color' => $color,
				'unread' => $unread[$categoryId],
				'total' => $total[$categoryId],
			];
		}

		return [
			'unread' => $unread,
			'total' => $total,
			'categories' => $categories,
		];
	}

	/**
	 * Resolve a commander name to a user id within one universe.
	 */
	public static function findUserIdByUsername(string $username, int $universe): ?int
	{
		$username = trim($username);
		if ($username === '' || $universe < 1) {
			return null;
		}

		$row = Database::get()->selectSingle(
			'SELECT id FROM %%USERS%% WHERE universe = :universe AND username = :username;',
			[
				':universe' => $universe,
				':username' => $username,
			]
		);

		if (!is_array($row) || !isset($row['id'])) {
			return null;
		}

		$id = (int) $row['id'];
		return $id > 0 ? $id : null;
	}
}
