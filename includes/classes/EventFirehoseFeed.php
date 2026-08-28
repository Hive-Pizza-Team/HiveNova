<?php

namespace HiveNova\Core;

use ArrayAccess;

class EventFirehoseFeed
{
	public const LIMIT = 50;

	public const JSON_KEYS = ['id', 'time', 'eventType', 'size', 'outcome', 'headline'];

	/**
	 * @param array<string, string>|ArrayAccess $LNG
	 * @return list<array{id: int, time: string, eventType: string, size: string, outcome: string, headline: string}>
	 */
	public static function fetch(int $universe, array|ArrayAccess $LNG, string $timezone, int $sinceId = 0): array
	{
		$limit = self::LIMIT;
		if ($sinceId > 0) {
			$sql = 'SELECT id, time, event_type, size_bucket, outcome, actor_name, target_name
				FROM %%UNIVERSE_EVENTS%%
				WHERE universe = :universe AND id > :sinceId
				ORDER BY id ASC
				LIMIT ' . $limit;
			$params = [
				':universe' => $universe,
				':sinceId'  => $sinceId,
			];
		} else {
			$sql = 'SELECT id, time, event_type, size_bucket, outcome, actor_name, target_name
				FROM %%UNIVERSE_EVENTS%%
				WHERE universe = :universe
				ORDER BY id DESC
				LIMIT ' . $limit;
			$params = [
				':universe' => $universe,
			];
		}

		try {
			$rows = Database::get()->select($sql, $params);
		} catch (\Throwable $e) {
			error_log('EventFirehoseFeed: ' . $e->getMessage());
			return [];
		}
		$out  = [];
		foreach ($rows as $row) {
			$out[] = self::present($row, $LNG, $timezone);
		}

		if ($sinceId > 0) {
			$out = array_reverse($out);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, string>|ArrayAccess $LNG
	 * @return array{id: int, time: string, eventType: string, size: string, outcome: string, headline: string}
	 */
	public static function present(array $row, array|ArrayAccess $LNG, string $timezone): array
	{
		$eventType = (string) ($row['event_type'] ?? EventFirehoseWriter::EVENT_BATTLE);
		$size      = (string) ($row['size_bucket'] ?? EventFirehoseWriter::SIZE_SMALL);
		$outcome   = (string) ($row['outcome'] ?? EventFirehoseWriter::OUTCOME_DRAW);
		$time      = (int) ($row['time'] ?? 0);

		return [
			'id'        => (int) ($row['id'] ?? 0),
			'time'      => _date($LNG['php_tdformat'] ?? 'd. M Y, H:i:s', $time, $timezone),
			'eventType' => (string) ($LNG['ef_event_' . $eventType] ?? $LNG['ef_event_battle'] ?? 'Battle'),
			'size'      => (string) ($LNG['ef_size_' . $eventType . '_' . $size] ?? $LNG['ef_size_' . $size] ?? $size),
			'outcome'   => (string) ($LNG['ef_outcome_' . $outcome] ?? $outcome),
			'headline'  => self::headline($row, $LNG),
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, string>|ArrayAccess $LNG
	 */
	public static function headline(array $row, array|ArrayAccess $LNG): string
	{
		$actor = EventFirehoseWriter::sanitizeName((string) ($row['actor_name'] ?? ''));
		$target = EventFirehoseWriter::sanitizeName((string) ($row['target_name'] ?? ''));
		$eventType = (string) ($row['event_type'] ?? EventFirehoseWriter::EVENT_BATTLE);

		if ($eventType === EventFirehoseWriter::EVENT_MOON && $actor !== '') {
			return sprintf((string) ($LNG['ef_headline_moon'] ?? '%s formed a moon'), $actor);
		}
		if ($eventType === EventFirehoseWriter::EVENT_FEAT && $actor !== '') {
			return sprintf((string) ($LNG['ef_headline_feat'] ?? '%s claimed a feat'), $actor);
		}
		if ($actor !== '' && $target !== '') {
			return sprintf((string) ($LNG['ef_headline_vs'] ?? '%s vs %s'), $actor, $target);
		}
		if ($actor !== '') {
			return $actor;
		}

		return '';
	}
}
