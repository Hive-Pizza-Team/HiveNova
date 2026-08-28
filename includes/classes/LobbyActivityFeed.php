<?php

namespace HiveNova\Core;

use ArrayAccess;

/**
 * Public lobby activity feed — privacy-safe universe events for guests.
 */
class LobbyActivityFeed
{
	public const LIMIT = 25;

	public const JSON_KEYS = ['id', 'ts', 'time', 'eventType', 'size', 'outcome', 'universe', 'universeId'];

	/**
	 * @param list<int> $universeIds
	 * @param array<string, string>|ArrayAccess $LNG
	 * @param array<int, string> $universeNames uniId => display name
	 * @return list<array{id: int, ts: int, time: string, eventType: string, size: string, outcome: string, universe: string, universeId: int}>
	 */
	public static function fetch(
		array $universeIds,
		array|ArrayAccess $LNG,
		string $timezone,
		array $universeNames = [],
		int $sinceId = 0,
		int $limit = self::LIMIT
	): array {
		$universeIds = array_values(array_unique(array_filter(array_map('intval', $universeIds))));
		if ($universeIds === []) {
			return [];
		}

		$limit = max(1, min(50, $limit));
		$inList = implode(',', $universeIds);

		if ($sinceId > 0) {
			$sql = 'SELECT id, universe, time, event_type, size_bucket, outcome
				FROM %%UNIVERSE_EVENTS%%
				WHERE universe IN ('.$inList.') AND id > :sinceId
				ORDER BY id ASC
				LIMIT '.$limit;
			$params = [':sinceId' => $sinceId];
		} else {
			$sql = 'SELECT id, universe, time, event_type, size_bucket, outcome
				FROM %%UNIVERSE_EVENTS%%
				WHERE universe IN ('.$inList.')
				ORDER BY id DESC
				LIMIT '.$limit;
			$params = [];
		}

		try {
			$rows = Database::get()->select($sql, $params);
		} catch (\Throwable $e) {
			error_log('LobbyActivityFeed: '.$e->getMessage());
			return [];
		}

		$out = [];
		foreach ($rows as $row) {
			$out[] = self::present($row, $LNG, $timezone, $universeNames);
		}

		if ($sinceId > 0) {
			$out = array_reverse($out);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, string>|ArrayAccess $LNG
	 * @param array<int, string> $universeNames
	 * @return array{id: int, ts: int, time: string, eventType: string, size: string, outcome: string, universe: string, universeId: int}
	 */
	public static function present(
		array $row,
		array|ArrayAccess $LNG,
		string $timezone,
		array $universeNames = []
	): array {
		$base = EventFirehoseFeed::present($row, $LNG, $timezone);
		$uniId = (int) ($row['universe'] ?? 0);
		$name = $universeNames[$uniId] ?? ('Uni '.$uniId);

		return [
			'id'         => $base['id'],
			'ts'         => (int) ($row['time'] ?? 0),
			'time'       => $base['time'],
			'eventType'  => $base['eventType'],
			'size'       => $base['size'],
			'outcome'    => $base['outcome'],
			'universe'   => $name,
			'universeId' => $uniId,
		];
	}
}
