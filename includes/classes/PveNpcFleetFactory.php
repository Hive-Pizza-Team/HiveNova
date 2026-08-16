<?php

namespace HiveNova\Core;

class PveNpcFleetFactory
{
	/**
	 * Fixed templates by tier (1 small, 2 medium, 3 large) and family.
	 * Never copies the player's fleet.
	 *
	 * @return array<int, int> shipId => count
	 */
	public static function template(string $family, int $tier, bool $accusedBump = false): array
	{
		$tier = max(1, min(3, $tier));
		$family = strtolower($family);

		$templates = [
			'pirate' => [
				1 => [204 => 8, 202 => 2],
				2 => [204 => 12, 206 => 4],
				3 => [206 => 6, 207 => 3],
			],
			'alien' => [
				1 => [205 => 6, 203 => 1],
				2 => [205 => 10, 215 => 3],
				3 => [215 => 5, 213 => 2],
			],
			'salvager' => [
				1 => [209 => 4, 204 => 4],
				2 => [209 => 6, 206 => 3],
				3 => [219 => 2, 207 => 2],
			],
		];

		if (!isset($templates[$family])) {
			$family = 'pirate';
		}

		$ships = $templates[$family][$tier];
		if ($accusedBump) {
			foreach ($ships as $id => $count) {
				$ships[$id] = (int) ceil($count * PVE_ACCUSED_SHIP_FACTOR);
			}
		}

		return $ships;
	}

	public static function familyFromSeed(int $seed): string
	{
		$roll = abs($seed) % 100;
		if ($roll < 50) {
			return 'pirate';
		}
		if ($roll < 80) {
			return 'alien';
		}

		return 'salvager';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function syntheticPlayer(string $username = 'Pirates', int $tech = 5): array
	{
		return [
			'id' => 0,
			'username' => $username,
			'military_tech' => $tech,
			'defence_tech' => $tech,
			'shield_tech' => $tech,
			'dmg_cd' => 0,
			'rpg_amiral' => 0,
			'lang' => 'en',
			'factor' => [
				'Attack' => 0,
				'Defensive' => 0,
				'Shield' => 0,
			],
		];
	}

	public static function displayName(string $family): string
	{
		return match ($family) {
			'alien' => 'Aliens',
			'salvager' => 'Salvagers',
			default => 'Pirates',
		};
	}
}
