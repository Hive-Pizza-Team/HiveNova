<?php

namespace HiveNova\Core;

class EconomyPlayService
{
	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<string, string> $lng
	 * @return array<string, mixed>
	 */
	public static function buildings(array $user, array $planet, mixed $lng): array
	{
		global $resource, $reslist, $pricelist;

		$elements = $reslist['allow'][$planet['planet_type'] ?? 1] ?? ($reslist['build'] ?? []);
		$queueData = self::buildingQueue($planet);
		$queue = $queueData['queue'];
		$quick = $queueData['quick'];
		$config = Config::get();
		$queueCount = count($queue);
		$maxFields = CalculateMaxPlanetFields($planet);
		$items = [];
		foreach ($elements as $elementId) {
			$elementId = (int) $elementId;
			if (!BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)) {
				continue;
			}
			$level = (int) ($planet[$resource[$elementId]] ?? 0);
			$levelToBuild = (int) ($quick[$elementId] ?? $level);
			$cost = BuildFunctions::getElementPrice($user, $planet, $elementId, false, $levelToBuild + 1);
			$items[$elementId] = [
				'id' => $elementId,
				'name' => (string) (self::techName($lng, $elementId)),
				'level' => $level,
				'levelToBuild' => $levelToBuild,
				'maxLevel' => (int) ($pricelist[$elementId]['max'] ?? 255),
				'cost' => self::intMap($cost),
				'overflow' => self::intMap(BuildFunctions::getRestPrice($user, $planet, $elementId, $cost)),
				'time' => (int) BuildFunctions::getBuildingTime($user, $planet, $elementId, $cost),
				'buyable' => $queueCount !== 0 || BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost),
			];
		}

		return [
			'fields' => [
				'current' => (int) ($planet['field_current'] ?? 0),
				'max' => (int) $maxFields,
			],
			'canQueue' => (int) $user['urlaubs_modus'] === 1
				|| (int) $config->max_elements_build === 0
				|| $queueCount < (int) $config->max_elements_build,
			'queue' => $queue,
			'items' => $items,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<string, string> $lng
	 * @return array<string, mixed>
	 */
	public static function research(array $user, array $planet, mixed $lng): array
	{
		global $resource, $reslist, $pricelist;

		$planet[$resource[31].'_inter'] = ResourceUpdate::getNetworkLevel($user, $planet);
		$queueData = self::researchQueue($user);
		$queue = $queueData['queue'];
		$quick = $queueData['quick'];
		$lab = (int) ($planet[$resource[31]] ?? 0);
		$items = [];
		foreach ($reslist['tech'] ?? [] as $elementId) {
			$elementId = (int) $elementId;
			if (!BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)) {
				continue;
			}
			$level = (int) ($user[$resource[$elementId]] ?? 0);
			$levelToBuild = (int) ($quick[$elementId] ?? $level);
			$cost = BuildFunctions::getElementPrice($user, $planet, $elementId, false, $levelToBuild + 1);
			$items[$elementId] = [
				'id' => $elementId,
				'name' => (string) (self::techName($lng, $elementId)),
				'level' => $level,
				'levelToBuild' => $levelToBuild,
				'maxLevel' => (int) ($pricelist[$elementId]['max'] ?? 255),
				'cost' => self::intMap($cost),
				'overflow' => self::intMap(BuildFunctions::getRestPrice($user, $planet, $elementId, $cost)),
				'time' => (int) BuildFunctions::getBuildingTime($user, $planet, $elementId, $cost),
				'buyable' => count($queue) !== 0 || BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost),
			];
		}

		return [
			'labLevel' => $lab,
			'queue' => $queue,
			'items' => $items,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<string, string> $lng
	 * @return array<string, mixed>
	 */
	public static function shipyard(array $user, array $planet, mixed $lng, string $mode): array
	{
		global $resource, $reslist;

		$mode = $mode === 'defense' ? 'defense' : 'fleet';
		$ids = $mode === 'defense'
			? array_merge($reslist['defense'] ?? [], $reslist['missile'] ?? [])
			: ($reslist['fleet'] ?? []);
		$queueRaw = safe_unserialize($planet['b_hangar_id'] ?? '');
		$queue = [];
		$remaining = 0;
		if (is_array($queueRaw)) {
			foreach ($queueRaw as $i => $row) {
				$el = (int) ($row[0] ?? 0);
				$count = (int) ($row[1] ?? 0);
				$unit = (int) BuildFunctions::getBuildingTime($user, $planet, $el);
				$remaining += $unit * $count;
				$queue[] = [
					'index' => (int) $i,
					'elementId' => $el,
					'name' => (string) (self::techName($lng, $el)),
					'count' => $count,
					'unitTime' => $unit,
				];
			}
			$remaining = max($remaining - (int) ($planet['b_hangar'] ?? 0), 0);
		}
		$items = [];
		foreach ($ids as $elementId) {
			$elementId = (int) $elementId;
			if (!BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)) {
				continue;
			}
			$cost = BuildFunctions::getElementPrice($user, $planet, $elementId);
			$items[$elementId] = [
				'id' => $elementId,
				'name' => (string) (self::techName($lng, $elementId)),
				'available' => (int) ($planet[$resource[$elementId]] ?? 0),
				'cost' => self::intMap($cost),
				'overflow' => self::intMap(BuildFunctions::getRestPrice($user, $planet, $elementId, $cost)),
				'time' => (int) BuildFunctions::getBuildingTime($user, $planet, $elementId, $cost),
				'buyable' => BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost),
				'maxBuildable' => (int) BuildFunctions::getMaxConstructibleElements($user, $planet, $elementId, $cost),
			];
		}

		return [
			'mode' => $mode,
			'shipyardLevel' => (int) ($planet[$resource[21]] ?? 0),
			'queue' => $queue,
			'remainingTime' => $remaining,
			'items' => $items,
		];
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 */
	public static function insertBuilding(array &$user, array &$planet, int $elementId): bool
	{
		global $resource, $reslist, $pricelist;

		$allowed = $reslist['allow'][$planet['planet_type'] ?? 1] ?? ($reslist['build'] ?? []);
		if (!in_array($elementId, $allowed, false)
			|| !BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)
			|| ($elementId === 31 && (int) ($user['b_tech_planet'] ?? 0) !== 0)
			|| (($elementId === 15 || $elementId === 21) && !empty($planet['b_hangar_id']))
		) {
			return false;
		}

		$currentQueue = safe_unserialize($planet['b_building_id'] ?? '');
		if (!is_array($currentQueue)) {
			$currentQueue = [];
		}
		$actualCount = count($currentQueue);
		$config = Config::get();
		if ((int) $config->max_elements_build !== 0 && $actualCount === (int) $config->max_elements_build) {
			return false;
		}
		if ((int) $planet['field_current'] >= CalculateMaxPlanetFields($planet)) {
			return false;
		}

		$buildLevel = (int) ($planet[$resource[$elementId]] ?? 0) + 1;
		foreach ($currentQueue as $row) {
			if ((int) $row[0] === $elementId) {
				$buildLevel += (($row[4] ?? 'build') === 'build') ? 1 : -1;
			}
		}
		if (($pricelist[$elementId]['max'] ?? 255) < $buildLevel) {
			return false;
		}

		$cost = BuildFunctions::getElementPrice($user, $planet, $elementId, false, $buildLevel);
		if ($actualCount === 0 && !BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost)) {
			return false;
		}

		if ($actualCount === 0) {
			self::debit($user, $planet, $cost);
			$elementTime = BuildFunctions::getBuildingTime($user, $planet, $elementId, $cost);
			$end = TIMESTAMP + $elementTime;
			$planet['b_building_id'] = serialize([[$elementId, $buildLevel, $elementTime, $end, 'build']]);
			$planet['b_building'] = $end;
		} else {
			$elementTime = BuildFunctions::getBuildingTime($user, $planet, $elementId, null, false, $buildLevel);
			$end = $currentQueue[$actualCount - 1][3] + $elementTime;
			$currentQueue[] = [$elementId, $buildLevel, $elementTime, $end, 'build'];
			$planet['b_building_id'] = serialize($currentQueue);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 */
	public static function cancelBuilding(array &$user, array &$planet): bool
	{
		global $resource;

		$currentQueue = safe_unserialize($planet['b_building_id'] ?? '');
		if (!is_array($currentQueue) || $currentQueue === []) {
			$planet['b_building_id'] = '';
			$planet['b_building'] = 0;
			return false;
		}
		$element = (int) $currentQueue[0][0];
		$level = (int) $currentQueue[0][1];
		$mode = (string) $currentQueue[0][4];
		$cost = BuildFunctions::getElementPrice($user, $planet, $element, $mode === 'destroy', $level);
		self::credit($user, $planet, $cost);
		array_shift($currentQueue);
		if ($currentQueue === []) {
			$planet['b_building'] = 0;
			$planet['b_building_id'] = '';
			return true;
		}
		$planet['b_building'] = TIMESTAMP;
		$planet['b_building_id'] = serialize(array_values($currentQueue));
		$eco = new ResourceUpdate();
		$eco->setResourceData($GLOBALS['resource'], $GLOBALS['reslist']);
		$eco->setData($user, $planet);
		$eco->SetNextQueueElementOnTop();
		[$user, $planet] = $eco->getData();

		return true;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 */
	public static function insertResearch(array &$user, array &$planet, int $elementId): bool
	{
		global $resource, $reslist, $pricelist;

		$planet[$resource[31].'_inter'] = ResourceUpdate::getNetworkLevel($user, $planet);

		if (!in_array($elementId, $reslist['tech'] ?? [], false)
			|| !BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)
			|| (int) ($planet[$resource[31]] ?? 0) === 0
		) {
			return false;
		}
		$currentQueue = safe_unserialize($user['b_tech_queue'] ?? '');
		if (!is_array($currentQueue)) {
			$currentQueue = [];
		}
		$actualCount = count($currentQueue);
		if ((int) Config::get()->max_elements_tech !== 0 && (int) Config::get()->max_elements_tech <= $actualCount) {
			return false;
		}
		$buildLevel = (int) ($user[$resource[$elementId]] ?? 0) + 1;
		foreach ($currentQueue as $row) {
			if ((int) $row[0] === $elementId) {
				$buildLevel++;
			}
		}
		if (($pricelist[$elementId]['max'] ?? 255) < $buildLevel) {
			return false;
		}
		$cost = BuildFunctions::getElementPrice($user, $planet, $elementId, false, $buildLevel);
		if ($actualCount === 0 && !BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost)) {
			return false;
		}
		if ($actualCount === 0) {
			self::debit($user, $planet, $cost);
			$elementTime = BuildFunctions::getBuildingTime($user, $planet, $elementId, $cost);
			$end = TIMESTAMP + $elementTime;
			$user['b_tech_queue'] = serialize([[$elementId, $buildLevel, $elementTime, $end, $planet['id']]]);
			$user['b_tech'] = $end;
			$user['b_tech_id'] = $elementId;
			$user['b_tech_planet'] = $planet['id'];
		} else {
			$elementTime = BuildFunctions::getBuildingTime($user, $planet, $elementId, null, false, $buildLevel);
			$end = $currentQueue[$actualCount - 1][3] + $elementTime;
			$currentQueue[] = [$elementId, $buildLevel, $elementTime, $end, $planet['id']];
			$user['b_tech_queue'] = serialize($currentQueue);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int|string, mixed> $fmenge
	 */
	public static function buildShips(array &$user, array &$planet, array $fmenge): bool
	{
		global $resource, $reslist;

		if ((int) ($planet[$resource[21]] ?? 0) === 0) {
			return false;
		}
		$built = false;
		foreach ($fmenge as $elementId => $count) {
			$elementId = (int) $elementId;
			$count = (int) round((float) $count);
			if ($count <= 0
				|| !in_array($elementId, array_merge($reslist['fleet'] ?? [], $reslist['defense'] ?? [], $reslist['missile'] ?? []), true)
				|| !BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)
			) {
				continue;
			}
			$max = (int) BuildFunctions::getMaxConstructibleElements($user, $planet, $elementId);
			$count = max(min($count, (int) Config::get()->max_fleet_per_build, $max), 0);
			if ($count <= 0) {
				continue;
			}
			$cost = BuildFunctions::getElementPrice($user, $planet, $elementId, false, $count);
			self::debit($user, $planet, $cost);
			$buildArray = !empty($planet['b_hangar_id']) ? safe_unserialize($planet['b_hangar_id']) : [];
			if (!is_array($buildArray)) {
				$buildArray = [];
			}
			$buildArray[] = [$elementId, $count];
			$planet['b_hangar_id'] = serialize($buildArray);
			$built = true;
		}

		return $built;
	}

	/**
	 * @param array<int, array<int, mixed>> $raw
	 * @return array{queue: list<array<string, mixed>>, quick: array<int, int>}
	 */
	public static function buildingQueue(array $planet): array
	{
		$script = [];
		$quick = [];
		if (empty($planet['b_building']) || ($planet['b_building_id'] ?? '') === '') {
			return ['queue' => $script, 'quick' => $quick];
		}
		$buildQueue = safe_unserialize($planet['b_building_id']);
		if (!is_array($buildQueue)) {
			return ['queue' => $script, 'quick' => $quick];
		}
		foreach ($buildQueue as $i => $row) {
			if (($row[3] ?? 0) < TIMESTAMP) {
				continue;
			}
			$quick[(int) $row[0]] = (int) $row[1];
			$script[] = [
				'index' => (int) $i + 1,
				'elementId' => (int) $row[0],
				'level' => (int) $row[1],
				'time' => (int) $row[2],
				'resttime' => (int) $row[3] - TIMESTAMP,
				'endtime' => (int) $row[3],
				'destroy' => ($row[4] ?? '') === 'destroy',
			];
		}

		return ['queue' => $script, 'quick' => $quick];
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array{queue: list<array<string, mixed>>, quick: array<int, int>}
	 */
	public static function researchQueue(array $user): array
	{
		$script = [];
		$quick = [];
		$queue = safe_unserialize($user['b_tech_queue'] ?? '');
		if (!is_array($queue)) {
			return ['queue' => $script, 'quick' => $quick];
		}
		foreach ($queue as $i => $row) {
			if (($row[3] ?? 0) < TIMESTAMP) {
				continue;
			}
			$quick[(int) $row[0]] = (int) $row[1];
			$script[] = [
				'index' => (int) $i + 1,
				'elementId' => (int) $row[0],
				'level' => (int) $row[1],
				'time' => (int) $row[2],
				'resttime' => (int) $row[3] - TIMESTAMP,
				'endtime' => (int) $row[3],
				'planetId' => (int) ($row[4] ?? 0),
			];
		}

		return ['queue' => $script, 'quick' => $quick];
	}

	public static function techName(mixed $lng, int $elementId): string
	{
		$tech = is_array($lng) || $lng instanceof \ArrayAccess ? ($lng['tech'] ?? null) : null;
		if (is_array($tech) && isset($tech[$elementId])) {
			return (string) $tech[$elementId];
		}

		return (string) $elementId;
	}

	/**
	 * @param array<int|string, mixed> $map
	 * @return array<int, int>
	 */
	public static function intMap(array $map): array
	{
		$out = [];
		foreach ($map as $k => $v) {
			$out[(int) $k] = (int) $v;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int, float|int> $cost
	 */
	private static function debit(array &$user, array &$planet, array $cost): void
	{
		global $resource;
		if (isset($cost[901])) {
			$planet[$resource[901]] -= $cost[901];
		}
		if (isset($cost[902])) {
			$planet[$resource[902]] -= $cost[902];
		}
		if (isset($cost[903])) {
			$planet[$resource[903]] -= $cost[903];
		}
		if (isset($cost[921])) {
			$user[$resource[921]] -= $cost[921];
		}
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 * @param array<int, float|int> $cost
	 */
	private static function credit(array &$user, array &$planet, array $cost): void
	{
		global $resource;
		if (isset($cost[901])) {
			$planet[$resource[901]] += $cost[901];
		}
		if (isset($cost[902])) {
			$planet[$resource[902]] += $cost[902];
		}
		if (isset($cost[903])) {
			$planet[$resource[903]] += $cost[903];
		}
		if (isset($cost[921])) {
			$user[$resource[921]] += $cost[921];
		}
	}
}
