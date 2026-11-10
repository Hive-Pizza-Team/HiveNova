<?php

namespace HiveNova\Core;

/**
 * Battle hall TOPKB listing without correlated per-row subqueries.
 *
 * Names come from the fight snapshot on TOPKB_USERS. A deleted account
 * (no USERS row) still shows that snapshot. Live USERS.username is only
 * the fallback when the snapshot is empty.
 */
class BattleHallService
{
	public const MISSING_NAME = '(deleted)';

	public function __construct(
		private readonly ?DatabaseInterface $db = null,
	) {
	}

	private function db(): DatabaseInterface
	{
		return $this->db ?? Database::get();
	}

	/**
	 * Top battles for a universe, names resolved in one join pass.
	 *
	 * @return list<array{result: string, time: int, units: float|int|string, rid: string, attacker: string, defender: string}>
	 */
	public function listTopBattles(int $universe, int $limit = 100, string $missingLabel = self::MISSING_NAME): array
	{
		$limit = max(1, min(100, $limit));
		$db = $this->db();

		$top = $db->select(
			'SELECT rid, units, result, time
			FROM %%TOPKB%%
			WHERE universe = :universe
			ORDER BY units DESC
			LIMIT ' . $limit,
			[':universe' => $universe]
		);

		if ($top === []) {
			return [];
		}

		$namesByRid = $this->participantNames(array_column($top, 'rid'), $missingLabel);

		$out = [];
		foreach ($top as $row) {
			$rid = (string) $row['rid'];
			$sides = $namesByRid[$rid] ?? ['attacker' => '', 'defender' => ''];
			$out[] = [
				'result'   => (string) $row['result'],
				'time'     => (int) $row['time'],
				'units'    => $row['units'],
				'rid'      => $rid,
				'attacker' => $sides['attacker'],
				'defender' => $sides['defender'],
			];
		}

		return $out;
	}

	/**
	 * Attacker and defender labels for the given report ids.
	 *
	 * Each TOPKB_USERS row is kept even when its uid is gone from USERS
	 * (LEFT JOIN). Display order is snapshot username, then the live
	 * username, then $missingLabel.
	 *
	 * @param list<mixed> $rids
	 * @return array<string, array{attacker: string, defender: string}>
	 */
	public function participantNames(array $rids, string $missingLabel = self::MISSING_NAME): array
	{
		$rids = array_values(array_unique(array_filter(
			array_map('strval', $rids),
			static fn (string $rid): bool => $rid !== ''
		)));
		if ($rids === []) {
			return [];
		}

		$missingLabel = trim($missingLabel);
		if ($missingLabel === '') {
			$missingLabel = self::MISSING_NAME;
		}

		$db = $this->db();
		$quoted = [];
		foreach ($rids as $rid) {
			$quoted[] = $db->quote($rid);
		}
		$inList = implode(',', $quoted);

		$rows = $db->select(
			'SELECT tk.rid, tk.role, tk.uid, tk.username AS snapshot_name, u.username AS live_name
			FROM %%TOPKB_USERS%% tk
			LEFT JOIN %%USERS%% u ON u.id = tk.uid
			WHERE tk.rid IN (' . $inList . ')
			ORDER BY tk.rid, tk.role, tk.uid'
		);

		$grouped = [];
		foreach ($rows as $row) {
			$rid = (string) ($row['rid'] ?? '');
			$role = (int) ($row['role'] ?? 0);
			if ($rid === '' || ($role !== 1 && $role !== 2)) {
				continue;
			}
			$live = $row['live_name'] ?? null;
			$grouped[$rid][$role][] = [
				'uid'  => (int) ($row['uid'] ?? 0),
				'name' => $this->resolveDisplayName(
					isset($row['snapshot_name']) ? (string) $row['snapshot_name'] : '',
					$live === null ? null : (string) $live,
					$missingLabel
				),
			];
		}

		$out = [];
		foreach ($rids as $rid) {
			$out[$rid] = [
				'attacker' => $this->joinSide($grouped[$rid][1] ?? []),
				'defender' => $this->joinSide($grouped[$rid][2] ?? []),
			];
		}

		return $out;
	}

	/**
	 * @param list<array{uid: int, name: string}> $participants
	 */
	private function joinSide(array $participants): string
	{
		usort($participants, static fn (array $a, array $b): int => $a['uid'] <=> $b['uid']);
		$names = [];
		foreach ($participants as $participant) {
			if ($participant['name'] !== '') {
				$names[] = $participant['name'];
			}
		}

		return implode(' & ', $names);
	}

	private function resolveDisplayName(?string $snapshot, ?string $live, string $missingLabel): string
	{
		$snapshotName = trim((string) $snapshot);
		if ($snapshotName !== '') {
			return $snapshotName;
		}
		$liveName = trim((string) $live);
		if ($liveName !== '') {
			return $liveName;
		}

		return $missingLabel;
	}
}
