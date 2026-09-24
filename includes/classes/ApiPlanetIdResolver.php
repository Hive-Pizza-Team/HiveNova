<?php

namespace HiveNova\Core;

class ApiPlanetIdResolver
{
	/**
	 * @return array{ok: true, planetId: ?int}|array{ok: false, status: int, error: string}
	 */
	public static function fromRequest(?string $raw): array
	{
		if ($raw === null) {
			return ['ok' => true, 'planetId' => null];
		}

		$trimmed = trim($raw);
		if ($trimmed === '' || preg_match('/^-?\d+$/', $trimmed) !== 1) {
			return ['ok' => false, 'status' => 422, 'error' => 'planet'];
		}

		$id = (int) $trimmed;
		if ($id <= 0) {
			return ['ok' => false, 'status' => 422, 'error' => 'planet'];
		}

		return ['ok' => true, 'planetId' => $id];
	}

	/**
	 * @return array{ok: true}|array{ok: false, status: int, error: string}
	 */
	public static function requireOwned(?int $requestedPlanetId, ?int $ownedPlanetId): array
	{
		if ($requestedPlanetId === null) {
			return ['ok' => true];
		}
		if ($ownedPlanetId === null || $ownedPlanetId !== $requestedPlanetId) {
			return ['ok' => false, 'status' => 403, 'error' => 'planet'];
		}

		return ['ok' => true];
	}
}
