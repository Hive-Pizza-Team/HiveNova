<?php

namespace HiveNova\Core;

use RuntimeException;

class ExpeditionChoiceService
{
	public const STANCE_CAUTIOUS = 'cautious';
	public const STANCE_BALANCED = 'balanced';
	public const STANCE_AGGRESSIVE = 'aggressive';

	public const BRANCH_RATE = 35;

	public const ERROR_FORBIDDEN = 'forbidden';
	public const ERROR_INVALID_BRANCH = 'invalid_branch';
	public const ERROR_ALREADY_RESOLVED = 'already_resolved';
	public const ERROR_NOT_FOUND = 'not_found';

	/** @var callable|null */
	private static $rng = null;

	public static function setRng(?callable $rng): void
	{
		self::$rng = $rng;
	}

	public static function roll(int $min, int $max): int
	{
		if (self::$rng !== null) {
			$rng = self::$rng;
			return (int) $rng($min, $max);
		}

		return mt_rand($min, $max);
	}

	public static function normalizeStance(?string $stance): string
	{
		$stance = strtolower(trim((string) $stance));
		if (in_array($stance, [self::STANCE_CAUTIOUS, self::STANCE_BALANCED, self::STANCE_AGGRESSIVE], true)) {
			return $stance;
		}

		return self::STANCE_BALANCED;
	}

	public static function isValidStance(string $stance): bool
	{
		return in_array($stance, [self::STANCE_CAUTIOUS, self::STANCE_BALANCED, self::STANCE_AGGRESSIVE], true);
	}

	public static function yieldMultiplier(string $stance): float
	{
		return match (self::normalizeStance($stance)) {
			self::STANCE_CAUTIOUS => 0.80,
			self::STANCE_AGGRESSIVE => 1.25,
			default => 1.00,
		};
	}

	public static function lossChance(string $stance): float
	{
		return match (self::normalizeStance($stance)) {
			self::STANCE_CAUTIOUS => 0.05,
			self::STANCE_AGGRESSIVE => 0.22,
			default => 0.12,
		};
	}

	public static function stanceFromMeta(mixed $meta): string
	{
		if (is_array($meta)) {
			return self::normalizeStance($meta['stance'] ?? null);
		}
		if (!is_string($meta) || $meta === '') {
			return self::STANCE_BALANCED;
		}
		$decoded = json_decode($meta, true);
		if (!is_array($decoded)) {
			return self::STANCE_BALANCED;
		}

		return self::normalizeStance($decoded['stance'] ?? null);
	}

	public static function encodeMeta(string $stance, array $extra = []): string
	{
		return json_encode(array_merge($extra, ['stance' => self::normalizeStance($stance)])) ?: '{"stance":"balanced"}';
	}

	public static function isBranchEligible(string $encounterKey): bool
	{
		return in_array($encounterKey, ['resource_find', 'ship_salvage', 'contact'], true);
	}

	public static function shouldCreateBranch(string $encounterKey, ?int $roll = null): bool
	{
		if (!self::isBranchEligible($encounterKey)) {
			return false;
		}
		$roll ??= self::roll(1, 100);

		return $roll <= self::BRANCH_RATE;
	}

	/**
	 * @param array<string, mixed> $baseReward metal/crystal/deuterium plus optional ships
	 * @return array<string, array<string, mixed>>
	 */
	public static function buildOptions(string $encounterKey, string $stance, array $baseReward): array
	{
		$stance = self::normalizeStance($stance);
		$yield = self::yieldMultiplier($stance);
		$loss = self::lossChance($stance);
		$metal = (int) ($baseReward['metal'] ?? 0);
		$crystal = (int) ($baseReward['crystal'] ?? 0);
		$deuterium = (int) ($baseReward['deuterium'] ?? 0);
		$ships = is_array($baseReward['ships'] ?? null) ? $baseReward['ships'] : [];

		$scale = static function (int $amount, float $factor) use ($yield): int {
			return (int) floor($amount * $factor * $yield);
		};

		$lossShips = [];
		if ($loss > 0 && $ships !== []) {
			$firstId = (int) array_key_first($ships);
			$have = (int) $ships[$firstId];
			$lossAmount = max(1, (int) floor($have * $loss));
			$lossShips[$firstId] = min($have, $lossAmount);
		}

		return [
			'cautious' => [
				'key' => 'cautious',
				'metal' => $scale($metal, 0.70),
				'crystal' => $scale($crystal, 0.70),
				'deuterium' => $scale($deuterium, 0.70),
				'ships' => self::scaleShips($ships, 0.70 * $yield),
				'loss_ships' => [],
			],
			'balanced' => [
				'key' => 'balanced',
				'metal' => $scale($metal, 1.00),
				'crystal' => $scale($crystal, 1.00),
				'deuterium' => $scale($deuterium, 1.00),
				'ships' => self::scaleShips($ships, 1.00 * $yield),
				'loss_ships' => [],
			],
			'aggressive' => [
				'key' => 'aggressive',
				'metal' => $scale($metal, 1.40),
				'crystal' => $scale($crystal, 1.40),
				'deuterium' => $scale($deuterium, 1.40),
				'ships' => self::scaleShips($ships, 1.40 * $yield),
				'loss_ships' => $lossShips,
			],
		];
	}

	/**
	 * @param array<int, int|float> $ships
	 * @return array<int, int>
	 */
	private static function scaleShips(array $ships, float $factor): array
	{
		$out = [];
		foreach ($ships as $id => $count) {
			$scaled = (int) floor((float) $count * $factor);
			if ($scaled > 0) {
				$out[(int) $id] = $scaled;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $fleet
	 * @param array<string, mixed> $baseReward
	 * @param array<int, int|float> $fleetArray
	 */
	public static function maybeDeferOutcome(array $fleet, string $encounterKey, array $baseReward, array $fleetArray): bool
	{
		if (!DirectiveHooks::enabled()) {
			return false;
		}
		if (!self::shouldCreateBranch($encounterKey)) {
			return false;
		}

		$stance = self::stanceFromMeta($fleet['fleet_meta'] ?? null);
		self::createPendingBranch(
			(int) $fleet['fleet_id'],
			(int) $fleet['fleet_owner'],
			(int) $fleet['fleet_start_id'],
			$encounterKey,
			$stance,
			$baseReward,
			$fleetArray
		);

		return true;
	}

	/**
	 * @param array<string, mixed> $baseReward
	 * @param array<int, int|float> $fleetArray
	 */
	public static function createPendingBranch(
		int $fleetId,
		int $userId,
		int $fleetStartId,
		string $encounterKey,
		string $stance,
		array $baseReward,
		array $fleetArray
	): void {
		$options = [
			'fleet_array' => $fleetArray,
			'planet_id' => $fleetStartId,
			'branches' => self::buildOptions($encounterKey, $stance, $baseReward),
		];
		Database::get()->insert(
			'INSERT INTO %%EXPEDITION_PENDING_CHOICES%%
			(fleet_id, user_id, fleet_start_id, encounter_key, options_json, stance, resolved_at, created_at)
			VALUES (:fleetId, :userId, :planetId, :encounter, :options, :stance, NULL, :created)',
			[
				':fleetId' => $fleetId,
				':userId' => $userId,
				':planetId' => $fleetStartId,
				':encounter' => $encounterKey,
				':options' => json_encode($options),
				':stance' => self::normalizeStance($stance),
				':created' => TIMESTAMP,
			]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function resolveBranch(int $fleetId, int $userId, string $branchKey): array
	{
		$db = Database::get();
		$db->beginTransaction();
		try {
			$row = $db->selectSingle(
				'SELECT fleet_id, user_id, fleet_start_id, encounter_key, options_json, stance, resolved_at, created_at
				FROM %%EXPEDITION_PENDING_CHOICES%%
				WHERE fleet_id = :fleetId FOR UPDATE',
				[':fleetId' => $fleetId]
			);
			if (!is_array($row)) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_NOT_FOUND);
			}
			if ((int) $row['user_id'] !== $userId) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_FORBIDDEN);
			}
			if (!empty($row['resolved_at'])) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_ALREADY_RESOLVED);
			}

			$payload = json_decode((string) $row['options_json'], true);
			$branches = is_array($payload) ? ($payload['branches'] ?? []) : [];
			if (!isset($branches[$branchKey]) || !is_array($branches[$branchKey])) {
				$db->rollback();
				throw new RuntimeException(self::ERROR_INVALID_BRANCH);
			}

			$choice = $branches[$branchKey];
			$planetId = (int) ($payload['planet_id'] ?? $row['fleet_start_id']);
			self::applyPlanetDeltas($db, $planetId, $choice);

			$db->update(
				'UPDATE %%EXPEDITION_PENDING_CHOICES%% SET resolved_at = :resolved WHERE fleet_id = :fleetId AND resolved_at IS NULL',
				[
					':resolved' => TIMESTAMP,
					':fleetId' => $fleetId,
				]
			);
			$db->commit();

			DirectiveProgressService::record($userId, 'expedition_branch', [
				'universe' => Universe::current(),
			]);

			return $choice;
		} catch (RuntimeException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}
	}

	public static function autoResolveExpired(int $maxAgeSeconds = 172800): int
	{
		$cutoff = TIMESTAMP - $maxAgeSeconds;
		$db = Database::get();
		$rows = $db->select(
			'SELECT fleet_id, user_id FROM %%EXPEDITION_PENDING_CHOICES%%
			WHERE resolved_at IS NULL AND created_at <= :cutoff',
			[':cutoff' => $cutoff]
		);
		$resolved = 0;
		foreach ($rows as $row) {
			try {
				self::resolveBranch((int) $row['fleet_id'], (int) $row['user_id'], 'balanced');
				$resolved++;
			} catch (\Throwable $e) {
				continue;
			}
		}

		return $resolved;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function pendingForUser(int $userId): array
	{
		$rows = Database::get()->select(
			'SELECT fleet_id, user_id, fleet_start_id, encounter_key, options_json, stance, resolved_at, created_at
			FROM %%EXPEDITION_PENDING_CHOICES%%
			WHERE user_id = :userId AND resolved_at IS NULL',
			[':userId' => $userId]
		);
		$out = [];
		foreach ($rows as $row) {
			$payload = json_decode((string) $row['options_json'], true);
			$out[] = [
				'fleet_id' => (int) $row['fleet_id'],
				'encounter_key' => (string) $row['encounter_key'],
				'stance' => (string) $row['stance'],
				'created_at' => (int) $row['created_at'],
				'branches' => is_array($payload) ? ($payload['branches'] ?? []) : [],
			];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $choice
	 */
	private static function applyPlanetDeltas(DatabaseInterface $db, int $planetId, array $choice): void
	{
		global $resource;

		$metal = (int) ($choice['metal'] ?? 0);
		$crystal = (int) ($choice['crystal'] ?? 0);
		$deuterium = (int) ($choice['deuterium'] ?? 0);

		$db->update(
			'UPDATE %%PLANETS%% SET
				metal = metal + :metal,
				crystal = crystal + :crystal,
				deuterium = deuterium + :deuterium
			WHERE id = :planetId',
			[
				':metal' => $metal,
				':crystal' => $crystal,
				':deuterium' => $deuterium,
				':planetId' => $planetId,
			]
		);

		$ships = is_array($choice['ships'] ?? null) ? $choice['ships'] : [];
		$losses = is_array($choice['loss_ships'] ?? null) ? $choice['loss_ships'] : [];
		$net = [];
		foreach ($ships as $id => $count) {
			$net[(int) $id] = (int) ($net[(int) $id] ?? 0) + (int) $count;
		}
		foreach ($losses as $id => $count) {
			$net[(int) $id] = (int) ($net[(int) $id] ?? 0) - (int) $count;
		}
		foreach ($net as $id => $delta) {
			$col = $resource[$id] ?? null;
			if (!is_string($col) || $col === '' || $delta === 0) {
				continue;
			}
			if ($delta > 0) {
				$db->update(
					'UPDATE %%PLANETS%% SET ' . $col . ' = ' . $col . ' + :delta WHERE id = :planetId',
					[':delta' => $delta, ':planetId' => $planetId]
				);
			} else {
				$db->update(
					'UPDATE %%PLANETS%% SET ' . $col . ' = GREATEST(0, ' . $col . ' - :delta) WHERE id = :planetId',
					[':delta' => abs($delta), ':planetId' => $planetId]
				);
			}
		}

		DirectiveService::addResourcesToSessionPlanet($planetId, [
			'metal' => $metal,
			'crystal' => $crystal,
			'deuterium' => $deuterium,
		]);
		self::addShipsToSessionPlanet($planetId, $net);
	}

	/**
	 * @param array<int, int> $net
	 */
	private static function addShipsToSessionPlanet(int $planetId, array $net): void
	{
		global $resource;

		if (!isset($GLOBALS['PLANET']) || !is_array($GLOBALS['PLANET'])) {
			return;
		}
		if ((int) ($GLOBALS['PLANET']['id'] ?? 0) !== $planetId) {
			return;
		}
		foreach ($net as $id => $delta) {
			$col = $resource[$id] ?? null;
			if (!is_string($col) || $col === '' || $delta === 0) {
				continue;
			}
			$GLOBALS['PLANET'][$col] = max(0, (int) ($GLOBALS['PLANET'][$col] ?? 0) + $delta);
		}
	}
}
