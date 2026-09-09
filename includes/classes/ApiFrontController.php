<?php

namespace HiveNova\Core;

class ApiFrontController
{
	public function dispatch(): void
	{
		$result = $this->handle();
		if (!empty($result['ok'])) {
			ApiJsonResponse::sendSuccess($result['data'] ?? [], $result['meta'] ?? [], (int) ($result['status'] ?? 200));
		}
		ApiJsonResponse::sendError(
			(string) ($result['error'] ?? 'error'),
			(int) ($result['status'] ?? 500),
			(string) ($result['message'] ?? '')
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function handle(): array
	{
		global $USER, $PLANET, $LNG;

		$resourceName = ApiRouteTable::sanitizeResource((string) HTTP::_GP('r', 'bootstrap'));
		if ($resourceName === '') {
			$resourceName = 'bootstrap';
		}
		$action = ApiRouteTable::sanitizeAction((string) HTTP::_GP('action', 'show'));
		if ($action === '') {
			$action = 'show';
		}
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

		if (!ApiRouteTable::isKnown($resourceName)) {
			return self::fail('module', 404);
		}
		if (!ApiRouteTable::isKnownAction($resourceName, $action)) {
			return self::fail('action', 404);
		}

		if (ApiRouteTable::isPublic($resourceName)) {
			return self::ok([
				'gameName' => trim((string) Config::get()->game_name),
			], [
				'serverTime' => TIMESTAMP,
				'planetId' => 0,
			]);
		}

		$tick = ApiRouteTable::tickClass($resourceName, $action, $method);

		if ($tick === ApiTickClass::Mutate && $method !== 'POST') {
			return self::fail('method', 405);
		}

		$seasonFail = $this->enforceSeason($resourceName);
		if ($seasonFail !== null) {
			return $seasonFail;
		}

		if ($tick === ApiTickClass::Mutate) {
			$csrfFail = ApiCsrf::rejectMutate();
			if ($csrfFail !== null) {
				return $csrfFail;
			}
			if ((int) ($USER['urlaubs_modus'] ?? 0) === 1) {
				return self::fail('vacation', 403);
			}
		}
		if ($tick === ApiTickClass::Mutate || array_key_exists('planetId', $_REQUEST)) {
			$planetFail = $this->applyPlanetId($USER);
			if ($planetFail !== null) {
				return $planetFail;
			}
			$PLANET = $this->reloadPlanet($USER);
		}

		if ($tick->runsEconomy()) {
			$this->runTicks();
			$this->persistPlanet();
		}

		$planetId = (int) ($PLANET['id'] ?? 0);
		$meta = [
			'serverTime' => TIMESTAMP,
			'planetId' => $planetId,
		];

		return match ($resourceName) {
			'bootstrap' => self::ok($this->bootstrap(), $meta),
			'overview' => $this->overview($action, $meta),
			'buildings' => $this->buildings($action, $meta),
			'research' => $this->research($action, $meta),
			'shipyard' => $this->shipyard($action, $meta),
			'fleet' => $this->fleet($action, $meta),
			'galaxy' => $this->galaxy($action, $meta),
			'messages' => self::ok(
				MessagePlayService::inbox($USER, (int) HTTP::_GP('category', 100), (int) HTTP::_GP('side', 1))
					+ ['categories' => MessagePlayService::categoryLabels($LNG)],
				$meta
			),
			'alerts' => self::ok([
				'count' => IncomingHostileFleetQuery::countForUser((int) $USER['id']),
			], $meta),
			'events' => self::ok([
				'events' => EventFirehoseFeed::fetch(
					(int) ($USER['universe'] ?? 0),
					$LNG,
					(string) ($USER['timezone'] ?? 'UTC'),
					(int) HTTP::_GP('sinceId', 0),
				),
			], $meta),
			'i18n' => self::ok(CatalogPlayService::chromeLabels($LNG), $meta),
			'catalog' => $this->catalog($action, $meta),
			default => self::fail('module', 404),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function ok(array $data, array $meta = [], int $status = 200): array
	{
		return ['ok' => true, 'data' => $data, 'meta' => $meta, 'status' => $status];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function fail(string $error, int $status, string $message = ''): array
	{
		return ['ok' => false, 'error' => $error, 'status' => $status, 'message' => $message];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function enforceSeason(string $resourceName): ?array
	{
		global $USER;

		$config = Config::get();
		$season = new SeasonService(new DatabaseSeasonStore());
		if ($season->mustRedirect($USER, $config, ApiRouteTable::seasonPageAlias($resourceName))) {
			return self::fail('season', 403);
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>|null
	 */
	private function applyPlanetId(array $user): ?array
	{
		$raw = array_key_exists('planetId', $_REQUEST) ? (string) $_REQUEST['planetId'] : null;
		$resolved = ApiPlanetIdResolver::fromRequest($raw);
		if (!$resolved['ok']) {
			return self::fail($resolved['error'], $resolved['status']);
		}

		$requested = $resolved['planetId'];
		if ($requested === null) {
			return null;
		}

		$sql = 'SELECT id FROM %%PLANETS%% WHERE id = :planetId AND id_owner = :userId AND destruyed = 0;';
		$owned = Database::get()->selectSingle($sql, [
			':planetId' => $requested,
			':userId' => (int) $user['id'],
		], 'id');
		$ownedId = !empty($owned) ? (int) $owned : null;
		$ownedCheck = ApiPlanetIdResolver::requireOwned($requested, $ownedId);
		if (!$ownedCheck['ok']) {
			return self::fail($ownedCheck['error'], $ownedCheck['status']);
		}

		Session::load()->planetId = $requested;

		return null;
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>
	 */
	private function reloadPlanet(array $user): array
	{
		$session = Session::load();
		$sql = 'SELECT * FROM %%PLANETS%% WHERE id = :planetId;';
		$planet = Database::get()->selectSingle($sql, [
			':planetId' => $session->planetId,
		]);
		if (empty($planet)) {
			$sql = 'SELECT * FROM %%PLANETS%% WHERE id = :planetId;';
			$planet = Database::get()->selectSingle($sql, [
				':planetId' => $user['id_planet'],
			]);
		}

		return is_array($planet) ? $planet : [];
	}

	private function runTicks(): void
	{
		global $resource, $reslist;

		if (isModuleAvailable(MODULE_FLEET_EVENTS)) {
			require_once ROOT_PATH.'includes/FleetHandler.php';
		}

		$eco = new ResourceUpdate();
		$eco->setResourceData($resource, $reslist);
		$eco->CalcResource(null, null, true);
	}

	private function persistPlanet(): void
	{
		global $USER, $PLANET, $resource, $reslist;

		$eco = new ResourceUpdate();
		$eco->setResourceData($resource, $reslist);
		$eco->setData($USER, $PLANET);
		$eco->SavePlanetToDB();
		[$USER, $PLANET] = $eco->getData();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function bootstrap(): array
	{
		global $USER, $PLANET, $LNG, $resource, $reslist;

		$config = Config::get();
		$season = new SeasonService(new DatabaseSeasonStore());

		$resourceTable = ApiBootstrapService::resourceTable(
			$USER,
			$PLANET,
			is_array($resource) ? $resource : [],
			is_array($reslist) ? $reslist : [],
			$config
		);

		$modules = [];
		for ($i = 0; $i < MODULE_AMOUNT; $i++) {
			if (isModuleAvailable($i)) {
				$modules[] = $i;
			}
		}

		return (new ApiBootstrapService())->build(
			$USER,
			$PLANET,
			is_array($USER['PLANETS'] ?? null) ? $USER['PLANETS'] : [],
			$resourceTable,
			IncomingHostileFleetQuery::countForUser((int) $USER['id']),
			Cronjob::getNeedTodoExecutedJobs(),
			$modules,
			ApiCsrf::token(),
			TIMESTAMP,
			AssetRevision::fromFilesystem((string) $config->VERSION),
			(int) $config->game_disable === 0,
			!$season->canPlay($USER, $config),
			trim((string) $config->game_name),
		) + ['i18n' => CatalogPlayService::chromeLabels($LNG)];
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function overview(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG;

		if ($action === 'rename') {
			$newName = HTTP::_GP('name', '', UTF8_SUPPORT);
			$service = new OverviewPlanetActionService();
			$result = $service->validateRename($newName, $PLANET);
			if (!$result['ok']) {
				$code = $result['error'] ?? 'empty_name';
				$message = (string) ($LNG['ov_newname_specialchar'] ?? '');

				return self::fail($code, 422, $message);
			}
			Database::get()->update('UPDATE %%PLANETS%% SET name = :newName WHERE id = :planetID;', [
				':newName' => $newName,
				':planetID' => $PLANET['id'],
			]);
			$PLANET['name'] = $newName;

			return self::ok([
				'name' => $newName,
				'message' => (string) ($LNG['ov_newname_done'] ?? ''),
			], $meta);
		}

		return self::ok(
			(new OverviewPlanetActionService())->snapshot(
				$USER,
				$PLANET,
				$LNG,
				is_array($USER['PLANETS'] ?? null) ? array_values($USER['PLANETS']) : []
			),
			$meta
		);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function buildings(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG;

		if ($action === 'insert') {
			$ok = EconomyPlayService::insertBuilding($USER, $PLANET, (int) HTTP::_GP('building', 0));
			$this->persistPlanet();
			if (!$ok) {
				return self::fail('queue', 422);
			}
		} elseif ($action === 'cancel') {
			EconomyPlayService::cancelBuilding($USER, $PLANET);
			$this->persistPlanet();
		}

		return self::ok(EconomyPlayService::buildings($USER, $PLANET, $LNG), $meta);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function research(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG;

		if ($action === 'insert') {
			$ok = EconomyPlayService::insertResearch($USER, $PLANET, (int) HTTP::_GP('tech', 0));
			$this->persistPlanet();
			if (!$ok) {
				return self::fail('queue', 422);
			}
		}

		return self::ok(EconomyPlayService::research($USER, $PLANET, $LNG), $meta);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function shipyard(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG;

		$mode = (string) HTTP::_GP('mode', 'fleet');
		if ($action === 'build') {
			$ok = EconomyPlayService::buildShips($USER, $PLANET, HTTP::_GP('fmenge', []));
			$this->persistPlanet();
			if (!$ok) {
				return self::fail('queue', 422);
			}
		}

		return self::ok(EconomyPlayService::shipyard($USER, $PLANET, $LNG, $mode), $meta);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function fleet(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG;

		try {
			if ($action === 'send') {
				$result = FleetPlayService::send(
					$USER,
					$PLANET,
					HTTP::_GP('ships', []),
					[
						'galaxy' => (int) HTTP::_GP('galaxy', 0),
						'system' => (int) HTTP::_GP('system', 0),
						'planet' => (int) HTTP::_GP('planet', 0),
						'type' => (int) HTTP::_GP('type', 1),
					],
					(int) HTTP::_GP('mission', FLEET_MISSION_ATTACK),
					(int) HTTP::_GP('speed', 10),
					[
						901 => (int) HTTP::_GP('metal', 0),
						902 => (int) HTTP::_GP('crystal', 0),
						903 => (int) HTTP::_GP('deuterium', 0),
					]
				);
				$this->persistPlanet();

				return self::ok($result, $meta);
			}
			if ($action === 'recall') {
				FleetPlayService::recall($USER, (int) HTTP::_GP('fleetId', 0));

				return self::ok(['recalled' => true], $meta);
			}
		} catch (\RuntimeException $e) {
			return self::fail('fleet', 422, $e->getMessage());
		}

		return self::ok(FleetPlayService::table($USER, $PLANET, $LNG), $meta);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function galaxy(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG;

		try {
			if ($action === 'spy') {
				$result = FleetPlayService::spy($USER, $PLANET, (int) HTTP::_GP('targetPlanetId', 0));
				$this->persistPlanet();

				return self::ok($result, $meta);
			}
			$data = GalaxyPlayService::show(
				$USER,
				$PLANET,
				(int) HTTP::_GP('galaxy', (int) $PLANET['galaxy']),
				(int) HTTP::_GP('system', (int) $PLANET['system'])
			);
			if (!empty($data['deuteriumCharged'])) {
				$this->persistPlanet();
			}

			return self::ok($data, $meta);
		} catch (\RuntimeException $e) {
			return self::fail('galaxy', 422, $e->getMessage());
		}
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function catalog(string $action, array $meta): array
	{
		global $USER, $PLANET, $LNG, $resource, $reslist, $requirements;

		$kind = CatalogPlayService::sanitizeKind((string) HTTP::_GP('kind', 'techtree'));
		$resourceMap = is_array($resource) ? $resource : [];
		$listMap = is_array($reslist) ? $reslist : [];

		if ($action === 'prod') {
			$updates = CatalogPlayService::productionUpdates(HTTP::_GP('prod', []), $resourceMap);
			if ($updates !== []) {
				$applied = CatalogPlayService::applyProductionUpdates($PLANET, $updates);
				$params = [':planetId' => (int) $PLANET['id']] + $applied['params'];
				Database::get()->update('UPDATE %%PLANETS%% SET '.implode(', ', $applied['set']).' WHERE id = :planetId;', $params);
			}

			return self::ok([
				'sliders' => CatalogPlayService::productionSliders($PLANET, $LNG, $resourceMap),
			], $meta);
		}

		if ($action === 'note') {
			$title = HTTP::_GP('title', '', UTF8_SUPPORT);
			$text = HTTP::_GP('text', '', UTF8_SUPPORT);
			if ($title !== '' && $text !== '') {
				Database::get()->insert(
					'INSERT INTO %%NOTES%% SET owner = :owner, universe = :uni, time = :time, priority = :priority, title = :title, text = :text;',
					[
						':owner' => (int) $USER['id'],
						':uni' => (int) ($USER['universe'] ?? 0),
						':time' => TIMESTAMP,
						':priority' => (int) HTTP::_GP('priority', 1),
						':title' => $title,
						':text' => $text,
					]
				);
			}
		}

		$data = match ($kind) {
			'officers' => ['items' => CatalogPlayService::officers($USER, $PLANET, $LNG, $listMap, $resourceMap)],
			'resources' => ['sliders' => CatalogPlayService::productionSliders($PLANET, $LNG, $resourceMap)],
			'trader' => [
				'rates' => CatalogPlayService::traderRates(),
				'cost' => (int) Config::get()->darkmatter_cost_trader,
			],
			'empire' => ['bodies' => CatalogPlayService::empireBodies($this->empirePlanetRows((int) $USER['id'], $PLANET), Config::get())],
			'missiles' => CatalogPlayService::missiles($PLANET, $resourceMap),
			'phalanx' => CatalogPlayService::phalanx($PLANET, $resourceMap),
			'acs' => ['groups' => $this->acsGroups((int) $USER['id'])],
			'buddies' => ['items' => $this->buddyItems((int) $USER['id'])],
			'notes' => ['items' => $this->noteItems((int) $USER['id'])],
			'statistics' => ['items' => $this->statItems((int) ($USER['universe'] ?? 0))],
			'alliance' => CatalogPlayService::allianceSummary($USER),
			default => [
				'items' => CatalogPlayService::techtree(
					$USER,
					$PLANET,
					$LNG,
					is_array($requirements) ? $requirements : [],
					$listMap,
					$resourceMap
				),
			],
		};

		return self::ok($data, $meta);
	}

	/**
	 * @param array<string, mixed> $current
	 * @return list<array<string, mixed>>
	 */
	private function empirePlanetRows(int $userId, array $current): array
	{
		try {
			$rows = Database::get()->select(
				'SELECT id, name, image, galaxy, `system`, planet, planet_type,
					field_current, field_max, temp_min, temp_max,
					metal, crystal, deuterium, energy, energy_used,
					metal_perhour, crystal_perhour, deuterium_perhour
				FROM %%PLANETS%% WHERE id_owner = :id AND destruyed = 0
				ORDER BY galaxy, `system`, planet, planet_type;',
				[':id' => $userId]
			);
		} catch (\Throwable) {
			global $USER;
			$rows = array_values(is_array($USER['PLANETS'] ?? null) ? $USER['PLANETS'] : []);
		}
		$out = [];
		foreach ($rows as $row) {
			if ((int) ($row['id'] ?? 0) === (int) ($current['id'] ?? 0)) {
				$row = array_merge($row, [
					'name' => $current['name'] ?? $row['name'] ?? '',
					'image' => $current['image'] ?? $row['image'] ?? '',
					'metal' => $current['metal'] ?? $row['metal'] ?? 0,
					'crystal' => $current['crystal'] ?? $row['crystal'] ?? 0,
					'deuterium' => $current['deuterium'] ?? $row['deuterium'] ?? 0,
					'energy' => $current['energy'] ?? $row['energy'] ?? 0,
					'energy_used' => $current['energy_used'] ?? $row['energy_used'] ?? 0,
					'metal_perhour' => $current['metal_perhour'] ?? $row['metal_perhour'] ?? 0,
					'crystal_perhour' => $current['crystal_perhour'] ?? $row['crystal_perhour'] ?? 0,
					'deuterium_perhour' => $current['deuterium_perhour'] ?? $row['deuterium_perhour'] ?? 0,
					'field_current' => $current['field_current'] ?? $row['field_current'] ?? 0,
					'field_max' => $current['field_max'] ?? $row['field_max'] ?? 0,
				]);
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function acsGroups(int $userId): array
	{
		try {
			$rows = Database::get()->select(
				'SELECT a.id, a.name FROM %%USERS_ACS%% ua INNER JOIN %%AKS%% a ON a.id = ua.acsID WHERE ua.userID = :id;',
				[':id' => $userId]
			);
		} catch (\Throwable) {
			return [];
		}
		$out = [];
		foreach ($rows as $row) {
			$out[] = CatalogPlayService::mapAcs($row);
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function buddyItems(int $userId): array
	{
		$out = [];
		foreach (\HiveNova\Repository\BuddyRepository::getBuddyList($userId) as $row) {
			$out[] = CatalogPlayService::mapBuddy($row, $userId);
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function noteItems(int $userId): array
	{
		$rows = Database::get()->select(
			'SELECT * FROM %%NOTES%% WHERE owner = :id ORDER BY priority DESC, time DESC;',
			[':id' => $userId]
		);
		$out = [];
		foreach ($rows as $row) {
			$out[] = CatalogPlayService::mapNote($row);
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function statItems(int $universe): array
	{
		try {
			$rows = Database::get()->select(
				'SELECT s.total_rank, s.total_points, s.id_owner, u.id, u.username, a.ally_name
				FROM %%STATPOINTS%% s
				INNER JOIN %%USERS%% u ON u.id = s.id_owner
				LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
				WHERE s.stat_type = 1 AND s.universe = :uni
				ORDER BY s.total_rank ASC LIMIT 25;',
				[':uni' => $universe]
			);
		} catch (\Throwable) {
			return [];
		}
		$out = [];
		foreach ($rows as $row) {
			$out[] = CatalogPlayService::mapStat($row);
		}

		return $out;
	}
}
