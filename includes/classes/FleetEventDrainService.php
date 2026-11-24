<?php

namespace HiveNova\Core;

/**
 * Drains overdue fleet_event rows across multiple passes in one request.
 *
 * A late Overview/Fleet load can need TargetEvent → EndStayEvent → ReturnEvent
 * when fleet_end_stay / fleet_end_time are already past. One lock batch only
 * runs the current fleet_mess stage; re-locking after SaveFleet advances the
 * chain so Hold (A) / expedition EndStay counters and slots update without
 * several manual reloads (#453 / #455).
 */
class FleetEventDrainService
{
	public const DEFAULT_MAX_PASSES = 5;

	/**
	 * @param callable(): string|null $tokenFactory
	 * @param callable(string): FlyingFleetHandler|null $handlerFactory
	 * @return int Number of passes that locked at least one event
	 */
	public static function drainDueEvents(
		?DatabaseInterface $db = null,
		?int $now = null,
		int $maxPasses = self::DEFAULT_MAX_PASSES,
		?callable $tokenFactory = null,
		?callable $handlerFactory = null
	): int {
		$db ??= Database::get();
		$now ??= TIMESTAMP;
		if ($maxPasses < 1) {
			return 0;
		}

		$tokenFactory ??= static fn (): string => getRandomString();
		$handlerFactory ??= static function (string $token): FlyingFleetHandler {
			$handler = new FlyingFleetHandler();
			$handler->setToken($token);

			return $handler;
		};

		$passesWithWork = 0;
		for ($pass = 0; $pass < $maxPasses; $pass++) {
			$token = (string) $tokenFactory();
			$db->update(
				'UPDATE %%FLEETS_EVENT%% SET `lock` = :token WHERE `lock` IS NULL AND `time` <= :time;',
				[
					':time' => $now,
					':token' => $token,
				]
			);

			if ((int) $db->rowCount() === 0) {
				break;
			}

			$passesWithWork++;
			$handler = $handlerFactory($token);
			$failedFleetIds = $handler->run();
			self::releaseLock($db, $token, $failedFleetIds);
		}

		return $passesWithWork;
	}

	/**
	 * @param list<int> $failedFleetIds
	 */
	public static function releaseLock(DatabaseInterface $db, string $token, array $failedFleetIds): void
	{
		if ($failedFleetIds === []) {
			$db->update(
				'UPDATE %%FLEETS_EVENT%% SET `lock` = NULL WHERE `lock` = :token;',
				[':token' => $token]
			);

			return;
		}

		$placeholders = [];
		$params = [':token' => $token];
		foreach ($failedFleetIds as $index => $fleetId) {
			$key = ':failed'.$index;
			$placeholders[] = $key;
			$params[$key] = $fleetId;
		}
		$db->update(
			'UPDATE %%FLEETS_EVENT%% SET `lock` = NULL WHERE `lock` = :token AND fleetID NOT IN ('.implode(', ', $placeholders).')',
			$params
		);
	}
}
