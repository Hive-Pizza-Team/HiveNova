<?php

namespace HiveNova\Core;

class OverviewPlanetActionService
{
	public const ERROR_SPECIAL_CHAR = 'special_char';
	public const ERROR_EMPTY_NAME = 'empty_name';
	public const ERROR_FLEETS = 'fleets';
	public const ERROR_HOME = 'home';
	public const ERROR_WRONG_NAME = 'wrong_name';
	public const ERROR_NOT_POSSIBLE = 'not_possible';

	/**
	 * @param array<string, mixed> $planet
	 * @return array{ok: bool, error?: string}
	 */
	public function validateRename(string $newName, array $planet): array
	{
		if ($newName === '') {
			return ['ok' => false, 'error' => self::ERROR_EMPTY_NAME];
		}
		if (!PlayerUtil::isNameValid($newName)) {
			return ['ok' => false, 'error' => self::ERROR_SPECIAL_CHAR];
		}

		return ['ok' => true];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @return array{ok: bool, error?: string}
	 */
	public function validateAbandon(
		string $confirmName,
		array $user,
		array $planet,
		int $fleetCount,
		int $coloniesIncludingThis,
	): array {
		if ($fleetCount > 0) {
			return ['ok' => false, 'error' => self::ERROR_FLEETS];
		}
		if ((int) ($user['id_planet'] ?? 0) === (int) ($planet['id'] ?? 0) && $coloniesIncludingThis <= 1) {
			return ['ok' => false, 'error' => self::ERROR_HOME];
		}
		if ($confirmName !== (string) ($planet['name'] ?? '')) {
			return ['ok' => false, 'error' => self::ERROR_WRONG_NAME];
		}

		return ['ok' => true];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @return array<string, mixed>
	 */
	public function snapshot(array $user, array $planet, mixed $lng = [], array $planets = []): array
	{
		$buildQueue = EconomyPlayService::buildingQueue($planet)['queue'];
		$techQueue = EconomyPlayService::researchQueue($user)['queue'];
		$colonies = [];
		foreach ($planets as $row) {
			$colonies[] = self::colonyCard($row, $lng);
		}

		$moon = null;
		foreach ($colonies as $body) {
			if (
				(int) $body['type'] === 3
				&& (int) $body['galaxy'] === (int) $planet['galaxy']
				&& (int) $body['system'] === (int) $planet['system']
				&& (int) $body['planet'] === (int) $planet['planet']
				&& (int) $body['id'] !== (int) $planet['id']
			) {
				$moon = $body;
				break;
			}
		}

		return [
			'planet' => [
				'id' => (int) $planet['id'],
				'name' => (string) $planet['name'],
				'galaxy' => (int) $planet['galaxy'],
				'system' => (int) $planet['system'],
				'planet' => (int) $planet['planet'],
				'type' => (int) ($planet['planet_type'] ?? 1),
				'image' => PlanetImageUtil::hiveThemeImage(
					(string) ($planet['image'] ?? ''),
					(int) ($planet['temp_min'] ?? 0),
					(int) ($planet['temp_max'] ?? 0),
				),
				'diameter' => (int) ($planet['diameter'] ?? 0),
				'fieldCurrent' => (int) ($planet['field_current'] ?? 0),
				'fieldMax' => (int) CalculateMaxPlanetFields($planet),
				'tempMin' => (int) ($planet['temp_min'] ?? 0),
				'tempMax' => (int) ($planet['temp_max'] ?? 0),
			],
			'username' => (string) $user['username'],
			'unreadMessages' => (int) ($user['messages'] ?? 0),
			'queues' => [
				'building' => self::namedQueueItem($buildQueue[0] ?? null, $lng),
				'research' => self::namedQueueItem($techQueue[0] ?? null, $lng),
				'shipyard' => self::hangarHead($planet, $lng),
			],
			'colonies' => $colonies,
			'moon' => $moon,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function colonyCard(array $row, mixed $lng): array
	{
		$build = EconomyPlayService::buildingQueue($row)['queue'][0] ?? null;

		return [
			'id' => (int) ($row['id'] ?? 0),
			'name' => (string) ($row['name'] ?? ''),
			'galaxy' => (int) ($row['galaxy'] ?? 0),
			'system' => (int) ($row['system'] ?? 0),
			'planet' => (int) ($row['planet'] ?? 0),
			'type' => (int) ($row['planet_type'] ?? 1),
			'image' => PlanetImageUtil::hiveThemeImage((string) ($row['image'] ?? '')),
			'building' => is_array($build)
				? (string) ($build['name'] ?? EconomyPlayService::techName($lng, (int) ($build['elementId'] ?? 0)))
				: '',
		];
	}

	/**
	 * @param array<string, mixed> $planet
	 * @return array<string, mixed>|null
	 */
	public static function hangarHead(array $planet, mixed $lng): ?array
	{
		$raw = safe_unserialize($planet['b_hangar_id'] ?? '');
		if (!is_array($raw) || $raw === []) {
			return null;
		}
		$elementId = (int) ($raw[0][0] ?? 0);
		$count = (int) ($raw[0][1] ?? 0);
		if ($elementId <= 0 || $count <= 0) {
			return null;
		}

		return [
			'elementId' => $elementId,
			'name' => EconomyPlayService::techName($lng, $elementId),
			'count' => $count,
			'resttime' => max((int) ($planet['b_hangar'] ?? 0), 0),
		];
	}

	/**
	 * @param array<string, mixed>|null $item
	 * @return array<string, mixed>|null
	 */
	public static function namedQueueItem(?array $item, mixed $lng): ?array
	{
		if ($item === null) {
			return null;
		}
		$item['name'] = EconomyPlayService::techName($lng, (int) ($item['elementId'] ?? 0));

		return $item;
	}
}
