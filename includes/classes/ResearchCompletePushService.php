<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Web Push when research jobs finish.
 *
 * Multiple completions in one processing window (one ResourceUpdate tick or
 * one cron run) are merged into a single digest so a queued catch-up does not
 * spam the player. Two delivery paths share per-job dedup:
 * - Cron inspects overdue queues so AFK jobs notify without a visit
 * - ResourceUpdate notifies when the economy tick actually applies the jobs
 */
class ResearchCompletePushService
{
	public const NOTIFY_URL = 'game.php?page=research';
	public const CLEANUP_AFTER = 7 * 86400;
	public const MIN_ELEMENT_ID = 101;
	public const MAX_ELEMENT_ID = 199;

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
	 * @return list<array{elementId: int, level: int, techEnd: int}>
	 */
	public static function dueResearchJobs(mixed $serializedOrQueue, int $now): array
	{
		$queue = is_string($serializedOrQueue)
			? safe_unserialize($serializedOrQueue)
			: $serializedOrQueue;

		if (!is_array($queue)) {
			return [];
		}

		$jobs = [];
		foreach ($queue as $item) {
			if (!is_array($item) || !isset($item[0], $item[1], $item[3])) {
				continue;
			}

			$job = self::normalizeJob([
				'elementId' => $item[0],
				'level'     => $item[1],
				'techEnd'   => $item[3],
			]);
			if ($job === null || $job['techEnd'] > $now) {
				continue;
			}

			$jobs[] = $job;
		}

		return $jobs;
	}

	/**
	 * Cron must not treat later planned queue rows as finished. Only the
	 * currently running job (queue head) has actually elapsed.
	 *
	 * @return list<array{elementId: int, level: int, techEnd: int}>
	 */
	public static function currentDueResearchJobs(mixed $serializedOrQueue, int $now): array
	{
		$queue = is_string($serializedOrQueue)
			? safe_unserialize($serializedOrQueue)
			: $serializedOrQueue;

		if (!is_array($queue) || !isset($queue[0]) || !is_array($queue[0])) {
			return [];
		}

		return self::dueResearchJobs([$queue[0]], $now);
	}

	public static function jobKey(int $userId, int $elementId, int $level, int $techEnd): string
	{
		return $userId . ':' . $elementId . ':' . $level . ':' . $techEnd;
	}

	/**
	 * @param list<array{name: string, level: int}> $namedJobs
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function researchCompleteMessage(array $namedJobs, ?array $lng = null): array
	{
		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$title = $lng['push_research_title'] ?? 'Research complete';
		$count = count($namedJobs);
		$first = $namedJobs[0] ?? ['name' => 'Research', 'level' => 1];
		$name = is_string($first['name'] ?? null) ? $first['name'] : 'Research';
		$level = (int) ($first['level'] ?? 1);

		if ($count <= 1) {
			$template = $lng['push_research_body'] ?? '%s (level %d) finished';
			$body = sprintf($template, $name, $level);
		} else {
			$template = $lng['push_research_body_many'] ?? '%s (level %d) and %d more finished';
			$body = sprintf($template, $name, $level, $count - 1);
		}

		return [
			'title' => $title,
			'body'  => $body,
			'data'  => [
				'url'   => self::NOTIFY_URL,
				'type'  => 'research_complete',
				'count' => max(1, $count),
			],
		];
	}

	/**
	 * @param list<array{elementId?: int, level?: int, techEnd?: int}> $jobs
	 */
	public static function notifyCompletedJobs(int $userId, array $jobs, string $lang = 'en'): int
	{
		return (new self())->notifyJobs($userId, $jobs, $lang);
	}

	public static function notifyCompletedJob(int $userId, int $elementId, int $level, int $techEnd, string $lang = 'en'): int
	{
		return self::notifyCompletedJobs($userId, [[
			'elementId' => $elementId,
			'level'     => $level,
			'techEnd'   => $techEnd,
		]], $lang);
	}

	/**
	 * Send at most one push covering every pending job in this window.
	 *
	 * @param list<array{elementId?: int, level?: int, techEnd?: int}> $jobs
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
				$key = self::jobKey($userId, $normalized['elementId'], $normalized['level'], $normalized['techEnd']);
				if (isset($pending[$key])) {
					continue;
				}
				if ($this->wasNotified($userId, $normalized['elementId'], $normalized['level'], $normalized['techEnd'])) {
					continue;
				}
				$pending[$key] = $normalized;
			}

			if ($pending === []) {
				return 0;
			}
			$pending = array_values($pending);

			foreach ($pending as $job) {
				$this->markNotified($userId, $job['elementId'], $job['level'], $job['techEnd']);
			}
			$message = $this->buildMessage($pending, $lang);
			$this->deliver($userId, $message);

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
			$users = $db->select(
				'SELECT u.id AS user_id, u.b_tech_queue, u.lang
				FROM %%USERS%% u
				WHERE u.b_tech > 0 AND u.b_tech <= :now
				AND (u.settings_push IS NULL OR u.settings_push = 1)
				AND EXISTS (
					SELECT 1 FROM %%PUSH_SUBSCRIPTIONS%% s WHERE s.user_id = u.id
				)',
				[':now' => $now]
			);

			if (!is_array($users) || $users === []) {
				$this->cleanup($now);

				return 0;
			}

			$sent = 0;
			foreach ($users as $row) {
				$jobs = self::currentDueResearchJobs($row['b_tech_queue'] ?? '', $now);
				$sent += $this->notifyJobs(
					(int) ($row['user_id'] ?? 0),
					$jobs,
					(string) ($row['lang'] ?? 'en')
				);
			}

			$this->cleanup($now);

			return $sent;
		} catch (Throwable $e) {
			return 0;
		}
	}

	/**
	 * @param array{elementId?: mixed, level?: mixed, techEnd?: mixed} $job
	 * @return array{elementId: int, level: int, techEnd: int}|null
	 */
	public static function normalizeJob(array $job): ?array
	{
		$elementId = (int) ($job['elementId'] ?? 0);
		$level = (int) ($job['level'] ?? 0);
		$techEnd = (int) ($job['techEnd'] ?? 0);

		if ($elementId < self::MIN_ELEMENT_ID || $elementId > self::MAX_ELEMENT_ID || $level < 1 || $techEnd <= 0) {
			return null;
		}

		return [
			'elementId' => $elementId,
			'level'     => $level,
			'techEnd'   => $techEnd,
		];
	}

	/**
	 * @param list<array{elementId: int, level: int, techEnd: int}> $jobs
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
				: ('Research #' . $elementId);
			$named[] = [
				'name'  => $name,
				'level' => $job['level'],
			];
		}

		return self::researchCompleteMessage($named, $lng);
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

	private function wasNotified(int $userId, int $elementId, int $level, int $techEnd): bool
	{
		$row = Database::get()->selectSingle(
			'SELECT user_id FROM %%PUSH_RESEARCH_NOTIFIED%%
			WHERE user_id = :userId AND element_id = :elementId AND level = :level AND tech_end = :techEnd',
			[
				':userId'    => $userId,
				':elementId' => $elementId,
				':level'     => $level,
				':techEnd'   => $techEnd,
			],
			'user_id'
		);

		return $row !== false && $row !== null && $row !== '';
	}

	private function markNotified(int $userId, int $elementId, int $level, int $techEnd): void
	{
		Database::get()->insert(
			'INSERT IGNORE INTO %%PUSH_RESEARCH_NOTIFIED%% (user_id, element_id, level, tech_end, notified_at)
			VALUES (:userId, :elementId, :level, :techEnd, :notifiedAt)',
			[
				':userId'      => $userId,
				':elementId'   => $elementId,
				':level'       => $level,
				':techEnd'     => $techEnd,
				':notifiedAt'  => defined('TIMESTAMP') ? TIMESTAMP : time(),
			]
		);
	}

	private function cleanup(int $now): void
	{
		Database::get()->delete(
			'DELETE FROM %%PUSH_RESEARCH_NOTIFIED%% WHERE notified_at > 0 AND notified_at < :old',
			[':old' => $now - self::CLEANUP_AFTER]
		);
	}
}
