<?php

namespace HiveNova\Core;

class ApiFrontController
{
	public function dispatch(): void
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
			ApiJsonResponse::sendError('module', 404);
		}
		if (!ApiRouteTable::isKnownAction($resourceName, $action)) {
			ApiJsonResponse::sendError('action', 404);
		}

		$tick = ApiRouteTable::tickClass($resourceName, $action, $method);

		if ($tick === ApiTickClass::Mutate && $method !== 'POST') {
			ApiJsonResponse::sendError('method', 405);
		}

		$this->enforceSeason($resourceName);

		if ($tick === ApiTickClass::Mutate) {
			ApiCsrf::enforceMutate();
			$this->applyPlanetId($USER);
			$PLANET = $this->reloadPlanet($USER);
		}

		if ($tick->runsEconomy()) {
			$this->runTicks();
		}

		$planetId = (int) ($PLANET['id'] ?? 0);
		$meta = [
			'serverTime' => TIMESTAMP,
			'planetId' => $planetId,
		];

		match ($resourceName) {
			'bootstrap' => ApiJsonResponse::sendSuccess($this->bootstrap(), $meta),
			'overview' => $this->overview($action, $meta),
			'alerts' => ApiJsonResponse::sendSuccess([
				'count' => IncomingHostileFleetQuery::countForUser((int) $USER['id']),
			], $meta),
			'events' => ApiJsonResponse::sendSuccess([
				'events' => EventFirehoseFeed::fetch(
					(int) ($USER['universe'] ?? 0),
					$LNG,
					(string) ($USER['timezone'] ?? 'UTC'),
					(int) HTTP::_GP('sinceId', 0),
				),
			], $meta),
			'i18n' => ApiJsonResponse::sendSuccess($this->i18nSlice(), $meta),
			default => ApiJsonResponse::sendError('module', 404),
		};
	}

	private function enforceSeason(string $resourceName): void
	{
		global $USER;

		$config = Config::get();
		$season = new SeasonService(new DatabaseSeasonStore());
		if ($season->mustRedirect($USER, $config, ApiRouteTable::seasonPageAlias($resourceName))) {
			ApiJsonResponse::sendError('season', 403);
		}
	}

	/**
	 * @param array<string, mixed> $user
	 */
	private function applyPlanetId(array $user): void
	{
		$raw = array_key_exists('planetId', $_REQUEST) ? (string) $_REQUEST['planetId'] : null;
		$resolved = ApiPlanetIdResolver::fromRequest($raw);
		if (!$resolved['ok']) {
			ApiJsonResponse::sendError($resolved['error'], $resolved['status']);
		}

		$requested = $resolved['planetId'];
		if ($requested === null) {
			return;
		}

		$sql = 'SELECT id FROM %%PLANETS%% WHERE id = :planetId AND id_owner = :userId AND destruyed = 0;';
		$owned = Database::get()->selectSingle($sql, [
			':planetId' => $requested,
			':userId' => (int) $user['id'],
		], 'id');
		$ownedId = !empty($owned) ? (int) $owned : null;
		$ownedCheck = ApiPlanetIdResolver::requireOwned($requested, $ownedId);
		if (!$ownedCheck['ok']) {
			ApiJsonResponse::sendError($ownedCheck['error'], $ownedCheck['status']);
		}

		Session::load()->planetId = $requested;
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
		$eco->CalcResource();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function bootstrap(): array
	{
		global $USER, $PLANET, $resource, $reslist;

		$config = Config::get();
		$season = new SeasonService(new DatabaseSeasonStore());

		$resourceTable = [];
		$resourceSpeed = $config->resource_multiplier;
		if (isset($reslist['resstype'][1]) && is_array($reslist['resstype'][1])) {
			foreach ($reslist['resstype'][1] as $resourceID) {
				$col = $resource[$resourceID];
				$production = (float) ($PLANET[$col.'_perhour'] ?? 0);
				if ((int) ($USER['urlaubs_modus'] ?? 0) !== 1 && (int) ($PLANET['planet_type'] ?? 1) === 1) {
					$production += (float) $config->{$col.'_basic_income'} * $resourceSpeed;
				}
				$resourceTable[$resourceID] = [
					'name' => $col,
					'current' => (float) ($PLANET[$col] ?? 0),
					'max' => (float) ($PLANET[$col.'_max'] ?? 0),
					'production' => $production,
				];
			}
		}

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
		) + ['i18n' => $this->i18nSlice()];
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function overview(string $action, array $meta): void
	{
		global $USER, $PLANET, $LNG;

		if ($action === 'rename') {
			$newName = HTTP::_GP('name', '', UTF8_SUPPORT);
			$service = new OverviewPlanetActionService();
			$result = $service->validateRename($newName, $PLANET);
			if (!$result['ok']) {
				$code = $result['error'] ?? 'empty_name';
				$message = (string) ($LNG['ov_newname_specialchar'] ?? '');
				ApiJsonResponse::sendError($code, 422, $message);
			}
			Database::get()->update('UPDATE %%PLANETS%% SET name = :newName WHERE id = :planetID;', [
				':newName' => $newName,
				':planetID' => $PLANET['id'],
			]);
			$PLANET['name'] = $newName;
			ApiJsonResponse::sendSuccess([
				'name' => $newName,
				'message' => (string) ($LNG['ov_newname_done'] ?? ''),
			], $meta);
		}

		ApiJsonResponse::sendSuccess([
			'planet' => [
				'id' => (int) $PLANET['id'],
				'name' => (string) $PLANET['name'],
				'galaxy' => (int) $PLANET['galaxy'],
				'system' => (int) $PLANET['system'],
				'planet' => (int) $PLANET['planet'],
				'image' => (string) ($PLANET['image'] ?? ''),
				'diameter' => (int) ($PLANET['diameter'] ?? 0),
				'fieldCurrent' => (int) ($PLANET['field_current'] ?? 0),
				'fieldMax' => (int) CalculateMaxPlanetFields($PLANET),
				'tempMin' => (int) ($PLANET['temp_min'] ?? 0),
				'tempMax' => (int) ($PLANET['temp_max'] ?? 0),
			],
			'username' => (string) $USER['username'],
		], $meta);
	}

	/**
	 * @return array<string, string>
	 */
	private function i18nSlice(): array
	{
		global $LNG;

		$keys = [
			'lm_overview', 'lm_buildings', 'lm_shipshard', 'lm_defenses', 'lm_research',
			'lm_fleet', 'lm_galaxy', 'lm_messages', 'lm_alliance', 'lm_options', 'lm_logout',
			'lm_administration', 'hn_classic_ui', 'hn_try_new_ui',
			'ov_newname_done', 'ov_newname_specialchar',
		];
		$out = [];
		foreach ($keys as $key) {
			$out[$key] = (string) ($LNG[$key] ?? $key);
		}

		return $out;
	}
}
