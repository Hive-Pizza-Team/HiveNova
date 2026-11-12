<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Web Push when a player's fleet lands back at its origin planet.
 *
 * Multiple returns in one processing window (one fleet-handler pass or one
 * cron run) are merged into a single digest per user so a catch-up does not
 * spam the player. Two delivery paths share per-fleet dedup:
 * - Cron inspects due returning fleets so AFK players notify without a visit
 * - MissionFunctions::RestoreFleet notifies when the return is applied
 */
class FleetReturnPushService
{
	public const NOTIFY_URL = 'game.php?page=fleetTable';
	public const CLEANUP_AFTER = 7 * 86400;

	/**
	 * @param callable(int, string, string, array<string, mixed>): (bool|void)|null $notifier
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
	 * @param array{fleetId?: mixed, mission?: mixed, galaxy?: mixed, system?: mixed, planet?: mixed, planetName?: mixed, returnEnd?: mixed} $fleet
	 * @return array{fleetId: int, mission: int, galaxy: int, system: int, planet: int, planetName: string, returnEnd: int}|null
	 */
	public static function normalizeFleet(array $fleet): ?array
	{
		$fleetId = (int) ($fleet['fleetId'] ?? 0);
		$mission = (int) ($fleet['mission'] ?? 0);
		$galaxy = (int) ($fleet['galaxy'] ?? 0);
		$system = (int) ($fleet['system'] ?? 0);
		$planet = (int) ($fleet['planet'] ?? 0);
		$returnEnd = (int) ($fleet['returnEnd'] ?? 0);
		$planetName = is_string($fleet['planetName'] ?? null) ? $fleet['planetName'] : '';

		if ($fleetId <= 0 || $mission <= 0 || $returnEnd <= 0) {
			return null;
		}

		return [
			'fleetId'    => $fleetId,
			'mission'    => $mission,
			'galaxy'     => $galaxy,
			'system'     => $system,
			'planet'     => $planet,
			'planetName' => $planetName,
			'returnEnd'  => $returnEnd,
		];
	}

	public static function fleetKey(int $fleetId): string
	{
		return (string) $fleetId;
	}

	/**
	 * @param list<array{missionName: string, planetName: string, galaxy: int, system: int, planet: int}> $namedFleets
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	public static function fleetReturnDigest(array $namedFleets, ?array $lng = null): array
	{
		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$title = $lng['push_fleet_return_title'] ?? 'Fleet returned';
		$count = count($namedFleets);
		$first = $namedFleets[0] ?? [
			'missionName' => 'Fleet',
			'planetName'  => '',
			'galaxy'      => 0,
			'system'      => 0,
			'planet'      => 0,
		];
		$missionName = is_string($first['missionName'] ?? null) ? $first['missionName'] : 'Fleet';
		$planetName = is_string($first['planetName'] ?? null) ? $first['planetName'] : '';
		$galaxy = (int) ($first['galaxy'] ?? 0);
		$system = (int) ($first['system'] ?? 0);
		$planet = (int) ($first['planet'] ?? 0);

		if ($count <= 1) {
			$template = $lng['push_fleet_return_body'] ?? '%s returned to %s [%d:%d:%d]';
			$body = sprintf($template, $missionName, $planetName, $galaxy, $system, $planet);
		} else {
			$template = $lng['push_fleet_return_body_many'] ?? '%s returned to %s and %d more fleets are home';
			$body = sprintf($template, $missionName, $planetName, $count - 1);
		}

		return [
			'title' => $title,
			'body'  => $body,
			'data'  => [
				'url'   => self::NOTIFY_URL,
				'type'  => 'fleet_return',
				'count' => max(1, $count),
			],
		];
	}

	/**
	 * @param list<array{fleetId?: int, mission?: int, galaxy?: int, system?: int, planet?: int, planetName?: string, returnEnd?: int}> $fleets
	 */
	public static function notifyReturnedFleets(int $userId, array $fleets, string $lang = 'en'): int
	{
		return (new self())->notifyFleets($userId, $fleets, $lang);
	}

	public static function notifyReturnedFleet(
		int $userId,
		int $fleetId,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		string $planetName,
		int $returnEnd,
		string $lang = 'en'
	): bool {
		return self::notifyReturnedFleets($userId, [[
			'fleetId'    => $fleetId,
			'mission'    => $mission,
			'galaxy'     => $galaxy,
			'system'     => $system,
			'planet'     => $planet,
			'planetName' => $planetName,
			'returnEnd'  => $returnEnd,
		]], $lang) > 0;
	}

	/**
	 * Send at most one push covering every pending return in this window.
	 *
	 * @param list<array{fleetId?: int, mission?: int, galaxy?: int, system?: int, planet?: int, planetName?: string, returnEnd?: int}> $fleets
	 */
	public function notifyFleets(int $userId, array $fleets, string $lang = 'en'): int
	{
		if ($userId <= 0 || !$this->isConfigured()) {
			return 0;
		}

		try {
			$pending = [];
			foreach ($fleets as $fleet) {
				$normalized = self::normalizeFleet($fleet);
				if ($normalized === null) {
					continue;
				}
				$key = self::fleetKey($normalized['fleetId']);
				if (isset($pending[$key])) {
					continue;
				}
				if ($this->wasNotified($normalized['fleetId'])) {
					continue;
				}
				$pending[$key] = $normalized;
			}

			if ($pending === []) {
				return 0;
			}

			$claimed = [];
			foreach ($pending as $fleet) {
				if ($this->claimPending($userId, $fleet)) {
					$claimed[] = $fleet;
				}
			}
			if ($claimed === []) {
				return 0;
			}

			$message = $this->buildMessage($claimed, $lang);
			if (!$this->deliver($userId, $message)) {
				foreach ($claimed as $fleet) {
					$this->releaseClaim($fleet['fleetId']);
				}
				PushNotificationService::logFailure('FleetReturnPushService: delivery failed user=' . $userId);

				return 0;
			}

			return 1;
		} catch (Throwable $e) {
			PushNotificationService::logFailure('FleetReturnPushService::notifyFleets: ' . $e->getMessage());

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
			$fleets = $db->select(
				'SELECT f.fleet_id, f.fleet_owner AS user_id, f.fleet_mission, f.fleet_end_time AS return_end,
					f.fleet_start_galaxy AS galaxy, f.fleet_start_system AS system, f.fleet_start_planet AS planet,
					p.name AS planet_name, u.lang
				FROM %%FLEETS%% f
				INNER JOIN %%USERS%% u ON u.id = f.fleet_owner
				LEFT JOIN %%PLANETS%% p ON p.id = f.fleet_start_id
				WHERE f.fleet_mess = :returnState
				AND f.fleet_end_time > 0 AND f.fleet_end_time <= :now
				AND f.fleet_owner > 0
				AND (u.settings_push IS NULL OR u.settings_push = 1)
				AND EXISTS (
					SELECT 1 FROM %%PUSH_SUBSCRIPTIONS%% s WHERE s.user_id = f.fleet_owner
				)',
				[
					':returnState' => defined('FLEET_RETURN') ? FLEET_RETURN : 1,
					':now'         => $now,
				]
			);

			$byUser = [];
			if (is_array($fleets)) {
				foreach ($fleets as $row) {
					$userId = (int) ($row['user_id'] ?? 0);
					$byUser[$userId]['lang'] = (string) ($row['lang'] ?? 'en');
					$byUser[$userId]['fleets'][] = [
						'fleetId'    => (int) ($row['fleet_id'] ?? 0),
						'mission'    => (int) ($row['fleet_mission'] ?? 0),
						'galaxy'     => (int) ($row['galaxy'] ?? 0),
						'system'     => (int) ($row['system'] ?? 0),
						'planet'     => (int) ($row['planet'] ?? 0),
						'planetName' => (string) ($row['planet_name'] ?? ''),
						'returnEnd'  => (int) ($row['return_end'] ?? 0),
					];
				}
			}

			foreach ($this->pendingNotifiedFleets() as $fleet) {
				$userId = (int) ($fleet['userId'] ?? 0);
				if ($userId <= 0) {
					continue;
				}
				$byUser[$userId]['lang'] = (string) ($fleet['lang'] ?? $byUser[$userId]['lang'] ?? 'en');
				$byUser[$userId]['fleets'][] = $fleet;
			}

			if ($byUser === []) {
				$this->cleanup($now);

				return 0;
			}

			$sent = 0;
			foreach ($byUser as $userId => $payload) {
				$sent += $this->notifyFleets(
					(int) $userId,
					$payload['fleets'] ?? [],
					(string) ($payload['lang'] ?? 'en')
				);
			}

			$this->cleanup($now);

			return $sent;
		} catch (Throwable $e) {
			PushNotificationService::logFailure('FleetReturnPushService::run: ' . $e->getMessage());

			return 0;
		}
	}

	/**
	 * @param list<array{fleetId: int, mission: int, galaxy: int, system: int, planet: int, planetName: string, returnEnd: int}> $fleets
	 * @return array{title: string, body: string, data: array<string, mixed>}
	 */
	private function buildMessage(array $fleets, string $lang): array
	{
		$lng = $this->loadLanguage($lang);
		$named = [];
		foreach ($fleets as $fleet) {
			$mission = $fleet['mission'];
			$missionName = isset($lng['type_mission_' . $mission]) && is_string($lng['type_mission_' . $mission])
				? $lng['type_mission_' . $mission]
				: ('Mission ' . $mission);
			$named[] = [
				'missionName' => $missionName,
				'planetName'  => $fleet['planetName'],
				'galaxy'      => $fleet['galaxy'],
				'system'      => $fleet['system'],
				'planet'      => $fleet['planet'],
			];
		}

		return self::fleetReturnDigest($named, $lng);
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
		$files = ['INGAME.php', 'FLEET.php'];
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
	private function deliver(int $userId, array $message): bool
	{
		if (is_callable($this->notifier)) {
			return ($this->notifier)($userId, $message['title'], $message['body'], $message['data']) !== false;
		}

		$result = PushNotificationService::notifyUser($userId, $message['title'], $message['body'], $message['data']);

		return !empty($result['ok']);
	}

	/**
	 * @return list<array{fleetId: int, mission: int, galaxy: int, system: int, planet: int, planetName: string, returnEnd: int, userId: int, lang: string}>
	 */
	private function pendingNotifiedFleets(): array
	{
		$rows = Database::get()->select(
			'SELECT n.fleet_id, n.user_id, n.mission, n.galaxy, n.system, n.planet, n.planet_name, n.return_end, u.lang
			FROM %%PUSH_FLEET_RETURN_NOTIFIED%% n
			INNER JOIN %%USERS%% u ON u.id = n.user_id
			WHERE n.notified_at = 0
			AND (u.settings_push IS NULL OR u.settings_push = 1)
			AND EXISTS (
				SELECT 1 FROM %%PUSH_SUBSCRIPTIONS%% s WHERE s.user_id = n.user_id
			)'
		);

		if (!is_array($rows)) {
			return [];
		}

		$fleets = [];
		foreach ($rows as $row) {
			$normalized = self::normalizeFleet([
				'fleetId'    => $row['fleet_id'] ?? 0,
				'mission'    => $row['mission'] ?? 0,
				'galaxy'     => $row['galaxy'] ?? 0,
				'system'     => $row['system'] ?? 0,
				'planet'     => $row['planet'] ?? 0,
				'planetName' => $row['planet_name'] ?? '',
				'returnEnd'  => $row['return_end'] ?? 0,
			]);
			if ($normalized === null) {
				continue;
			}
			$fleets[] = $normalized + [
				'userId' => (int) ($row['user_id'] ?? 0),
				'lang'   => (string) ($row['lang'] ?? 'en'),
			];
		}

		return $fleets;
	}

	private function wasNotified(int $fleetId): bool
	{
		$row = Database::get()->selectSingle(
			'SELECT fleet_id FROM %%PUSH_FLEET_RETURN_NOTIFIED%%
			WHERE fleet_id = :fleetId AND notified_at > 0',
			[':fleetId' => $fleetId],
			'fleet_id'
		);

		return $row !== false && $row !== null && $row !== '';
	}

	/**
	 * Atomically claim a fleet-return notification slot.
	 * INSERT IGNORE ensures the row exists; UPDATE … WHERE notified_at = 0
	 * succeeds for exactly one concurrent caller (rowCount === 1).
	 *
	 * @param array{fleetId: int, mission: int, galaxy: int, system: int, planet: int, planetName: string, returnEnd: int} $fleet
	 */
	private function claimPending(int $userId, array $fleet): bool
	{
		$db = Database::get();
		$db->insert(
			'INSERT IGNORE INTO %%PUSH_FLEET_RETURN_NOTIFIED%%
			(fleet_id, user_id, mission, galaxy, system, planet, planet_name, return_end, notified_at)
			VALUES (:fleetId, :userId, :mission, :galaxy, :system, :planet, :planetName, :returnEnd, :notifiedAt)',
			[
				':fleetId'    => $fleet['fleetId'],
				':userId'     => $userId,
				':mission'    => $fleet['mission'],
				':galaxy'     => $fleet['galaxy'],
				':system'     => $fleet['system'],
				':planet'     => $fleet['planet'],
				':planetName' => $fleet['planetName'],
				':returnEnd'  => $fleet['returnEnd'],
				':notifiedAt' => 0,
			]
		);
		$db->update(
			'UPDATE %%PUSH_FLEET_RETURN_NOTIFIED%% SET notified_at = :notifiedAt
			WHERE fleet_id = :fleetId AND notified_at = 0',
			[
				':fleetId'    => $fleet['fleetId'],
				':notifiedAt' => defined('TIMESTAMP') ? TIMESTAMP : time(),
			]
		);

		return (int) $db->rowCount() === 1;
	}

	private function releaseClaim(int $fleetId): void
	{
		Database::get()->update(
			'UPDATE %%PUSH_FLEET_RETURN_NOTIFIED%% SET notified_at = 0
			WHERE fleet_id = :fleetId AND notified_at > 0',
			[':fleetId' => $fleetId]
		);
	}

	private function cleanup(int $now): void
	{
		Database::get()->delete(
			'DELETE FROM %%PUSH_FLEET_RETURN_NOTIFIED%%
			WHERE (notified_at > 0 AND notified_at < :old)
			OR (notified_at = 0 AND return_end < :old)',
			[':old' => $now - self::CLEANUP_AFTER]
		);
	}
}
