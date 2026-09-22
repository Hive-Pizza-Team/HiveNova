<?php

namespace HiveNova\Core;

/**
 * Deep-links from a requirement name to the existing Research / Buildings /
 * Shipyard / Officer panel, using the same DOM ids those pages already expose
 * (`t115`, `s202`, plus `g*` for buildings and `o*` for officers).
 */
class ElementRequirementService
{
	public const SHIPYARD = 21;
	public const ENERGY_TECH = 113;
	/** Level that puts Energy Technology on the Day-0 Shipyard path. */
	public const ENERGY_TECH_FOR_SHIPYARD = 1;

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

	public function pageForElement(int $elementId): ?string
	{
		if ($elementId >= self::BUILDING_MIN && $elementId <= self::BUILDING_MAX) {
			return 'buildings';
		}
		if ($elementId >= self::RESEARCH_MIN && $elementId <= self::RESEARCH_MAX) {
			return 'research';
		}
		if ($elementId >= self::SHIP_MIN && $elementId <= self::SHIP_MAX) {
			return 'shipyard';
		}
		if ($elementId >= self::DEFENSE_MIN && $elementId <= self::DEFENSE_MAX) {
			return 'shipyard';
		}
		if ($elementId >= self::OFFICIER_MIN && $elementId <= self::OFFICIER_MAX) {
			return 'officier';
		}

		return null;
	}

	public function fragmentId(int $elementId): ?string
	{
		if ($elementId >= self::BUILDING_MIN && $elementId <= self::BUILDING_MAX) {
			return self::FRAGMENT_BUILDING . $elementId;
		}
		if ($elementId >= self::RESEARCH_MIN && $elementId <= self::RESEARCH_MAX) {
			return self::FRAGMENT_RESEARCH . $elementId;
		}
		if ($elementId >= self::SHIP_MIN && $elementId <= self::SHIP_MAX) {
			return self::FRAGMENT_SHIPYARD . $elementId;
		}
		if ($elementId >= self::DEFENSE_MIN && $elementId <= self::DEFENSE_MAX) {
			return self::FRAGMENT_SHIPYARD . $elementId;
		}
		if ($elementId >= self::OFFICIER_MIN && $elementId <= self::OFFICIER_MAX) {
			return self::FRAGMENT_OFFICIER . $elementId;
		}

		return null;
	}

	public function hrefForElement(int $elementId): ?string
	{
		$page = $this->pageForElement($elementId);
		$fragment = $this->fragmentId($elementId);
		if ($page === null || $fragment === null) {
			return null;
		}

		$url = 'game.php?page=' . $page;
		if ($page === 'shipyard') {
			$mode = ($elementId >= self::DEFENSE_MIN && $elementId <= self::DEFENSE_MAX)
				? ShipyardPageModeService::MODE_DEFENSE
				: ShipyardPageModeService::MODE_FLEET;
			$url .= '&mode=' . $mode;
		}

		return $url . '#' . $fragment;
	}

	/**
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $requirements
	 * @param array<int|string, mixed> $resourceColumns
	 * @param array<int|string, mixed> $techNames
	 * @return list<array{id:int,name:string,count:int,own:int,met:bool,href:?string}>
	 */
	public function listForElement(
		int $elementId,
		array $user,
		array $planet,
		array $requirements,
		array $resourceColumns,
		array $techNames
	): array {
		$needed = $requirements[$elementId] ?? $requirements[(string) $elementId] ?? null;
		if (!is_array($needed) || $needed === []) {
			return [];
		}

		$rows = [];
		foreach ($needed as $requireId => $level) {
			$requireId = (int) $requireId;
			$count = (int) $level;
			$own = $this->ownedLevel($requireId, $user, $planet, $resourceColumns);
			$name = $techNames[$requireId] ?? $techNames[(string) $requireId] ?? (string) $requireId;
			$rows[] = [
				'id'    => $requireId,
				'name'  => (string) $name,
				'count' => $count,
				'own'   => $own,
				'met'   => $own >= $count,
				'href'  => $this->hrefForElement($requireId),
			];
		}

		return $this->withShipyardEnergyPath($elementId, $rows, $user, $planet, $resourceColumns, $techNames);
	}

	/**
	 * Clickable unmet-requirement lines for a locked-element message.
	 * Uses the same hrefs as the requirement rows (#611 / #612).
	 *
	 * @param list<array{id:int,name:string,count:int,own:int,met:bool,href:?string}> $rows
	 */
	public function unmetRequirementLinksHtml(array $rows, string $levelLabel = 'Level '): string
	{
		$parts = [];
		foreach ($rows as $row) {
			if (!empty($row['met'])) {
				continue;
			}
			$name = htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8');
			$own = (int) ($row['own'] ?? 0);
			$count = (int) ($row['count'] ?? 0);
			$level = htmlspecialchars($levelLabel, ENT_QUOTES, 'UTF-8') . $own . '/' . $count;
			$label = $name . ' <span class="requirement-level">(' . $level . ')</span>';
			$href = $row['href'] ?? null;
			if (is_string($href) && $href !== '') {
				$safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
				$parts[] = '<a href="' . $safeHref . '" class="requirement-link requirement-link--unmet">' . $label . '</a>';
			} else {
				$parts[] = '<span class="requirement-link requirement-link--unmet">' . $label . '</span>';
			}
		}

		if ($parts === []) {
			return '';
		}

		return '<br>' . implode('<br>', $parts);
	}

	/**
	 * Shipyard stays a Gigafactory building. When it is locked and Energy
	 * Technology is still missing, surface Energy as a clickable reason so
	 * the Day-0 path is visible on the locked card and the info popup.
	 *
	 * @param list<array{id:int,name:string,count:int,own:int,met:bool,href:?string}> $rows
	 * @param array<int|string, mixed> $user
	 * @param array<int|string, mixed> $planet
	 * @param array<int|string, mixed> $resourceColumns
	 * @param array<int|string, mixed> $techNames
	 * @return list<array{id:int,name:string,count:int,own:int,met:bool,href:?string}>
	 */
	private function withShipyardEnergyPath(
		int $elementId,
		array $rows,
		array $user,
		array $planet,
		array $resourceColumns,
		array $techNames
	): array {
		if ($elementId !== self::SHIPYARD || $rows === []) {
			return $rows;
		}

		foreach ($rows as $row) {
			if ((int) ($row['id'] ?? 0) === self::ENERGY_TECH) {
				return $rows;
			}
		}

		$locked = false;
		foreach ($rows as $row) {
			if (empty($row['met'])) {
				$locked = true;
				break;
			}
		}
		if (!$locked) {
			return $rows;
		}

		$own = $this->ownedLevel(self::ENERGY_TECH, $user, $planet, $resourceColumns);
		$need = self::ENERGY_TECH_FOR_SHIPYARD;
		if ($own >= $need) {
			return $rows;
		}

		$name = $techNames[self::ENERGY_TECH] ?? $techNames[(string) self::ENERGY_TECH] ?? 'Energy Technology';
		$rows[] = [
			'id'    => self::ENERGY_TECH,
			'name'  => (string) $name,
			'count' => $need,
			'own'   => $own,
			'met'   => false,
			'href'  => $this->hrefForElement(self::ENERGY_TECH),
		];

		return $rows;
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
}
