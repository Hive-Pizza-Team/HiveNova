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
				'image' => (string) ($row['image'] ?? ''),
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
				'image' => (string) ($planet['image'] ?? ''),
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
		];
	}
}
