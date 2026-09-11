<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Web Push when a building construction job finishes.
 *
 * Multiple completions in one processing window (one ResourceUpdate tick or
 * one cron run) are merged into a single digest per user so a queued catch-up
 * does not spam the player. Incoming hostile fleets stay one-per-event.
 * Two delivery paths share per-job dedup:
 * - Cron inspects overdue queues so AFK / other-planet jobs notify without a visit
 * - ResourceUpdate notifies when the economy tick actually applies the jobs
 */
class BuildingCompletePushService
{
	public const NOTIFY_URL = 'game.php?page=buildings';
	public const CLEANUP_AFTER = 7 * 86400;

	/**
	 * @param callable(int, string, string, array<string, mixed>): void|null $notifier
	 * @param callable(string): array<string, mixed>|null $languageLoader
	 */
	public function __construct(
		private readonly mixed $notifier = null,
		private readonly ?bool $configured = null,
		private readonly mixed $languageLoader = null,
	) {
	}

	public function isConfigured(): bool
	{
		return $this->configured ?? PushNotificationService::isConfigured();
	}

	/**
	 * @return list<array{elementId: int, level: int, buildEnd: int}>
	 */
	public static function dueConstructionJobs(mixed $serializedOrQueue, int $now): array
	{
		$queue = is_string($serializedOrQueue)
			? safe_unserialize($serializedOrQueue)
			: $serializedOrQueue;

		if (!is_array($queue)) {
			return [];
		}

		$jobs = [];
		foreach ($queue as $item) {
			if (!is_array($item) || !isset($item[0], $item[1], $item[3], $item[4])) {
				continue;
			}

			$elementId = (int) $item[0];
			$level = (int) $item[1];
			$buildEnd = (int) $item[3];
			$mode = (string) $item[4];

			if ($mode !== 'build' || $elementId < 1 || $elementId > 99 || $level < 1 || $buildEnd <= 0 || $buildEnd > $now) {
				continue;
			}

			$jobs[] = [
				'elementId' => $elementId,
				'level'     => $level,
				'buildEnd'  => $buildEnd,
			];
		}

		return $jobs;
	}

	public static function jobKey(int $planetId, int $elementId, int $level, int $buildEnd): string
	{
		return $planetId . ':' . $elementId . ':' . $level . ':' . $buildEnd;
	}

	/**
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function buildingCompleteMessage(string $buildingName, int $level, string $planetName, ?array $lng = null): array
	{
		return self::buildingCompleteDigest([
			['name' => $buildingName, 'level' => $level, 'planetName' => $planetName],
		], $lng);
	}

	/**
	 * @param list<array{name: string, level: int, planetName: string}> $namedJobs
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function buildingCompleteDigest(array $namedJobs, ?array $lng = null): array
	{
		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$title = $lng['push_building_title'] ?? 'Building complete';
		$count = count($namedJobs);
		$first = $namedJobs[0] ?? ['name' => 'Building', 'level' => 1, 'planetName' => ''];
		$name = is_string($first['name'] ?? null) ? $first['name'] : 'Building';
		$level = (int) ($first['level'] ?? 1);
		$planetName = is_string($first['planetName'] ?? null) ? $first['planetName'] : '';

		if ($count <= 1) {
			$template = $lng['push_building_body'] ?? '%s (level %d) finished on %s';
			$body = sprintf($template, $name, $level, $planetName);
		} else {
			$template = $lng['push_building_body_many'] ?? '%s (level %d) on %s and %d more finished';
			$body = sprintf($template, $name, $level, $planetName, $count - 1);
		}

		return [
			'title' => $title,
			'body'  => $body,
			'data'  => [
				'url'   => self::NOTIFY_URL,
				'type'  => 'building_complete',
				'count' => max(1, $count),
			],
		];
	}

	/**
	 * @param list<array{planetId?: int, planetName?: string, elementId?: int, level?: int, buildEnd?: int}> $jobs
	 */
	public static function notifyCompletedJobs(int $userId, array $jobs, string $lang = 'en'): int
	{
		return (new self())->notifyJobs($userId, $jobs, $lang);
	}

	public static function notifyCompletedJob(
		int $userId,
		int $planetId,
		string $planetName,
		int $elementId,
		int $level,
		int $buildEnd,
		string $lang = 'en'
	): bool {
		return self::notifyCompletedJobs($userId, [[
			'planetId'   => $planetId,
			'planetName' => $planetName,
			'elementId'  => $elementId,
			'level'      => $level,
			'buildEnd'   => $buildEnd,
		]], $lang) > 0;
	}

	public function notifyJob(
		int $userId,
		int $planetId,
		string $planetName,
		int $elementId,
		int $level,
		int $buildEnd,
		string $lang = 'en'
	): bool {
		return $this->notifyJobs($userId, [[
			'planetId'   => $planetId,
			'planetName' => $planetName,
			'elementId'  => $elementId,
			'level'      => $level,
			'buildEnd'   => $buildEnd,
		]], $lang) > 0;
	}

	/**
	 * Send at most one push covering every pending job in this window.
	 *
	 * @param list<array{planetId?: int, planetName?: string, elementId?: int, level?: int, buildEnd?: int}> $jobs
	 */
	public function notifyJobs(int $userId, array $jobs, string $lang = 'en'): int
	{
		if ($userId <= 0 || !$this->isConfigured()) {
			return 0;
		}

		try {
			$pending = [];
			foreach ($jobs as $job) {
				$normalized = self::normalizeJob($job);
				if ($normalized === null) {
					continue;
				}
				$key = self::jobKey(
					$normalized['planetId'],
					$normalized['elementId'],
					$normalized['level'],
					$normalized['buildEnd']
				);
				if (isset($pending[$key])) {
					continue;
				}
				if ($this->wasNotified($normalized['planetId'], $normalized['elementId'], $normalized['level'], $normalized['buildEnd'])) {
					continue;
				}
				$pending[$key] = $normalized;
			}

			if ($pending === []) {
				return 0;
			}
			$pending = array_values($pending);

			$message = $this->buildMessage($pending, $lang);
			$this->deliver($userId, $message);
			foreach ($pending as $job) {
				$this->markNotified($job['planetId'], $job['elementId'], $job['level'], $job['buildEnd']);
			}

			return 1;
		} catch (Throwable $e) {
			return 0;
		}
	}

	public function run(?int $now = null): int
	{
		if (!$this->isConfigured()) {
			return 0;
		}

		$now = $now ?? (defined('TIMESTAMP') ? TIMESTAMP : time());

		try {
			$db = Database::get();
			$planets = $db->select(
				'SELECT p.id AS planet_id, p.name AS planet_name, p.b_building_id, p.id_owner AS user_id, u.lang
				FROM %%PLANETS%% p
				INNER JOIN %%USERS%% u ON u.id = p.id_owner
				WHERE p.b_building > 0 AND p.b_building <= :now
				AND (u.settings_push IS NULL OR u.settings_push = 1)
				AND EXISTS (
					SELECT 1 FROM %%PUSH_SUBSCRIPTIONS%% s WHERE s.user_id = p.id_owner
				)',
				[':now' => $now]
			);

			if (!is_array($planets) || $planets === []) {
				$this->cleanup($now);

				return 0;
			}

			$byUser = [];
			foreach ($planets as $row) {
				$userId = (int) ($row['user_id'] ?? 0);
				$planetId = (int) ($row['planet_id'] ?? 0);
				$planetName = (string) ($row['planet_name'] ?? '');
				$lang = (string) ($row['lang'] ?? 'en');
				foreach (self::dueConstructionJobs($row['b_building_id'] ?? '', $now) as $job) {
					$byUser[$userId]['lang'] = $lang;
					$byUser[$userId]['jobs'][] = [
						'planetId'   => $planetId,
						'planetName' => $planetName,
						'elementId'  => $job['elementId'],
						'level'      => $job['level'],
						'buildEnd'   => $job['buildEnd'],
					];
				}
			}

			$sent = 0;
			foreach ($byUser as $userId => $payload) {
				$sent += $this->notifyJobs(
					(int) $userId,
					$payload['jobs'] ?? [],
					(string) ($payload['lang'] ?? 'en')
				);
			}

			$this->cleanup($now);

			return $sent;
		} catch (Throwable $e) {
			return 0;
		}
	}

	/**
	 * @param array{planetId?: mixed, planetName?: mixed, elementId?: mixed, level?: mixed, buildEnd?: mixed} $job
	 * @return array{planetId: int, planetName: string, elementId: int, level: int, buildEnd: int}|null
	 */
	public static function normalizeJob(array $job): ?array
	{
		$planetId = (int) ($job['planetId'] ?? 0);
		$elementId = (int) ($job['elementId'] ?? 0);
		$level = (int) ($job['level'] ?? 0);
		$buildEnd = (int) ($job['buildEnd'] ?? 0);
		$planetName = is_string($job['planetName'] ?? null) ? $job['planetName'] : '';

		if ($planetId <= 0 || $elementId < 1 || $elementId > 99 || $level < 1 || $buildEnd <= 0) {
			return null;
		}

		return [
			'planetId'   => $planetId,
			'planetName' => $planetName,
			'elementId'  => $elementId,
			'level'      => $level,
			'buildEnd'   => $buildEnd,
		];
	}

	/**
	 * @param list<array{planetId: int, planetName: string, elementId: int, level: int, buildEnd: int}> $jobs
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	private function buildMessage(array $jobs, string $lang): array
	{
		$lng = $this->loadLanguage($lang);
		$tech = $lng['tech'] ?? [];
		$named = [];
		foreach ($jobs as $job) {
			$elementId = $job['elementId'];
			$name = is_array($tech) && isset($tech[$elementId]) && is_string($tech[$elementId])
				? $tech[$elementId]
				: ('Building #' . $elementId);
			$named[] = [
				'name'       => $name,
				'level'      => $job['level'],
				'planetName' => $job['planetName'],
			];
		}

		return self::buildingCompleteDigest($named, $lng);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function loadLanguage(string $lang): array
	{
		if (is_callable($this->languageLoader)) {
			$loaded = ($this->languageLoader)($lang);

			return is_array($loaded) ? $loaded : [];
		}

		$lang = preg_replace('/[^a-zA-Z0-9_-]/', '', $lang) ?: 'en';
		$files = ['INGAME.php', 'TECH.php'];
		$merged = [];

		foreach (['en', $lang] as $code) {
			foreach ($files as $file) {
				$path = ROOT_PATH . 'language/' . $code . '/' . $file;
				if (!is_file($path)) {
					continue;
				}
				$LNG = [];
				require $path;
				if (is_array($LNG)) {
					$merged = array_replace($merged, $LNG);
				}
			}
		}

		return $merged;
	}

	/**
	 * @param array{title: string, body: string, data: array<string, mixed>} $message
	 */
	private function deliver(int $userId, array $message): void
	{
		if (is_callable($this->notifier)) {
			($this->notifier)($userId, $message['title'], $message['body'], $message['data']);

			return;
		}

		PushNotificationService::notifyUser($userId, $message['title'], $message['body'], $message['data']);
	}

	private function wasNotified(int $planetId, int $elementId, int $level, int $buildEnd): bool
	{
		$row = Database::get()->selectSingle(
			'SELECT planet_id FROM %%PUSH_BUILDING_NOTIFIED%%
			WHERE planet_id = :planetId AND element_id = :elementId AND level = :level AND build_end = :buildEnd',
			[
				':planetId'  => $planetId,
				':elementId' => $elementId,
				':level'     => $level,
				':buildEnd'  => $buildEnd,
			],
			'planet_id'
		);

		return $row !== false && $row !== null && $row !== '';
	}

	private function markNotified(int $planetId, int $elementId, int $level, int $buildEnd): void
	{
		Database::get()->insert(
			'INSERT IGNORE INTO %%PUSH_BUILDING_NOTIFIED%% (planet_id, element_id, level, build_end)
			VALUES (:planetId, :elementId, :level, :buildEnd)',
			[
				':planetId'  => $planetId,
				':elementId' => $elementId,
				':level'     => $level,
				':buildEnd'  => $buildEnd,
			]
		);
	}

	private function cleanup(int $now): void
	{
		Database::get()->delete(
			'DELETE FROM %%PUSH_BUILDING_NOTIFIED%% WHERE build_end < :old',
			[':old' => $now - self::CLEANUP_AFTER]
		);
	}
}
