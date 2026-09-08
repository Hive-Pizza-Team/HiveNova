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
}
