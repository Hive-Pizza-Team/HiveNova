<?php

namespace HiveNova\Core;

use DateTime;
use DateTimeZone;
use RuntimeException;

class DirectiveService
{
	public const CSRF_SESSION_KEY = 'commander_csrf';
	public const ERROR_LOCKED = 'already_selected';
	public const ERROR_UNKNOWN = 'unknown_directive';
	public const ERROR_DISABLED = 'module_disabled';
	public const ERROR_NOT_COMPLETE = 'not_complete';
	public const ERROR_CLAIMED = 'already_claimed';
	public const ERROR_NO_DIRECTIVE = 'no_directive';
	public const ERROR_NO_PERIOD = 'no_period';

	/** Directive periods reset each UTC calendar day. */
	public const PERIOD_SECONDS = 86400;

	/** Push “period ending” when this many seconds remain. */
	public const PERIOD_ENDING_SECONDS = 3600;

	/**
	 * @return array{start: int, end: int}
	 */
	public static function periodWindow(int $timestamp): array
	{
		$dt = new DateTime('@' . $timestamp);
		$dt->setTimezone(new DateTimeZone('UTC'));
		$dt->setTime(0, 0, 0);
		$start = (int) $dt->getTimestamp();

		return [
			'start' => $start,
			'end' => $start + self::PERIOD_SECONDS,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function getCurrentPeriod(int $universe, ?int $timestamp = null): ?array
	{
		$timestamp ??= TIMESTAMP;
		$window = self::periodWindow($timestamp);
		$db = Database::get();
		$row = $db->selectSingle(
			'SELECT id, universe, period_start, period_end, created_at
			FROM %%DIRECTIVE_PERIODS%%
			WHERE universe = :universe AND period_start = :start',
			[
				':universe' => $universe,
				':start' => $window['start'],
			]
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function ensureCurrentPeriod(int $universe, ?int $timestamp = null): array
	{
		$timestamp ??= TIMESTAMP;
		$existing = self::getCurrentPeriod($universe, $timestamp);
		if (is_array($existing)) {
			return $existing;
		}

		$window = self::periodWindow($timestamp);
		$db = Database::get();
		$db->insert(
			'INSERT INTO %%DIRECTIVE_PERIODS%% (universe, period_start, period_end, created_at)
			VALUES (:universe, :start, :end, :created)',
			[
				':universe' => $universe,
				':start' => $window['start'],
				':end' => $window['end'],
				':created' => $timestamp,
			]
		);

		$created = self::getCurrentPeriod($universe, $timestamp);
		if (!is_array($created)) {
			return [
				'id' => (int) $db->lastInsertId(),
				'universe' => $universe,
				'period_start' => $window['start'],
				'period_end' => $window['end'],
				'created_at' => $timestamp,
			];
		}

		return $created;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function getUserDirective(int $userId, int $periodId): ?array
	{
		$db = Database::get();
		$row = $db->selectSingle(
			'SELECT id, user_id, period_id, directive_key, progress_json, completed_at, reward_claimed_at
			FROM %%USER_DIRECTIVES%%
			WHERE user_id = :userId AND period_id = :periodId',
			[
				':userId' => $userId,
				':periodId' => $periodId,
			]
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function selectDirective(int $userId, int $universe, string $directiveKey): array
	{
		if (!isModuleAvailable(MODULE_COMMANDER)) {
			throw new RuntimeException(self::ERROR_DISABLED);
		}
		if (!DirectiveCatalog::exists($directiveKey)) {
			throw new RuntimeException(self::ERROR_UNKNOWN);
		}

		$period = self::ensureCurrentPeriod($universe);
		$periodId = (int) $period['id'];
		$existing = self::getUserDirective($userId, $periodId);
		if (is_array($existing)) {
			throw new RuntimeException(self::ERROR_LOCKED);
		}

		$progress = DirectiveCatalog::emptyProgress($directiveKey);
		$db = Database::get();
		$db->insert(
			'INSERT INTO %%USER_DIRECTIVES%% (user_id, period_id, directive_key, progress_json, completed_at, reward_claimed_at)
			VALUES (:userId, :periodId, :directiveKey, :progress, NULL, NULL)',
			[
				':userId' => $userId,
				':periodId' => $periodId,
				':directiveKey' => $directiveKey,
				':progress' => json_encode($progress),
			]
		);

		$row = self::getUserDirective($userId, $periodId);
		if (!is_array($row)) {
			$row = [
				'id' => (int) $db->lastInsertId(),
				'user_id' => $userId,
				'period_id' => $periodId,
				'directive_key' => $directiveKey,
				'progress_json' => json_encode($progress),
				'completed_at' => null,
				'reward_claimed_at' => null,
			];
		}

		return $row;
	}

	/**
	 * @return array<string, int>
	 */
	public static function claimReward(int $userId, int $universe, int $planetId): array
	{
		if (!isModuleAvailable(MODULE_COMMANDER)) {
			throw new RuntimeException(self::ERROR_DISABLED);
		}

		$period = self::getCurrentPeriod($universe);
		if (!is_array($period)) {
			throw new RuntimeException(self::ERROR_NO_PERIOD);
		}

		$db = Database::get();
		$db->beginTransaction();
		try {
			$row = $db->selectSingle(
				'SELECT id, user_id, period_id, directive_key, progress_json, completed_at, reward_claimed_at
				FROM %%USER_DIRECTIVES%%
				WHERE user_id = :userId AND period_id = :periodId FOR UPDATE',
				[
					':userId' => $userId,
					':periodId' => (int) $period['id'],
				]
			);
			if (!is_array($row)) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_NO_DIRECTIVE);
			}
			if (empty($row['completed_at'])) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_NOT_COMPLETE);
			}
			if (!empty($row['reward_claimed_at'])) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_CLAIMED);
			}

			$catalog = DirectiveCatalog::get((string) $row['directive_key']);
			$base = is_array($catalog) ? $catalog['reward'] : ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
			$reward = DirectiveCatalog::scaledReward($base, self::playerPoints($userId));

			$db->update(
				'UPDATE %%PLANETS%% SET
					metal = metal + :metal,
					crystal = crystal + :crystal,
					deuterium = deuterium + :deuterium
				WHERE id = :planetId',
				[
					':metal' => (int) $reward['metal'],
					':crystal' => (int) $reward['crystal'],
					':deuterium' => (int) $reward['deuterium'],
					':planetId' => $planetId,
				]
			);
			self::addResourcesToSessionPlanet($planetId, $reward);
			$db->update(
				'UPDATE %%USER_DIRECTIVES%% SET reward_claimed_at = :claimed WHERE id = :id AND reward_claimed_at IS NULL',
				[
					':claimed' => TIMESTAMP,
					':id' => (int) $row['id'],
				]
			);
			$db->commit();

			return [
				'metal' => (int) $reward['metal'],
				'crystal' => (int) $reward['crystal'],
				'deuterium' => (int) $reward['deuterium'],
			];
		} catch (RuntimeException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}
	}

	/**
	 * Keep the in-memory planet in sync with the SQL increment, and shift the
	 * ResourceUpdate save baseline so a later delta save does not re-apply it.
	 *
	 * @param array{metal?: int, crystal?: int, deuterium?: int} $reward
	 */
	public static function addResourcesToSessionPlanet(int $planetId, array $reward): void
	{
		if (!isset($GLOBALS['PLANET']) || !is_array($GLOBALS['PLANET'])) {
			return;
		}
		if ((int) ($GLOBALS['PLANET']['id'] ?? 0) !== $planetId) {
			return;
		}

		$metal = (int) ($reward['metal'] ?? 0);
		$crystal = (int) ($reward['crystal'] ?? 0);
		$deuterium = (int) ($reward['deuterium'] ?? 0);

		$GLOBALS['PLANET']['metal'] = (float) ($GLOBALS['PLANET']['metal'] ?? 0) + $metal;
		$GLOBALS['PLANET']['crystal'] = (float) ($GLOBALS['PLANET']['crystal'] ?? 0) + $crystal;
		$GLOBALS['PLANET']['deuterium'] = (float) ($GLOBALS['PLANET']['deuterium'] ?? 0) + $deuterium;
	}

	public static function playerPoints(int $userId): int
	{
		$row = Database::get()->selectSingle(
			'SELECT total_points FROM %%STATPOINTS%% WHERE id_owner = :userId AND stat_type = :type',
			[
				':userId' => $userId,
				':type' => 1,
			]
		);
		if (!is_array($row)) {
			return 0;
		}

		return (int) ($row['total_points'] ?? 0);
	}

	public static function issueCsrfToken(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE && session_status() !== PHP_SESSION_DISABLED) {
			@session_start();
		}
		$token = bin2hex(random_bytes(16));
		$_SESSION[self::CSRF_SESSION_KEY] = $token;

		return $token;
	}

	public static function validateCsrfToken(?string $token): bool
	{
		$expected = $_SESSION[self::CSRF_SESSION_KEY] ?? '';
		if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
			return false;
		}

		return hash_equals($expected, $token);
	}

	public static function isSameOriginRequest(): bool
	{
		$hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
		$host = strtolower(explode(':', $hostHeader)[0]);
		$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
		if ($origin !== '') {
			$originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
			return $originHost !== '' && $originHost === $host;
		}

		$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
		if ($referer === '') {
			return true;
		}
		$refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));

		return $refererHost !== '' && $refererHost === $host;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function getBriefingData(int $userId, int $universe): array
	{
		$period = self::ensureCurrentPeriod($universe);
		$userDirective = self::getUserDirective($userId, (int) $period['id']);
		$remaining = max(0, (int) $period['period_end'] - TIMESTAMP);
		$points = self::playerPoints($userId);
		$catalog = [];
		foreach (DirectiveCatalog::all() as $key => $def) {
			$def['reward'] = DirectiveCatalog::scaledReward($def['reward'] ?? [], $points);
			$catalog[$key] = $def;
		}
		$selected = null;
		if (is_array($userDirective)) {
			$def = DirectiveCatalog::get((string) $userDirective['directive_key']);
			$progress = json_decode((string) ($userDirective['progress_json'] ?? '{}'), true);
			if (!is_array($progress)) {
				$progress = [];
			}
			$targets = is_array($def) ? ($def['targets'] ?? []) : [];
			$selected = [
				'key' => $userDirective['directive_key'],
				'title_key' => $def['title_key'] ?? '',
				'desc_key' => $def['desc_key'] ?? '',
				'suggestion_key' => $def['suggestion_key'] ?? '',
				'recommended_stance' => $def['recommended_stance'] ?? 'balanced',
				'targets' => $targets,
				'progress' => $progress,
				'bars' => DirectiveCatalog::progressBars($targets, $progress),
				'completed' => !empty($userDirective['completed_at']),
				'claimed' => !empty($userDirective['reward_claimed_at']),
				'reward' => DirectiveCatalog::scaledReward(is_array($def) ? ($def['reward'] ?? []) : [], $points),
			];
		}

		$pending = ExpeditionChoiceService::pendingForUser($userId);
		$expeditions = self::activeExpeditions($userId);

		return [
			'enabled' => isModuleAvailable(MODULE_COMMANDER),
			'period_start' => (int) $period['period_start'],
			'period_end' => (int) $period['period_end'],
			'remaining' => $remaining,
			'csrf' => self::issueCsrfToken(),
			'options' => array_values($catalog),
			'directive' => $selected,
			'pending_choices' => $pending,
			'expeditions' => $expeditions,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function activeExpeditions(int $userId): array
	{
		$db = Database::get();
		$rows = $db->select(
			'SELECT fleet_id, fleet_mission, fleet_meta, fleet_end_stay, fleet_end_time, fleet_mess
			FROM %%FLEETS%%
			WHERE fleet_owner = :userId AND fleet_mission = 15',
			[':userId' => $userId]
		);
		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'fleet_id' => (int) $row['fleet_id'],
				'stance' => ExpeditionChoiceService::stanceFromMeta($row['fleet_meta'] ?? null),
				'end_stay' => (int) ($row['fleet_end_stay'] ?? 0),
				'end_time' => (int) ($row['fleet_end_time'] ?? 0),
				'state' => (int) ($row['fleet_mess'] ?? 0),
			];
		}

		return $out;
	}

	public static function notifyPeriodEndingIfNeeded(int $universe): void
	{
		$period = self::getCurrentPeriod($universe);
		if (!is_array($period)) {
			return;
		}
		$remaining = (int) $period['period_end'] - TIMESTAMP;
		if ($remaining > self::PERIOD_ENDING_SECONDS || $remaining < 0) {
			return;
		}

		$db = Database::get();
		$rows = $db->select(
			'SELECT id, user_id, progress_json, completed_at, reward_claimed_at
			FROM %%USER_DIRECTIVES%%
			WHERE period_id = :periodId',
			[':periodId' => (int) $period['id']]
		);
		foreach ($rows as $row) {
			$progress = json_decode((string) ($row['progress_json'] ?? '{}'), true);
			if (!is_array($progress)) {
				$progress = [];
			}
			if (!empty($progress['ending_push_sent'])) {
				continue;
			}
			PushNotificationService::notifyDirectiveMilestone(
				(int) $row['user_id'],
				'directive_period_ending',
				(int) $period['period_end']
			);
			$progress['ending_push_sent'] = 1;
			$db->update(
				'UPDATE %%USER_DIRECTIVES%% SET progress_json = :progress WHERE id = :id',
				[
					':progress' => json_encode($progress),
					':id' => (int) $row['id'],
				]
			);
		}
	}
}
