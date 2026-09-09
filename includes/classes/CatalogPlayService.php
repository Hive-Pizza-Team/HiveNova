<?php

namespace HiveNova\Core;

class CatalogPlayService
{
	/** @var list<int> */
	public const PROD_ELEMENT_IDS = [1, 2, 3, 4, 12, 212];

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int, array<int, int>> $requirements
	 * @param array<string, list<int>> $reslist
	 * @param array<int|string, string> $resource
	 * @return list<array<string, mixed>>
	 */
	public static function techtree(array $user, array $planet, mixed $lng, array $requirements, array $reslist, array $resource): array
	{
		$ids = array_merge(
			$reslist['build'] ?? [],
			$reslist['tech'] ?? [],
			$reslist['fleet'] ?? [],
			$reslist['defense'] ?? [],
			$reslist['missile'] ?? [],
			$reslist['officier'] ?? []
		);
		$out = [];
		foreach ($ids as $elementId) {
			$elementId = (int) $elementId;
			if (!isset($requirements[$elementId]) || !is_array($requirements[$elementId])) {
				continue;
			}
			$reqs = [];
			foreach ($requirements[$elementId] as $needId => $needLevel) {
				$needId = (int) $needId;
				$col = $resource[$needId] ?? '';
				$own = 0;
				if ($col !== '') {
					$own = (int) ($planet[$col] ?? $user[$col] ?? 0);
				}
				$reqs[] = [
					'id' => $needId,
					'name' => EconomyPlayService::techName($lng, $needId),
					'need' => (int) $needLevel,
					'own' => $own,
					'ok' => $own >= (int) $needLevel,
				];
			}
			$out[] = [
				'id' => $elementId,
				'name' => EconomyPlayService::techName($lng, $elementId),
				'requirements' => $reqs,
			];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<string, list<int>> $reslist
	 * @param array<int|string, string> $resource
	 * @return list<array<string, mixed>>
	 */
	public static function officers(array $user, array $planet, mixed $lng, array $reslist, array $resource): array
	{
		$out = [];
		foreach ($reslist['officier'] ?? [] as $elementId) {
			$elementId = (int) $elementId;
			$col = $resource[$elementId] ?? '';
			$out[] = [
				'id' => $elementId,
				'name' => EconomyPlayService::techName($lng, $elementId),
				'level' => $col !== '' ? (int) ($user[$col] ?? 0) : 0,
			];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $planet
	 * @param array<int|string, string> $resource
	 * @return list<array<string, mixed>>
	 */
	public static function productionSliders(array $planet, mixed $lng, array $resource): array
	{
		$out = [];
		foreach (self::PROD_ELEMENT_IDS as $elementId) {
			$col = $resource[$elementId] ?? '';
			if ($col === '' || !array_key_exists($col.'_porcent', $planet)) {
				continue;
			}
			$out[] = [
				'id' => $elementId,
				'name' => EconomyPlayService::techName($lng, $elementId),
				'factor' => self::clampFactor((int) $planet[$col.'_porcent']),
				'level' => (int) ($planet[$col] ?? 0),
			];
		}

		return $out;
	}

	public static function clampFactor(int $value): int
	{
		return max(0, min(10, $value));
	}

	/**
	 * @param array<int|string, mixed> $posted
	 * @param array<int|string, string> $resource
	 * @return array<string, int>
	 */
	public static function productionUpdates(array $posted, array $resource): array
	{
		$updates = [];
		foreach ($posted as $id => $value) {
			$id = (int) $id;
			if (!in_array($id, self::PROD_ELEMENT_IDS, true)) {
				continue;
			}
			$col = $resource[$id] ?? '';
			if ($col === '') {
				continue;
			}
			$updates[$col.'_porcent'] = self::clampFactor((int) $value);
		}

		return $updates;
	}

	/**
	 * @return array<int, array<int, float>>
	 */
	public static function traderRates(): array
	{
		return [
			901 => [901 => 1.0, 902 => 2.0, 903 => 4.0],
			902 => [901 => 0.5, 902 => 1.0, 903 => 2.0],
			903 => [901 => 0.25, 902 => 0.5, 903 => 1.0],
		];
	}

	/**
	 * @param array<string, mixed> $planet
	 * @param array<int|string, string> $resource
	 * @return array<string, mixed>
	 */
	public static function missiles(array $planet, array $resource): array
	{
		$ipmCol = $resource[503] ?? 'interplanetary_misil';
		$abmCol = $resource[502] ?? 'interceptor_misil';

		return [
			'interceptor' => (int) ($planet[$abmCol] ?? $planet['interceptor_misil'] ?? 0),
			'interplanetary' => (int) ($planet[$ipmCol] ?? $planet['interplanetary_misil'] ?? 0),
			'silo' => (int) ($planet[$resource[44] ?? 'silo'] ?? $planet['silo'] ?? 0),
		];
	}

	public static function phalanxRange(int $level): int
	{
		if ($level <= 0) {
			return 0;
		}

		return $level === 1 ? 1 : ($level * $level) - 1;
	}

	/**
	 * @param array<string, mixed> $planet
	 * @param array<int|string, string> $resource
	 * @return array<string, mixed>
	 */
	public static function phalanx(array $planet, array $resource): array
	{
		$level = (int) ($planet[$resource[42] ?? 'sensor_phalanx'] ?? $planet['sensor_phalanx'] ?? 0);
		$range = self::phalanxRange($level);
		$system = (int) ($planet['system'] ?? 0);

		return [
			'level' => $level,
			'range' => $range,
			'galaxy' => (int) ($planet['galaxy'] ?? 0),
			'systemMin' => max(1, $system - $range),
			'systemMax' => $system + $range,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function mapBuddy(array $row, int $viewerId): array
	{
		return [
			'id' => (int) ($row['id'] ?? 0),
			'buddyId' => (int) ($row['buddyid'] ?? 0),
			'username' => (string) ($row['username'] ?? ''),
			'coords' => [
				'galaxy' => (int) ($row['galaxy'] ?? 0),
				'system' => (int) ($row['system'] ?? 0),
				'planet' => (int) ($row['planet'] ?? 0),
			],
			'alliance' => (string) ($row['ally_name'] ?? ''),
			'pending' => trim((string) ($row['text'] ?? '')) !== '',
			'self' => (int) ($row['id'] ?? 0) === $viewerId,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function mapNote(array $row): array
	{
		return [
			'id' => (int) ($row['id'] ?? 0),
			'title' => (string) ($row['title'] ?? ''),
			'text' => (string) ($row['text'] ?? ''),
			'priority' => (int) ($row['priority'] ?? 0),
			'time' => (int) ($row['time'] ?? 0),
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function mapStat(array $row): array
	{
		return [
			'rank' => (int) ($row['total_rank'] ?? $row['rank'] ?? 0),
			'points' => (int) ($row['total_points'] ?? $row['points'] ?? 0),
			'username' => (string) ($row['username'] ?? ''),
			'userId' => (int) ($row['id'] ?? $row['id_owner'] ?? 0),
			'alliance' => (string) ($row['ally_name'] ?? ''),
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>
	 */
	public static function allianceSummary(array $user): array
	{
		$id = (int) ($user['ally_id'] ?? 0);

		return [
			'member' => $id > 0,
			'id' => $id,
			'name' => (string) ($user['ally_name'] ?? ''),
		];
	}

	/**
	 * @param list<array<string, mixed>> $planets
	 * @return list<array<string, mixed>>
	 */
	public static function empireBodies(array $planets, ?object $config = null): array
	{
		$out = [];
		foreach ($planets as $row) {
			$type = (int) ($row['planet_type'] ?? 1);
			$planetIncome = $type === 1 && $config !== null;
			$mult = $config !== null ? (float) ($config->resource_multiplier ?? 1) : 1.0;
			$metalBasic = ($planetIncome && isset($config->metal_basic_income)) ? (float) $config->metal_basic_income * $mult : 0.0;
			$crystalBasic = ($planetIncome && isset($config->crystal_basic_income)) ? (float) $config->crystal_basic_income * $mult : 0.0;
			$deutBasic = ($planetIncome && isset($config->deuterium_basic_income)) ? (float) $config->deuterium_basic_income * $mult : 0.0;
			$out[] = [
				'id' => (int) ($row['id'] ?? 0),
				'name' => (string) ($row['name'] ?? ''),
				'image' => PlanetImageUtil::hiveThemeImage((string) ($row['image'] ?? '')),
				'galaxy' => (int) ($row['galaxy'] ?? 0),
				'system' => (int) ($row['system'] ?? 0),
				'planet' => (int) ($row['planet'] ?? 0),
				'type' => $type,
				'metal' => (int) ($row['metal'] ?? 0),
				'crystal' => (int) ($row['crystal'] ?? 0),
				'deuterium' => (int) ($row['deuterium'] ?? 0),
				'energy' => (int) ($row['energy'] ?? 0),
				'energyUsed' => (int) ($row['energy_used'] ?? 0),
				'metalPerHour' => (int) (($row['metal_perhour'] ?? 0) + $metalBasic),
				'crystalPerHour' => (int) (($row['crystal_perhour'] ?? 0) + $crystalBasic),
				'deuteriumPerHour' => (int) (($row['deuterium_perhour'] ?? 0) + $deutBasic),
				'fields' => (int) ($row['field_current'] ?? 0),
				'fieldMax' => (int) ($row['field_max'] ?? 0),
			];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function mapAcs(array $row): array
	{
		return [
			'id' => (int) ($row['id'] ?? 0),
			'name' => (string) ($row['name'] ?? ''),
		];
	}

	public static function sanitizeKind(string $raw): string
	{
		$kind = preg_replace('/[^a-z]/', '', strtolower($raw)) ?? '';

		return $kind === '' ? 'techtree' : $kind;
	}

	/**
	 * @param array<string, int> $updates
	 * @param array<string, mixed> $planet
	 * @return array{set: list<string>, params: array<string, int>}
	 */
	public static function applyProductionUpdates(array &$planet, array $updates): array
	{
		$set = [];
		$params = [];
		foreach ($updates as $col => $value) {
			$set[] = $col.' = :'.$col;
			$params[':'.$col] = $value;
			$planet[$col] = $value;
		}

		return ['set' => $set, 'params' => $params];
	}

	/**
	 * @return array<string, string>
	 */
	public static function chromeLabels(mixed $lng): array
	{
		$keys = [
			'lm_overview', 'lm_buildings', 'lm_shipshard', 'lm_defenses', 'lm_research',
			'lm_fleet', 'lm_galaxy', 'lm_messages', 'lm_alliance', 'lm_options', 'lm_logout',
			'lm_administration', 'hn_classic_ui', 'hn_try_new_ui',
			'lm_officiers', 'lm_trader', 'lm_technology', 'lm_resources', 'lm_empire',
			'lm_buddylist', 'lm_notes', 'lm_statistics', 'lm_viz',
			'ov_newname_done', 'ov_newname_specialchar',
		];
		$out = [];
		foreach ($keys as $key) {
			if (is_array($lng) || $lng instanceof \ArrayAccess) {
				$out[$key] = (string) ($lng[$key] ?? $key);
			} else {
				$out[$key] = $key;
			}
		}

		return $out;
	}
}
