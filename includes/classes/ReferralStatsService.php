<?php

namespace HiveNova\Core;

/**
 * Aggregates referral signup stats for admin reporting.
 */
class ReferralStatsService
{
	public const STATUS_PENDING = 'pending';
	public const STATUS_READY = 'ready';
	public const STATUS_PAID = 'paid';

	public static function bonusStatus(int $refBonus, int $totalPoints, int $refMinPoints): string
	{
		if ($refBonus !== 1) {
			return self::STATUS_PAID;
		}

		return $totalPoints >= $refMinPoints ? self::STATUS_READY : self::STATUS_PENDING;
	}

	/**
	 * @return array{
	 *   total_recruits: int,
	 *   active_referrers: int,
	 *   pending_bonus: int,
	 *   bonus_paid: int
	 * }
	 */
	public function getSummary(DatabaseInterface $db, int $universe, int $refMinPoints): array
	{
		$row = $db->selectSingle(
			'SELECT
				COUNT(*) AS total_recruits,
				COUNT(DISTINCT recruit.ref_id) AS active_referrers,
				SUM(CASE WHEN recruit.ref_bonus = 1 THEN 1 ELSE 0 END) AS pending_bonus,
				SUM(CASE WHEN recruit.ref_bonus = 0 THEN 1 ELSE 0 END) AS bonus_paid
			FROM %%USERS%% recruit
			WHERE recruit.universe = :universe AND recruit.ref_id > 0',
			[
				':universe' => $universe,
			]
		);

		return [
			'total_recruits'   => (int) ($row['total_recruits'] ?? 0),
			'active_referrers' => (int) ($row['active_referrers'] ?? 0),
			'pending_bonus'    => (int) ($row['pending_bonus'] ?? 0),
			'bonus_paid'       => (int) ($row['bonus_paid'] ?? 0),
			'ref_minpoints'    => $refMinPoints,
		];
	}

	public function countReferrers(DatabaseInterface $db, int $universe): int
	{
		$row = $db->selectSingle(
			'SELECT COUNT(DISTINCT recruit.ref_id) AS cnt
			FROM %%USERS%% recruit
			WHERE recruit.universe = :universe AND recruit.ref_id > 0',
			[':universe' => $universe]
		);

		return (int) ($row['cnt'] ?? 0);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getReferrerRows(
		DatabaseInterface $db,
		int $universe,
		int $refMinPoints,
		int $limit,
		int $offset,
	): array {
		$rows = $db->select(
			'SELECT
				ref.id AS referrer_id,
				ref.username AS referrer_username,
				ref.hive_account AS referrer_hive,
				COUNT(recruit.id) AS recruit_count,
				SUM(CASE WHEN recruit.ref_bonus = 1 THEN 1 ELSE 0 END) AS pending_bonus,
				SUM(CASE WHEN recruit.ref_bonus = 0 THEN 1 ELSE 0 END) AS bonus_paid,
				SUM(CASE WHEN COALESCE(stats.total_points, 0) >= :minPoints THEN 1 ELSE 0 END) AS qualified_count
			FROM %%USERS%% recruit
			INNER JOIN %%USERS%% ref ON ref.id = recruit.ref_id
			LEFT JOIN %%STATPOINTS%% stats ON stats.id_owner = recruit.id AND stats.stat_type = 1
			WHERE recruit.universe = :universe AND recruit.ref_id > 0
			GROUP BY ref.id, ref.username, ref.hive_account
			ORDER BY recruit_count DESC, ref.username ASC
			LIMIT :limit OFFSET :offset',
			[
				':universe'  => $universe,
				':minPoints' => $refMinPoints,
				':limit'     => $limit,
				':offset'    => $offset,
			]
		);

		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'referrer_id'       => (int) $row['referrer_id'],
				'referrer_username' => (string) $row['referrer_username'],
				'referrer_hive'     => (string) ($row['referrer_hive'] ?? ''),
				'recruit_count'     => (int) $row['recruit_count'],
				'pending_bonus'     => (int) $row['pending_bonus'],
				'bonus_paid'        => (int) $row['bonus_paid'],
				'qualified_count'   => (int) $row['qualified_count'],
				'referral_link'     => 'index.php?ref=' . (int) $row['referrer_id'],
			];
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getRecentRecruits(
		DatabaseInterface $db,
		int $universe,
		int $refMinPoints,
		int $limit,
		int $offset,
	): array {
		$rows = $db->select(
			'SELECT
				recruit.id AS recruit_id,
				recruit.username AS recruit_username,
				recruit.register_time,
				recruit.ref_bonus,
				ref.id AS referrer_id,
				ref.username AS referrer_username,
				COALESCE(stats.total_points, 0) AS total_points
			FROM %%USERS%% recruit
			INNER JOIN %%USERS%% ref ON ref.id = recruit.ref_id
			LEFT JOIN %%STATPOINTS%% stats ON stats.id_owner = recruit.id AND stats.stat_type = 1
			WHERE recruit.universe = :universe AND recruit.ref_id > 0
			ORDER BY recruit.register_time DESC, recruit.id DESC
			LIMIT :limit OFFSET :offset',
			[
				':universe' => $universe,
				':limit'    => $limit,
				':offset'   => $offset,
			]
		);

		$out = [];
		foreach ($rows as $row) {
			$points = (int) $row['total_points'];
			$refBonus = (int) $row['ref_bonus'];
			$out[] = [
				'recruit_id'         => (int) $row['recruit_id'],
				'recruit_username'   => (string) $row['recruit_username'],
				'register_time'      => (int) $row['register_time'],
				'referrer_id'        => (int) $row['referrer_id'],
				'referrer_username'  => (string) $row['referrer_username'],
				'total_points'       => $points,
				'bonus_status'       => self::bonusStatus($refBonus, $points, $refMinPoints),
			];
		}

		return $out;
	}

	public function countRecruits(DatabaseInterface $db, int $universe): int
	{
		$row = $db->selectSingle(
			'SELECT COUNT(*) AS cnt FROM %%USERS%% WHERE universe = :universe AND ref_id > 0',
			[':universe' => $universe]
		);

		return (int) ($row['cnt'] ?? 0);
	}
}
