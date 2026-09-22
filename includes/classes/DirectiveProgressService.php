<?php

namespace HiveNova\Core;

class DirectiveProgressService
{
	/**
	 * @param array<string, mixed> $context
	 */
	public static function record(int $userId, string $eventType, array $context = []): void
	{
		if ($userId <= 0 || !DirectiveHooks::enabled()) {
			return;
		}

		$universe = (int) ($context['universe'] ?? Universe::current());
		$period = DirectiveService::getCurrentPeriod($universe);
		if (!is_array($period)) {
			return;
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
				return;
			}

			$key = (string) $row['directive_key'];
			if (!DirectiveCatalog::eventCountsToward($key, $eventType, $context)) {
				$db->rollback();
				return;
			}

			$counter = DirectiveCatalog::counterForEvent($key, $eventType, $context);
			if ($counter === null) {
				$db->rollback();
				return;
			}

			$progress = json_decode((string) ($row['progress_json'] ?? '{}'), true);
			if (!is_array($progress)) {
				$progress = DirectiveCatalog::emptyProgress($key);
			}
			$progress[$counter] = (int) ($progress[$counter] ?? 0) + 1;

			$catalog = DirectiveCatalog::get($key);
			$targets = is_array($catalog) ? $catalog['targets'] : [];
			$complete = empty($row['completed_at']) && self::targetsMet($progress, $targets);
			$params = [
				':progress' => json_encode($progress),
				':id' => (int) $row['id'],
			];
			$sql = 'UPDATE %%USER_DIRECTIVES%% SET progress_json = :progress';
			if ($complete) {
				$sql .= ', completed_at = :completed';
				$params[':completed'] = TIMESTAMP;
			}
			$sql .= ' WHERE id = :id';
			$db->update($sql, $params);
			$db->commit();

			if ($complete) {
				PushNotificationService::notifyDirectiveMilestone($userId, 'directive_completable');
			}
		} catch (\Throwable $e) {
			$db->rollback();
		}
	}

	/**
	 * Persist completion when stored progress already meets the current catalog.
	 * Used when a directive's targets change under an in-progress row.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function markCompleteIfTargetsMet(array $row): array
	{
		if (!empty($row['completed_at']) || empty($row['id'])) {
			return $row;
		}

		$key = (string) ($row['directive_key'] ?? '');
		$catalog = DirectiveCatalog::get($key);
		$targets = is_array($catalog) ? ($catalog['targets'] ?? []) : [];
		$progress = json_decode((string) ($row['progress_json'] ?? '{}'), true);
		if (!is_array($progress) || !self::targetsMet($progress, $targets)) {
			return $row;
		}

		$completed = TIMESTAMP;
		Database::get()->update(
			'UPDATE %%USER_DIRECTIVES%% SET completed_at = :completed WHERE id = :id AND completed_at IS NULL',
			[
				':completed' => $completed,
				':id' => (int) $row['id'],
			]
		);
		$row['completed_at'] = $completed;

		return $row;
	}

	/**
	 * @param array<string, int> $progress
	 * @param array<string, int> $targets
	 */
	public static function targetsMet(array $progress, array $targets): bool
	{
		if ($targets === []) {
			return false;
		}
		foreach ($targets as $counter => $need) {
			if ((int) ($progress[$counter] ?? 0) < (int) $need) {
				return false;
			}
		}

		return true;
	}
}
