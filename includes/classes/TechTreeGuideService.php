<?php

namespace HiveNova\Core;

/**
 * New-player Tech Tree helpers: Start-here IDs, next Day-0 unlocks,
 * and #611-compatible deep links into Buildings / Research / Shipyard.
 *
 * Hashes match ElementRequirementService (`#g21`, `#t115`, `#s202`) so
 * both PRs can merge without sharing that class.
 */
class TechTreeGuideService
{
	public const SOLAR_PLANT = 4;
	public const ORE_EXTRACTOR = 1;
	public const SILICON_REFINERY = 2;
	public const GIGAFACTORY = 14;
	public const SHIPYARD = 21;
	public const RESEARCH_LAB = 31;
	public const ENERGY_TECH = 113;
	public const COMBUSTION = 115;
	public const SMALL_CARGO = 202;
	public const LIGHT_FIGHTER = 204;

	public const BUILDING_MIN = 1;
	public const BUILDING_MAX = 99;
	public const RESEARCH_MIN = 101;
	public const RESEARCH_MAX = 199;
	public const SHIP_MIN = 201;
	public const SHIP_MAX = 399;
	public const DEFENSE_MIN = 401;
	public const DEFENSE_MAX = 599;
	public const OFFICIER_MIN = 601;
	public const OFFICIER_MAX = 699;

	public const FRAGMENT_BUILDING = 'g';
	public const FRAGMENT_RESEARCH = 't';
	public const FRAGMENT_SHIPYARD = 's';
	public const FRAGMENT_OFFICIER = 'o';

	/** Typical Day-0 path: Energy → Shipyard → Combustion → Small Cargo. */
	public const NEXT_UNLOCK_PATH = [
		self::ENERGY_TECH,
		self::SHIPYARD,
		self::COMBUSTION,
		self::SMALL_CARGO,
	];

	/** Empire basics + first ships for the Start-here filter. */
	public const START_HERE = [
		self::SOLAR_PLANT,
		self::ORE_EXTRACTOR,
		self::SILICON_REFINERY,
		self::RESEARCH_LAB,
		self::GIGAFACTORY,
		self::SHIPYARD,
		self::ENERGY_TECH,
		self::COMBUSTION,
		self::SMALL_CARGO,
		self::LIGHT_FIGHTER,
	];

	/** @var array<int, array<int, int>> */
	private array $requirements;

	/**
	 * @param array<int|string, array<int|string, int|string>> $requirements
	 */
	public function __construct(array $requirements)
	{
		$normalized = [];
		foreach ($requirements as $elementId => $reqs) {
			if (!is_array($reqs)) {
				continue;
			}
			$row = [];
			foreach ($reqs as $requireId => $level) {
				$row[(int) $requireId] = (int) $level;
			}
			$normalized[(int) $elementId] = $row;
		}
		$this->requirements = $normalized;
	}

	/**
	 * @return list<int>
	 */
	public function startHereIds(): array
	{
		return self::START_HERE;
	}

	/**
	 * @return list<int>
	 */
	public function nextUnlockPath(): array
	{
		return self::NEXT_UNLOCK_PATH;
	}

	public function hrefForElement(int $elementId): ?string
	{
		if ($elementId >= self::BUILDING_MIN && $elementId <= self::BUILDING_MAX) {
			return 'game.php?page=buildings#' . self::FRAGMENT_BUILDING . $elementId;
		}
		if ($elementId >= self::RESEARCH_MIN && $elementId <= self::RESEARCH_MAX) {
			return 'game.php?page=research#' . self::FRAGMENT_RESEARCH . $elementId;
		}
		if ($elementId >= self::SHIP_MIN && $elementId <= self::SHIP_MAX) {
			return 'game.php?page=shipyard&mode=' . ShipyardPageModeService::MODE_FLEET
				. '#' . self::FRAGMENT_SHIPYARD . $elementId;
		}
		if ($elementId >= self::DEFENSE_MIN && $elementId <= self::DEFENSE_MAX) {
			return 'game.php?page=shipyard&mode=' . ShipyardPageModeService::MODE_DEFENSE
				. '#' . self::FRAGMENT_SHIPYARD . $elementId;
		}
		if ($elementId >= self::OFFICIER_MIN && $elementId <= self::OFFICIER_MAX) {
			return 'game.php?page=officier#' . self::FRAGMENT_OFFICIER . $elementId;
		}

		return null;
	}

	/**
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 */
	public function ownedLevel(int $elementId, array $user, array $planet, array $resourceColumns): int
	{
		$column = $resourceColumns[$elementId] ?? $resourceColumns[(string) $elementId] ?? null;
		if (!is_string($column) || $column === '') {
			return 0;
		}

		if (array_key_exists($column, $planet)) {
			return (int) $planet[$column];
		}
		if (array_key_exists($column, $user)) {
			return (int) $user[$column];
		}

		return 0;
	}

	/**
	 * Soonest incomplete Day-0 unlocks (typically 2–3 cards).
	 *
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 * @param array<int|string, mixed> $techNames
	 * @param array{need?:string,ready?:string,sep?:string} $copy
	 * @return list<array{
	 *   id:int,
	 *   name:string,
	 *   missing:string,
	 *   progress:string,
	 *   ready:bool,
	 *   href:string,
	 *   missingReqs:list<array{id:int,name:string,own:int,need:int}>
	 * }>
	 */
	public function nextUnlocks(
		array $user,
		array $planet,
		array $resourceColumns,
		array $techNames,
		array $copy = [],
		int $limit = 3
	): array {
		$limit = max(1, min(6, $limit));
		$needTpl = $copy['need'] ?? 'Need %s %d (%d/%d)';
		$readyTpl = $copy['ready'] ?? 'Requirements met — go start it.';
		$sep = $copy['sep'] ?? '; ';

		$candidates = [];
		foreach (self::NEXT_UNLOCK_PATH as $index => $elementId) {
			if ($this->isComplete($elementId, $user, $planet, $resourceColumns)) {
				continue;
			}
			$candidates[] = [
				'id'    => $elementId,
				'gap'   => $this->remainingGap($elementId, $user, $planet, $resourceColumns),
				'index' => $index,
			];
		}

		usort($candidates, static function (array $left, array $right): int {
			$gapCmp = $left['gap'] <=> $right['gap'];
			if ($gapCmp !== 0) {
				return $gapCmp;
			}

			return $left['index'] <=> $right['index'];
		});

		$cards = [];
		foreach (array_slice($candidates, 0, $limit) as $candidate) {
			$cards[] = $this->buildCard(
				(int) $candidate['id'],
				$user,
				$planet,
				$resourceColumns,
				$techNames,
				$needTpl,
				$readyTpl,
				$sep
			);
		}

		return $cards;
	}

	/**
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 */
	public function isComplete(int $elementId, array $user, array $planet, array $resourceColumns): bool
	{
		if ($elementId >= self::SHIP_MIN && $elementId <= self::SHIP_MAX) {
			return $this->requirementsMet($elementId, $user, $planet, $resourceColumns);
		}

		return $this->ownedLevel($elementId, $user, $planet, $resourceColumns) >= 1;
	}

	/**
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 */
	public function remainingGap(int $elementId, array $user, array $planet, array $resourceColumns): int
	{
		$gap = 0;
		foreach ($this->requirements[$elementId] ?? [] as $requireId => $need) {
			$own = $this->ownedLevel((int) $requireId, $user, $planet, $resourceColumns);
			if ($own < $need) {
				$gap += $need - $own;
			}
		}

		return $gap;
	}

	/**
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 */
	public function requirementsMet(int $elementId, array $user, array $planet, array $resourceColumns): bool
	{
		foreach ($this->requirements[$elementId] ?? [] as $requireId => $need) {
			if ($this->ownedLevel((int) $requireId, $user, $planet, $resourceColumns) < $need) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 * @param array<int|string, mixed> $techNames
	 * @return array{
	 *   id:int,
	 *   name:string,
	 *   missing:string,
	 *   progress:string,
	 *   ready:bool,
	 *   href:string,
	 *   missingReqs:list<array{id:int,name:string,own:int,need:int}>
	 * }
	 */
	private function buildCard(
		int $elementId,
		array $user,
		array $planet,
		array $resourceColumns,
		array $techNames,
		string $needTpl,
		string $readyTpl,
		string $sep
	): array {
		$missingReqs = [];
		$firstUnmetId = null;
		foreach ($this->requirements[$elementId] ?? [] as $requireId => $need) {
			$requireId = (int) $requireId;
			$own = $this->ownedLevel($requireId, $user, $planet, $resourceColumns);
			if ($own >= $need) {
				continue;
			}
			if ($firstUnmetId === null) {
				$firstUnmetId = $requireId;
			}
			$missingReqs[] = [
				'id'   => $requireId,
				'name' => $this->elementName($requireId, $techNames),
				'own'  => $own,
				'need' => $need,
			];
		}

		$ready = $missingReqs === [];
		$parts = [];
		foreach ($missingReqs as $row) {
			$parts[] = sprintf($needTpl, $row['name'], $row['need'], $row['own'], $row['need']);
		}

		$progress = '';
		if ($missingReqs !== []) {
			$first = $missingReqs[0];
			$progress = $first['own'] . '/' . $first['need'];
		}

		$hrefTarget = $firstUnmetId ?? $elementId;
		$href = $this->hrefForElement($hrefTarget) ?? 'game.php?page=techtree';

		return [
			'id'          => $elementId,
			'name'        => $this->elementName($elementId, $techNames),
			'missing'     => $ready ? $readyTpl : implode($sep, $parts),
			'progress'    => $progress,
			'ready'       => $ready,
			'href'        => $href,
			'missingReqs' => $missingReqs,
		];
	}

	/**
	 * @param array<int|string, mixed> $techNames
	 */
	private function elementName(int $elementId, array $techNames): string
	{
		$name = $techNames[$elementId] ?? $techNames[(string) $elementId] ?? null;

		return is_string($name) && $name !== '' ? $name : (string) $elementId;
	}
}
