<?php

namespace HiveNova\Core;

/**
 * Loads Battle Hall (TOPKB) reports for the public login-side battle page.
 *
 * Only TOPKB rows are exposed — private combat reports stay on game.php (noindex).
 */
class PublicBattleReportService
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
	 * @return array{
	 *   rid: string,
	 *   units: float|int|string,
	 *   result: string,
	 *   time: int,
	 *   universe: int,
	 *   attacker: string,
	 *   defender: string,
	 *   combatReport: array<string, mixed>
	 * }|null
	 */
	public function findPublicReport(string $rid): ?array
	{
		$rid = trim($rid);
		if ($rid === '' || !preg_match('/^[a-f0-9]{32}$/i', $rid)) {
			return null;
		}

		$row = $this->db()->selectSingle(
			'SELECT t.rid, t.units, t.result, t.time, t.universe, r.raport
			FROM %%TOPKB%% t
			INNER JOIN %%RW%% r ON r.rid = t.rid
			WHERE t.rid = :rid
			LIMIT 1',
			[':rid' => $rid]
		);

		if ($row === false || $row === null || $row === []) {
			return null;
		}

		$combatReport = safe_unserialize($row['raport'] ?? null);
		if (!is_array($combatReport)) {
			return null;
		}

		$combatReport = $this->normalizeLegacyReport($combatReport);
		$names = $this->loadSideNames((string) $row['rid']);

		return [
			'rid'          => (string) $row['rid'],
			'units'        => $row['units'],
			'result'       => (string) $row['result'],
			'time'         => (int) $row['time'],
			'universe'     => (int) $row['universe'],
			'attacker'     => $names['attacker'],
			'defender'     => $names['defender'],
			'combatReport' => $combatReport,
		];
	}

	/**
	 * @return array{attacker: string, defender: string}
	 */
	private function loadSideNames(string $rid): array
	{
		$rows = $this->db()->select(
			'SELECT tk.role,
				GROUP_CONCAT(
					IF(tk.username <> \'\', tk.username, u.username)
					SEPARATOR \' & \'
				) AS names
			FROM %%TOPKB_USERS%% tk
			LEFT JOIN %%USERS%% u ON u.id = tk.uid
			WHERE tk.rid = :rid
			GROUP BY tk.role',
			[':rid' => $rid]
		);

		$out = ['attacker' => '', 'defender' => ''];
		foreach ($rows as $row) {
			$names = (string) ($row['names'] ?? '');
			if ((int) $row['role'] === 1) {
				$out['attacker'] = $names;
			} elseif ((int) $row['role'] === 2) {
				$out['defender'] = $names;
			}
		}

		return $out;
	}

	/**
	 * Mirror ShowRaportPage::BCWrapperPreRev2321 for guest-safe display.
	 *
	 * @param array<string, mixed> $combatReport
	 * @return array<string, mixed>
	 */
	public function normalizeLegacyReport(array $combatReport): array
	{
		if (isset($combatReport['moon']['desfail'])) {
			$combatReport['moon'] = [
				'moonName'             => $combatReport['moon']['name'],
				'moonChance'           => $combatReport['moon']['chance'],
				'moonDestroySuccess'   => !$combatReport['moon']['desfail'],
				'fleetDestroyChance'   => $combatReport['moon']['chance2'],
				'fleetDestroySuccess'  => !$combatReport['moon']['fleetfail'],
			];
		} elseif (isset($combatReport['moon'][0])) {
			$combatReport['moon'] = [
				'moonName'             => $combatReport['moon'][1],
				'moonChance'           => $combatReport['moon'][0],
				'moonDestroySuccess'   => !$combatReport['moon'][2],
				'fleetDestroyChance'   => $combatReport['moon'][3],
				'fleetDestroySuccess'  => !$combatReport['moon'][4],
			];
		}

		if (isset($combatReport['simu'])) {
			$combatReport['additionalInfo'] = $combatReport['simu'];
		}

		if (isset($combatReport['debris'][0])) {
			$combatReport['debris'] = [
				RESOURCE_METAL   => $combatReport['debris'][0],
				RESOURCE_CRYSTAL => $combatReport['debris'][1],
			];
		}

		if (!empty($combatReport['steal']['metal'])) {
			$combatReport['steal'] = [
				RESOURCE_METAL     => $combatReport['steal']['metal'],
				RESOURCE_CRYSTAL   => $combatReport['steal']['crystal'],
				RESOURCE_DEUTERIUM => $combatReport['steal']['deuterium'],
			];
		}

		return $combatReport;
	}
}
