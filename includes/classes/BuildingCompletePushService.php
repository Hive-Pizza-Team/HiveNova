<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Web Push when a building construction job finishes.
 *
 * Two delivery paths share one-notify-per-job dedup:
 * - Cron inspects overdue queues so AFK / other-planet jobs notify without a visit
 * - ResourceUpdate notifies when the economy tick actually applies the job
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
		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$title = $lng['push_building_title'] ?? 'Building complete';
		$template = $lng['push_building_body'] ?? '%s (level %d) finished on %s';

		return [
			'title' => $title,
			'body'  => sprintf($template, $buildingName, $level, $planetName),
			'data'  => [
				'url'  => self::NOTIFY_URL,
				'type' => 'building_complete',
			],
		];
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
		return (new self())->notifyJob($userId, $planetId, $planetName, $elementId, $level, $buildEnd, $lang);
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
		if ($userId <= 0 || $planetId <= 0 || $elementId < 1 || $elementId > 99 || $level < 1 || $buildEnd <= 0) {
			return false;
		}

		if (!$this->isConfigured()) {
			return false;
		}

		try {
			if ($this->wasNotified($planetId, $elementId, $level, $buildEnd)) {
				return false;
			}

			$message = $this->buildMessage($elementId, $level, $planetName, $lang);
			$this->deliver($userId, $message);
			$this->markNotified($planetId, $elementId, $level, $buildEnd);

			return true;
		} catch (Throwable $e) {
			return false;
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

			$sent = 0;
			foreach ($planets as $row) {
				$jobs = self::dueConstructionJobs($row['b_building_id'] ?? '', $now);
				foreach ($jobs as $job) {
					if ($this->notifyJob(
						(int) ($row['user_id'] ?? 0),
						(int) ($row['planet_id'] ?? 0),
						(string) ($row['planet_name'] ?? ''),
						$job['elementId'],
						$job['level'],
						$job['buildEnd'],
						(string) ($row['lang'] ?? 'en')
					)) {
						$sent++;
					}
				}
			}

			$this->cleanup($now);

			return $sent;
		} catch (Throwable $e) {
			return 0;
		}
	}

	/**
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	private function buildMessage(int $elementId, int $level, string $planetName, string $lang): array
	{
		$lng = $this->loadLanguage($lang);
		$tech = $lng['tech'] ?? [];
		$buildingName = is_array($tech) && isset($tech[$elementId]) && is_string($tech[$elementId])
			? $tech[$elementId]
			: ('Building #' . $elementId);

		return self::buildingCompleteMessage($buildingName, $level, $planetName, $lng);
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
