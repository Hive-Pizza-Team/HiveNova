<?php

namespace HiveNova\Core;

/**
 * Loads Battle Hall (TOPKB) combat reports for the public login-side report page.
 */
class PublicBattleReportService
{
	public function __construct(
		private readonly ?BattleHallService $hall = null,
		private readonly ?DatabaseInterface $db = null,
	) {
	}

	private function hall(): BattleHallService
	{
		return $this->hall ?? new BattleHallService($this->db);
	}

	private function db(): DatabaseInterface
	{
		return $this->db ?? Database::get();
	}

	/**
	 * Hall-of-fame report for guests: TOPKB row plus optional deserialized combat blob.
	 *
	 * @return array{
	 *   hall: array{result: string, time: int, units: float|int|string, rid: string, universe: int, attacker: string, defender: string},
	 *   combat: array<string, mixed>|null
	 * }|null
	 */
	public function loadPublicReport(string $rid): ?array
	{
		$hall = $this->hall()->findByRid($rid);
		if ($hall === null) {
			return null;
		}

		$row = $this->db()->selectSingle(
			'SELECT raport FROM %%RW%% WHERE rid = :rid LIMIT 1',
			[':rid' => $hall['rid']]
		);

		$combat = null;
		if (!empty($row['raport']) && is_string($row['raport'])) {
			$decoded = safe_unserialize($row['raport']);
			if (is_array($decoded)) {
				$combat = $this->normalizeCombatReport($decoded);
			}
		}

		return [
			'hall'   => $hall,
			'combat' => $combat,
		];
	}

	/**
	 * Compact unit totals for titles (e.g. 488.7M).
	 */
	public static function formatCompactUnits(float|int|string $units): string
	{
		$value = (float) $units;
		if ($value < 0) {
			$value = 0.0;
		}

		if ($value >= 1_000_000_000) {
			return self::trimCompact(round($value / 1_000_000_000, 1)).'B';
		}
		if ($value >= 1_000_000) {
			return self::trimCompact(round($value / 1_000_000, 1)).'M';
		}
		if ($value >= 1_000) {
			return self::trimCompact(round($value / 1_000, 1)).'K';
		}

		return (string) (int) round($value);
	}

	private static function trimCompact(float $n): string
	{
		$formatted = number_format($n, 1, '.', '');
		return rtrim(rtrim($formatted, '0'), '.') ?: '0';
	}

	/**
	 * @param array<string, mixed> $combatReport
	 * @return array<string, mixed>
	 */
	private function normalizeCombatReport(array $combatReport): array
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
