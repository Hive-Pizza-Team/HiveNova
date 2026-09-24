<?php

namespace HiveNova\Core;

use HiveNova\Repository\MessageRepository;

class MessagePlayService
{
	/**
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>
	 */
	public static function inbox(array $user, int $category, int $page): array
	{
		$category = $category > 0 ? $category : 100;
		$page = max($page, 1);
		$limit = defined('MESSAGES_PER_PAGE') ? MESSAGES_PER_PAGE : 10;
		$universe = (int) ($user['universe'] ?? 0);
		$userId = (int) $user['id'];
		$count = MessageRepository::countMessages($userId, $category, false, '', $universe);
		$maxPage = max((int) ceil($count / $limit), 1);
		$page = min($page, $maxPage);
		$offset = ($page - 1) * $limit;
		$rows = MessageRepository::getMessagesPaged($userId, $category, $offset, $limit, '', $universe);
		$messages = [];
		foreach ($rows as $row) {
			$messages[] = self::mapRow($row);
		}

		return [
			'category' => $category,
			'page' => $page,
			'maxPage' => $maxPage,
			'count' => $count,
			'messages' => $messages,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function mapRow(array $row): array
	{
		$text = (string) ($row['message_text'] ?? '');
		$text = preg_replace('/\s+target="_blank"/i', '', $text) ?? $text;

		return [
			'id' => (int) ($row['message_id'] ?? 0),
			'time' => (int) ($row['message_time'] ?? 0),
			'from' => (string) ($row['message_from'] ?? ''),
			'subject' => (string) ($row['message_subject'] ?? ''),
			'sender' => (int) ($row['message_sender'] ?? 0),
			'type' => (int) ($row['message_type'] ?? 0),
			'unread' => (int) ($row['message_unread'] ?? 0) === 1,
			'text' => $text,
		];
	}

	/**
	 * @param mixed $lng
	 * @return array<int, string>
	 */
	public static function categoryLabels(mixed $lng): array
	{
		$raw = (is_array($lng) || $lng instanceof \ArrayAccess) ? ($lng['mg_type'] ?? []) : [];
		$out = [];
		if (is_array($raw)) {
			foreach ($raw as $id => $label) {
				$out[(int) $id] = (string) $label;
			}
		}

		return $out;
	}
}
