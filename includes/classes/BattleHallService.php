<?php

namespace HiveNova\Core;

/**
 * Battle hall TOPKB listing without correlated per-row subqueries.
 */
class BattleHallService
{
	public function __construct(
		private readonly ?DatabaseInterface $db = null,
	) {
	}

	private function db(): DatabaseInterface
	{
		return $this->db ?? Database::get();
	}

	/**
	 * Top battles for a universe, newest names resolved in one join pass.
	 *
	 * @return list<array{result: string, time: int, units: float|int|string, rid: string, attacker: string, defender: string}>
	 */
	public function listTopBattles(int $universe, int $limit = 100): array
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

		$namesByRid = $this->loadSideNames(array_column($top, 'rid'));

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
	 * @param list<string> $rids
	 * @return array<string, array{attacker: string, defender: string}>
	 */
	private function loadSideNames(array $rids): array
	{
		$rids = array_values(array_unique(array_filter(array_map('strval', $rids))));
		if ($rids === []) {
			return [];
		}

		$db = $this->db();
		$quoted = [];
		foreach ($rids as $rid) {
			$quoted[] = $db->quote($rid);
		}
		$inList = implode(',', $quoted);

		$rows = $db->select(
			'SELECT tk.rid, tk.role,
				GROUP_CONCAT(
					IF(tk.username <> \'\', tk.username, u.username)
					SEPARATOR \' & \'
				) AS names
			FROM %%TOPKB_USERS%% tk
			LEFT JOIN %%USERS%% u ON u.id = tk.uid
			WHERE tk.rid IN (' . $inList . ')
			GROUP BY tk.rid, tk.role'
		);

		$out = [];
		foreach ($rids as $rid) {
			$out[$rid] = ['attacker' => '', 'defender' => ''];
		}

		foreach ($rows as $row) {
			$rid = (string) $row['rid'];
			$names = (string) ($row['names'] ?? '');
			if (!isset($out[$rid])) {
				$out[$rid] = ['attacker' => '', 'defender' => ''];
			}
			if ((int) $row['role'] === 1) {
				$out[$rid]['attacker'] = $names;
			} elseif ((int) $row['role'] === 2) {
				$out[$rid]['defender'] = $names;
			}
		}

		return $out;
	}
}
