<?php

namespace HiveNova\Core;

class ApiBootstrapService
{
	/**
	 * Allowlisted chrome payload. Never pass raw $USER / $PLANET.
	 *
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param list<array<string, mixed>> $planets
	 * @param array<int, array<string, mixed>> $resourceTable
	 * @param list<int> $cronjobIds
	 * @param list<string> $modules
	 * @return array<string, mixed>
	 */
	public function build(
		array $user,
		array $planet,
		array $planets,
		array $resourceTable,
		int $attackAlertCount,
		array $cronjobIds,
		array $modules,
		string $csrf,
		int $serverTime,
		string $rev,
		bool $gameClosed,
		bool $seasonBlocked,
		string $gameName = '',
	): array {
		$planetList = [];
		foreach ($planets as $row) {
			$planetList[] = [
				'id' => (int) ($row['id'] ?? 0),
				'name' => (string) ($row['name'] ?? ''),
				'galaxy' => (int) ($row['galaxy'] ?? 0),
				'system' => (int) ($row['system'] ?? 0),
				'planet' => (int) ($row['planet'] ?? 0),
				'type' => (int) ($row['planet_type'] ?? 1),
				'image' => PlanetImageUtil::hiveThemeImage(
					(string) ($row['image'] ?? ''),
					isset($row['temp_min']) ? (int) $row['temp_min'] : null,
					isset($row['temp_max']) ? (int) $row['temp_max'] : null,
				),
			];
		}

		return [
			'user' => [
				'id' => (int) ($user['id'] ?? 0),
				'username' => (string) ($user['username'] ?? ''),
				'authlevel' => (int) ($user['authlevel'] ?? 0),
				'isStaff' => AuthLevel::isStaff((int) ($user['authlevel'] ?? 0)),
				'vacation' => (int) ($user['urlaubs_modus'] ?? 0) === 1,
				'locale' => (string) ($user['lang'] ?? 'en'),
				'timezone' => (string) ($user['timezone'] ?? 'UTC'),
				'unreadMessages' => (int) ($user['messages'] ?? 0),
			],
			'planet' => [
				'id' => (int) ($planet['id'] ?? 0),
				'name' => (string) ($planet['name'] ?? ''),
				'galaxy' => (int) ($planet['galaxy'] ?? 0),
				'system' => (int) ($planet['system'] ?? 0),
				'planet' => (int) ($planet['planet'] ?? 0),
				'type' => (int) ($planet['planet_type'] ?? 1),
				'image' => PlanetImageUtil::hiveThemeImage(
					(string) ($planet['image'] ?? ''),
					isset($planet['temp_min']) ? (int) $planet['temp_min'] : null,
					isset($planet['temp_max']) ? (int) $planet['temp_max'] : null,
				),
			],
			'planets' => $planetList,
			'resources' => $resourceTable,
			'attackAlertCount' => $attackAlertCount,
			'cronjobs' => $cronjobIds,
			'modules' => $modules,
			'csrf' => $csrf,
			'gameClosed' => $gameClosed,
			'seasonBlocked' => $seasonBlocked,
			'rev' => $rev,
			'serverTime' => $serverTime,
			'gameName' => $gameName,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int|string, string> $resource
	 * @param array<string, mixed> $reslist
	 * @return array<int, array<string, mixed>>
	 */
	public static function resourceTable(array $user, array $planet, array $resource, array $reslist, object $config): array
	{
		$table = [];
		$resourceSpeed = $config->resource_multiplier;
		if (isset($reslist['resstype'][1]) && is_array($reslist['resstype'][1])) {
			foreach ($reslist['resstype'][1] as $resourceID) {
				$col = $resource[$resourceID];
				$production = (float) ($planet[$col.'_perhour'] ?? 0);
				if ((int) ($user['urlaubs_modus'] ?? 0) !== 1 && (int) ($planet['planet_type'] ?? 1) === 1) {
					$incomeKey = $col.'_basic_income';
					$production += (float) ($config->{$incomeKey} ?? 0) * $resourceSpeed;
				}
				$table[$resourceID] = [
					'name' => $col,
					'current' => (float) ($planet[$col] ?? 0),
					'max' => (float) ($planet[$col.'_max'] ?? 0),
					'production' => $production,
				];
			}
		}
		if (isset($reslist['resstype'][2]) && is_array($reslist['resstype'][2])) {
			foreach ($reslist['resstype'][2] as $resourceID) {
				$col = $resource[$resourceID];
				$table[$resourceID] = [
					'name' => $col,
					'current' => (float) ($planet[$col] ?? 0),
					'max' => (float) ($planet[$col.'_max'] ?? 0),
					'production' => (float) ($planet[$col.'_used'] ?? 0),
				];
			}
		}
		if (isset($reslist['resstype'][3]) && is_array($reslist['resstype'][3])) {
			foreach ($reslist['resstype'][3] as $resourceID) {
				$col = $resource[$resourceID] ?? '';
				if ($col === '') {
					continue;
				}
				$table[$resourceID] = [
					'name' => $col,
					'current' => (float) ($user[$col] ?? 0),
					'max' => 0.0,
					'production' => 0.0,
				];
			}
		}

		return $table;
	}
}
