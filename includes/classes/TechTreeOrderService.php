<?php

namespace HiveNova\Core;

/**
 * Display order for the Technologies / tech tree page.
 *
 * Unlock effort is the hardest prerequisite path: for each required
 * element, effort(req) + required level. Items with no requirements
 * have effort 0. That puts early unlocks (Shipyard: Gigafactory 2)
 * ahead of late ones (University: Lab 22 + a deep research chain)
 * without changing gameplay requirements or costs.
 */
class TechTreeOrderService
{
	/** @var array<int, array{0: int, 1: int}> */
	public const CATEGORY_RANGES = [
		0   => [1, 99],
		100 => [101, 199],
		200 => [201, 299],
		400 => [401, 499],
		500 => [501, 599],
		600 => [601, 699],
	];

	/** @var array<int, array<int, int>> */
	private array $requirements;

	/** @var array<int, int> */
	private array $effortMemo = [];

	/** @var array<int, int> */
	private array $depthMemo = [];

	/** @var array<int, true> */
	private array $visitingEffort = [];

	/** @var array<int, true> */
	private array $visitingDepth = [];

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

	public function categoryOf(int $elementId): ?int
	{
		foreach (self::CATEGORY_RANGES as $categoryId => [$min, $max]) {
			if ($elementId >= $min && $elementId <= $max) {
				return $categoryId;
			}
		}

		return null;
	}

	public function depth(int $elementId): int
	{
		if (array_key_exists($elementId, $this->depthMemo)) {
			return $this->depthMemo[$elementId];
		}
		if (isset($this->visitingDepth[$elementId])) {
			return 0;
		}

		$reqs = $this->requirements[$elementId] ?? [];
		if ($reqs === []) {
			return $this->depthMemo[$elementId] = 0;
		}

		$this->visitingDepth[$elementId] = true;
		$max = 0;
		foreach ($reqs as $requireId => $_) {
			$child = $this->depth($requireId);
			if ($child > $max) {
				$max = $child;
			}
		}
		unset($this->visitingDepth[$elementId]);

		return $this->depthMemo[$elementId] = $max + 1;
	}

	public function effort(int $elementId): int
	{
		if (array_key_exists($elementId, $this->effortMemo)) {
			return $this->effortMemo[$elementId];
		}
		if (isset($this->visitingEffort[$elementId])) {
			return 0;
		}

		$reqs = $this->requirements[$elementId] ?? [];
		if ($reqs === []) {
			return $this->effortMemo[$elementId] = 0;
		}

		$this->visitingEffort[$elementId] = true;
		$max = 0;
		foreach ($reqs as $requireId => $level) {
			$path = $this->effort($requireId) + $level;
			if ($path > $max) {
				$max = $path;
			}
		}
		unset($this->visitingEffort[$elementId]);

		return $this->effortMemo[$elementId] = $max;
	}

	/**
	 * @param list<int|string> $elementIds
	 * @return list<int>
	 */
	public function sortIds(array $elementIds): array
	{
		$ids = array_values(array_unique(array_map('intval', $elementIds)));

		usort($ids, function (int $left, int $right): int {
			$effortCmp = $this->effort($left) <=> $this->effort($right);
			if ($effortCmp !== 0) {
				return $effortCmp;
			}

			$depthCmp = $this->depth($left) <=> $this->depth($right);
			if ($depthCmp !== 0) {
				return $depthCmp;
			}

			return $left <=> $right;
		});

		return $ids;
	}

	/**
	 * Category id → element IDs in unlock order (JSON object keys 0/100/…).
	 *
	 * @param list<int|string> $elementIds
	 * @return array<int, list<int>>
	 */
	public function orderByCategory(array $elementIds): array
	{
		$grouped = [];
		foreach (array_keys(self::CATEGORY_RANGES) as $categoryId) {
			$grouped[$categoryId] = [];
		}

		foreach ($elementIds as $elementId) {
			$elementId = (int) $elementId;
			$categoryId = $this->categoryOf($elementId);
			if ($categoryId === null) {
				continue;
			}
			$grouped[$categoryId][] = $elementId;
		}

		foreach ($grouped as $categoryId => $ids) {
			$grouped[$categoryId] = $this->sortIds($ids);
		}

		return $grouped;
	}
}
