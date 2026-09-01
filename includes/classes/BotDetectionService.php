<?php

namespace HiveNova\Core;

class BotDetectionService
{
	public const SLEEP_THRESHOLD = 7200;
	public const DAYS_WINDOW     = 7;
	public const MIN_ACTIONS       = 10;
	public const NPC_BOT_EMAIL     = 'bot';

	public function __construct(
		private ?DatabaseInterface $db = null,
	) {
		$this->db ??= Database::get();
	}

	public static function cutoffTimestamp(?int $now = null): int
	{
		$now ??= TIMESTAMP;

		return $now - (self::DAYS_WINDOW * 24 * 60 * 60);
	}

	/**
	 * @param list<int> $sortedTimes ascending Unix timestamps
	 */
	public static function computeMaxGapSeconds(array $sortedTimes, int $cutoff, int $now): int
	{
		$count = count($sortedTimes);
		if ($count === 0) {
			return 0;
		}

		$maxGap = $sortedTimes[0] - $cutoff;
		for ($i = 1; $i < $count; $i++) {
			$gap = $sortedTimes[$i] - $sortedTimes[$i - 1];
			if ($gap > $maxGap) {
				$maxGap = $gap;
			}
		}

		$tailGap = $now - $sortedTimes[$count - 1];
		if ($tailGap > $maxGap) {
			$maxGap = $tailGap;
		}

		return $maxGap;
	}

	public static function isFlagged(int $maxGapSeconds, int $actionCount): bool
	{
		return $actionCount >= self::MIN_ACTIONS && $maxGapSeconds < self::SLEEP_THRESHOLD;
	}

	public static function formatGapHuman(int $seconds): string
	{
		$hours   = (int) floor($seconds / 3600);
		$minutes = (int) floor(($seconds % 3600) / 60);

		return sprintf('%dh %02dm', $hours, $minutes);
	}

	/**
	 * @param list<array<string, mixed>> $suspects
	 */
	public static function computeDigestHash(array $suspects): string
	{
		if ($suspects === []) {
			return '';
		}

		$parts = [];
		foreach ($suspects as $row) {
			$parts[] = (int) $row['id'] . ':' . (int) $row['max_gap_seconds'];
		}
		sort($parts, SORT_STRING);

		return hash('sha256', implode('|', $parts));
	}

	/**
	 * @return list<array{
	 *   id: int,
	 *   username: string,
	 *   total_actions: int,
	 *   fleet_count: int,
	 *   building_count: int,
	 *   research_count: int,
	 *   shipyard_count: int,
	 *   max_gap_seconds: int,
	 *   max_gap_human: string
	 * }>
	 */
	public function findSuspects(int $universe, ?int $now = null): array
	{
		$now    = $now ?? TIMESTAMP;
		$cutoff = self::cutoffTimestamp($now);

		$sql = 'WITH events AS (
			SELECT owner_id AS user_id, event_time, source FROM (
				SELECT fl.fleet_owner AS owner_id, fl.fleet_start_time AS event_time, \'fleet\' AS source
				FROM %%LOG_FLEETS%% fl
				WHERE fl.fleet_universe = :universe
				  AND fl.fleet_start_time >= :cutoff
				UNION ALL
				SELECT lb.owner_id, lb.queued_at, \'building\'
				FROM %%LOG_BUILDINGS%% lb
				WHERE lb.universe = :universe
				  AND lb.queued_at >= :cutoff
				UNION ALL
				SELECT lr.owner_id, lr.queued_at, \'research\'
				FROM %%LOG_RESEARCH%% lr
				WHERE lr.universe = :universe
				  AND lr.queued_at >= :cutoff
				UNION ALL
				SELECT ls.owner_id, ls.queued_at, \'shipyard\'
				FROM %%LOG_SHIPYARD%% ls
				WHERE ls.universe = :universe
				  AND ls.queued_at >= :cutoff
			) raw
		),
		ordered AS (
			SELECT user_id, event_time, source,
			       LAG(event_time) OVER (PARTITION BY user_id ORDER BY event_time) AS prev_time
			FROM events
		),
		gaps AS (
			SELECT user_id,
			       MAX(event_time - prev_time) AS max_internal_gap,
			       MIN(event_time) AS first_time,
			       MAX(event_time) AS last_time,
			       COUNT(*) AS action_count,
			       SUM(CASE WHEN source = \'fleet\' THEN 1 ELSE 0 END) AS fleet_count,
			       SUM(CASE WHEN source = \'building\' THEN 1 ELSE 0 END) AS building_count,
			       SUM(CASE WHEN source = \'research\' THEN 1 ELSE 0 END) AS research_count,
			       SUM(CASE WHEN source = \'shipyard\' THEN 1 ELSE 0 END) AS shipyard_count
			FROM ordered
			GROUP BY user_id
			HAVING action_count >= :minActions
		)
		SELECT u.id, u.username,
		       GREATEST(
		         COALESCE(g.max_internal_gap, 0),
		         g.first_time - :cutoff,
		         :now - g.last_time
		       ) AS max_gap_seconds,
		       g.action_count AS total_actions,
		       g.fleet_count,
		       g.building_count,
		       g.research_count,
		       g.shipyard_count
		FROM gaps g
		JOIN %%USERS%% u ON u.id = g.user_id
		WHERE u.bana = 0
		  AND u.urlaubs_modus = 0
		  AND u.email != :npcEmail
		  AND u.authlevel = :authUsr
		  AND GREATEST(
		         COALESCE(g.max_internal_gap, 0),
		         g.first_time - :cutoff,
		         :now - g.last_time
		       ) < :sleepThreshold
		ORDER BY max_gap_seconds ASC;';

		$rows = $this->db->select($sql, [
			':universe'       => $universe,
			':cutoff'         => $cutoff,
			':now'            => $now,
			':minActions'     => self::MIN_ACTIONS,
			':sleepThreshold' => self::SLEEP_THRESHOLD,
			':npcEmail'       => self::NPC_BOT_EMAIL,
			':authUsr'        => AUTH_USR,
		]);

		$suspects = [];
		foreach ($rows as $row) {
			$maxGap = (int) $row['max_gap_seconds'];
			$suspects[] = [
				'id'              => (int) $row['id'],
				'username'        => (string) $row['username'],
				'total_actions'   => (int) $row['total_actions'],
				'fleet_count'     => (int) $row['fleet_count'],
				'building_count'  => (int) $row['building_count'],
				'research_count'  => (int) $row['research_count'],
				'shipyard_count'  => (int) $row['shipyard_count'],
				'max_gap_seconds' => $maxGap,
				'max_gap_human'   => self::formatGapHuman($maxGap),
			];
		}

		return $suspects;
	}

	public function getLastDigestHash(int $universe): ?string
	{
		$row = $this->db->selectSingle(
			'SELECT last_digest_hash FROM %%BOT_DETECTION_STATE%% WHERE universe = :universe;',
			[':universe' => $universe]
		);

		if (!is_array($row)) {
			return null;
		}

		return (string) $row['last_digest_hash'];
	}

	public function shouldNotify(int $universe, string $digestHash): bool
	{
		if ($digestHash === '') {
			return false;
		}

		return $this->getLastDigestHash($universe) !== $digestHash;
	}

	public function markNotified(int $universe, string $digestHash): void
	{
		$this->db->insert(
			'INSERT INTO %%BOT_DETECTION_STATE%% (universe, last_digest_hash, updated_at)
			VALUES (:universe, :hash, :updated)
			ON DUPLICATE KEY UPDATE last_digest_hash = :hash, updated_at = :updated;',
			[
				':universe' => $universe,
				':hash'     => $digestHash,
				':updated'  => TIMESTAMP,
			]
		);
	}

	/**
	 * @param list<array<string, mixed>> $suspects
	 */
	public function buildReportText(array $suspects): string
	{
		$lines = [];
		foreach ($suspects as $row) {
			$lines[] = sprintf(
				'- %s (longest break: %s)',
				$row['username'],
				$row['max_gap_human']
			);
		}

		return 'The following players have shown no natural sleep break in the last '
			. self::DAYS_WINDOW . " days:\n"
			. implode("\n", $lines) . "\n"
			. 'These accounts may be using automated scripts. Please review.';
	}

	/**
	 * @return list<array{id: int}>
	 */
	public function adminRecipientIds(int $universe): array
	{
		return $this->db->select(
			'SELECT id FROM %%USERS%% WHERE universe = :universe AND authlevel >= :authlevel;',
			[
				':universe'  => $universe,
				':authlevel' => AUTH_ADM,
			]
		);
	}
}
