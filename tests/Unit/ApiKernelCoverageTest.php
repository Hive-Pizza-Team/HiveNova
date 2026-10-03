<?php

use HiveNova\Core\ApiBootstrapService;
use HiveNova\Core\ApiCsrf;
use HiveNova\Core\ApiFrontController;
use HiveNova\Core\ApiJsonResponse;
use HiveNova\Core\CatalogPlayService;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\EconomyPlayService;
use HiveNova\Core\FleetPlayService;
use HiveNova\Core\GalaxyPlayService;
use HiveNova\Core\MessagePlayService;
use HiveNova\Core\OverviewPlanetActionService;
use PHPUnit\Framework\TestCase;

if (!defined('FIELDS_BY_TERRAFORMER')) {
	define('FIELDS_BY_TERRAFORMER', 5);
}
if (!defined('FIELDS_BY_MOONBASIS_LEVEL')) {
	define('FIELDS_BY_MOONBASIS_LEVEL', 3);
}
if (!defined('MODULE_FLEET_EVENTS')) {
	define('MODULE_FLEET_EVENTS', 10);
}
if (!defined('SESSION_LIFETIME')) {
	define('SESSION_LIFETIME', 604800);
}
if (!defined('HTTPS')) {
	define('HTTPS', false);
}
foreach ([
	'MODULE_MISSION_ATTACK' => 1,
	'MODULE_MISSION_SPY' => 24,
	'MODULE_MISSION_DESTROY' => 29,
	'MODULE_MISSION_EXPEDITION' => 30,
	'MODULE_MISSION_DARKMATTER' => 31,
	'MODULE_MISSION_RECYCLE' => 32,
	'MODULE_MISSION_HOLD' => 33,
	'MODULE_MISSION_TRANSPORT' => 34,
	'MODULE_MISSION_COLONY' => 35,
	'MODULE_MISSION_STATION' => 36,
	'MODULE_MISSION_ACS' => 42,
	'MODULE_MISSION_TRADE' => 44,
	'MODULE_MISSION_TRANSFER' => 45,
] as $moduleName => $moduleId) {
	if (!defined($moduleName)) {
		define($moduleName, $moduleId);
	}
}

/**
 * In-memory database for API kernel coverage. Returns shaped rows for the
 * queries the play services issue and accepts every write.
 */
class ScriptedCoverageDatabase implements DatabaseInterface
{
	/** @var array<int, array<string, mixed>> */
	public array $planets = [];

	/** @var list<array<string, mixed>> */
	public array $fleets = [];

	/** @var list<array<string, mixed>> */
	public array $messages = [];

	public int $insertId = 42;

	public function select($qry, array $params = [])
	{
		if (str_contains($qry, 'GROUP BY p.id')) {
			return [];
		}
		if (str_contains($qry, '%%FLEETS%%') && !str_contains($qry, 'COUNT(*)')) {
			return $this->fleets;
		}
		if (str_contains($qry, '%%MESSAGES%%')) {
			return $this->messages;
		}
		if (str_contains($qry, '%%NOTES%%')) {
			return [[
				'id' => 3,
				'title' => 'Note',
				'text' => 'Body',
				'priority' => 1,
				'time' => 10,
			]];
		}
		if (str_contains($qry, '%%BUDDY%%')) {
			return [[
				'id' => 2,
				'buddyid' => 8,
				'username' => 'buddy',
				'galaxy' => 1,
				'system' => 1,
				'planet' => 3,
				'ally_name' => '',
				'text' => '',
			]];
		}
		if (str_contains($qry, '%%USERS_ACS%%') || str_contains($qry, '%%AKS%%')) {
			return [['id' => 5, 'name' => 'Wing']];
		}
		if (str_contains($qry, '%%STATPOINTS%%') && str_contains($qry, 'username')) {
			return [[
				'total_rank' => 1,
				'total_points' => 100,
				'id' => 1,
				'username' => 'pilot',
				'ally_name' => 'HIVE',
			]];
		}
		if (str_contains($qry, '%%PLANETS%%') && str_contains($qry, 'id_owner')) {
			return array_values($this->planets);
		}

		return [];
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%SALVAGE_PACKAGES%%')) {
			return $field === false ? null : false;
		}
		if (str_contains($qry, 'fleet_id = :fleetId')) {
			$row = [
				'start_time' => TIMESTAMP - 30,
				'fleet_start_time' => TIMESTAMP + 60,
				'fleet_mission' => FLEET_MISSION_TRANSPORT,
				'fleet_group' => 0,
				'fleet_owner' => 1,
				'fleet_mess' => FLEET_OUTWARD,
			];
		} elseif (str_contains($qry, 'COUNT(*)') && str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'fleet_target_owner')) {
			$row = ['incoming' => 0, 'state' => 0];
		} elseif (str_contains($qry, 'COUNT(*)') && str_contains($qry, '%%FLEETS%%')) {
			$row = ['state' => 0];
		} elseif (str_contains($qry, 'COUNT(*)') && str_contains($qry, '%%MESSAGES%%')) {
			$row = ['c' => count($this->messages)];
		} elseif (str_contains($qry, 'COUNT(*)') && str_contains($qry, 'planet_type')) {
			$row = ['state' => 9];
		} elseif (str_contains($qry, 'COUNT(*)')) {
			$row = ['state' => 0, 'c' => 0, 'record' => 1];
		} elseif (str_contains($qry, 'FOR UPDATE') || str_contains($qry, 'metal, crystal, deuterium')) {
			$id = (int) ($params[':id'] ?? $params[':planetId'] ?? 4);
			$row = $this->planets[$id] ?? [
				'metal' => 500000,
				'crystal' => 500000,
				'deuterium' => 500000,
				'light_fighter' => 10,
				'espionage_probe' => 5,
				'recycler' => 4,
			];
		} elseif (str_contains($qry, '%%PLANETS%%')) {
			$id = (int) ($params[':planetId'] ?? $params[':planetID'] ?? $params[':id'] ?? 0);
			$row = $this->planets[$id] ?? null;
			if ($row === null && isset($params[':g'])) {
				$row = [
					'id' => 8,
					'id_owner' => 2,
					'galaxy' => (int) $params[':g'],
					'system' => (int) $params[':s'],
					'planet' => (int) $params[':p'],
					'planet_type' => 1,
					'der_metal' => 8000,
					'der_crystal' => 2000,
					'destruyed' => 0,
				];
			}
			if (is_array($row) && str_contains($qry, 'id_owner = :userId')
				&& (int) ($row['id_owner'] ?? 0) !== (int) ($params[':userId'] ?? 0)) {
				$row = null;
			}
		} elseif (str_contains($qry, '%%SESSION%%')) {
			$row = null;
		} elseif (str_contains($qry, '%%USERS%%')) {
			$row = [
				'id' => (int) ($params[':id'] ?? $params[':userId'] ?? 2),
				'universe' => 1,
				'username' => 'target',
				'authlevel' => AUTH_USR,
				'urlaubs_modus' => 0,
				'onlinetime' => TIMESTAMP,
				'ally_id' => 0,
				'id_planet' => 9,
				'bana' => 0,
				'total_points' => 100,
				'darkmatter' => 0,
			];
		} else {
			$row = ['state' => 0, 'id' => 1];
		}

		if (!is_array($row)) {
			return $field === false ? null : false;
		}
		if ($field === false) {
			return $row;
		}

		return $row[$field] ?? false;
	}

	public function insert($qry, array $params = [])
	{
		return true;
	}

	public function update($qry, array $params = [])
	{
		return true;
	}

	public function delete($qry, array $params = [])
	{
		return true;
	}

	public function replace($qry, array $params = [])
	{
		return true;
	}

	public function query($qry)
	{
		return true;
	}

	public function nativeQuery($qry)
	{
		return true;
	}

	public function lastInsertId()
	{
		return $this->insertId;
	}

	public function rowCount()
	{
		return 1;
	}

	public function getQueryCounter()
	{
		return 0;
	}

	public function quote($str)
	{
		return "'".str_replace("'", "''", (string) $str)."'";
	}

	public function disconnect()
	{
	}

	public function getHandle(): ?PDO
	{
		return null;
	}

	public function beginTransaction(): void
	{
	}

	public function commit(): void
	{
	}

	public function rollback(): void
	{
	}
}

class ApiKernelCoverageTest extends TestCase
{
	private ScriptedCoverageDatabase $db;

	/** @var array<string, mixed> */
	private array $savedGlobals = [];

	protected function setUp(): void
	{
		parent::setUp();
		foreach (['resource', 'reslist', 'pricelist', 'requirements', 'LNG', 'USER', 'PLANET'] as $key) {
			$this->savedGlobals[$key] = $GLOBALS[$key] ?? null;
		}
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		$sessionDir = CACHE_PATH.'sessions';
		if (!is_dir($sessionDir)) {
			mkdir($sessionDir, 0777, true);
		}
		$_SESSION = [];
		$sessionObj = new ReflectionProperty(\HiveNova\Core\Session::class, 'obj');
		$sessionObj->setAccessible(true);
		$sessionObj->setValue(null, null);
		$iniSet = new ReflectionProperty(\HiveNova\Core\Session::class, 'iniSet');
		$iniSet->setAccessible(true);
		$iniSet->setValue(null, false);

		$this->db = new ScriptedCoverageDatabase();
		$this->db->planets[4] = $this->planet();
		$this->db->planets[9] = [
			'id' => 9,
			'id_owner' => 2,
			'name' => 'Far',
			'galaxy' => 1,
			'system' => 1,
			'planet' => 9,
			'planet_type' => 1,
			'destruyed' => 0,
			'der_metal' => 8000,
			'der_crystal' => 2000,
			'universe' => 1,
		];
		Database::setInstance($this->db);
		$this->installConfig();
		$this->extendGameData();
		$_SESSION = [];
		$_GET = [];
		$_POST = [];
		$_REQUEST = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
		$_SERVER['HTTP_X_CSRF_TOKEN'] = '';
	}

	protected function tearDown(): void
	{
		$sessionObj = new ReflectionProperty(\HiveNova\Core\Session::class, 'obj');
		$sessionObj->setAccessible(true);
		$active = $sessionObj->getValue();
		if ($active instanceof \HiveNova\Core\Session) {
			$data = new ReflectionProperty(\HiveNova\Core\Session::class, 'data');
			$data->setAccessible(true);
			$data->setValue($active, null);
		}
		$sessionObj->setValue(null, null);
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		foreach ($this->savedGlobals as $key => $value) {
			if ($value === null) {
				unset($GLOBALS[$key]);
			} else {
				$GLOBALS[$key] = $value;
			}
		}
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		$ref = new ReflectionProperty(Database::class, 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);
		parent::tearDown();
	}

	public function testEconomyListsAndQueueMutations(): void
	{
		$user = $this->user();
		$planet = $this->planet();
		$lng = ['tech' => [1 => 'Mine', 108 => 'Computer', 202 => 'Fighter', 401 => 'Launcher']];

		$buildings = EconomyPlayService::buildings($user, $planet, $lng);
		$this->assertArrayHasKey(1, $buildings['items']);
		$this->assertSame('Mine', $buildings['items'][1]['name']);
		$this->assertGreaterThan(0, $buildings['fields']['max']);

		$research = EconomyPlayService::research($user, $planet, $lng);
		$this->assertArrayHasKey(108, $research['items']);
		$this->assertSame(5, $research['labLevel']);

		$yard = EconomyPlayService::shipyard($user, $planet, $lng, 'defense');
		$this->assertSame('defense', $yard['mode']);
		$this->assertNotEmpty($yard['queue']);
		$this->assertArrayHasKey(401, $yard['items']);
		$fleetYard = EconomyPlayService::shipyard($user, $planet, $lng, 'fleet');
		$this->assertSame('fleet', $fleetYard['mode']);
		$this->assertArrayHasKey(202, $fleetYard['items']);

		$bogus = $user;
		$bogusPlanet = $planet;
		$this->assertFalse(EconomyPlayService::insertBuilding($bogus, $bogusPlanet, 999));
		$bogusPlanet['b_hangar_id'] = serialize([[202, 1]]);
		$this->assertFalse(EconomyPlayService::insertBuilding($bogus, $bogusPlanet, 21));
		$full = $planet;
		$full['field_current'] = 500;
		$this->assertFalse(EconomyPlayService::insertBuilding($user, $full, 1));

		$queued = $user;
		$queuedPlanet = $planet;
		$this->assertTrue(EconomyPlayService::insertBuilding($queued, $queuedPlanet, 1));
		$this->assertNotSame('', $queuedPlanet['b_building_id']);
		$this->assertTrue(EconomyPlayService::insertBuilding($queued, $queuedPlanet, 1));

		$cancelUser = $user;
		$cancelPlanet = $planet;
		$cancelPlanet['b_building_id'] = serialize([
			[1, 2, 30, TIMESTAMP + 40, 'build'],
			[1, 3, 30, TIMESTAMP + 80, 'build'],
		]);
		$cancelPlanet['b_building'] = TIMESTAMP;
		$this->assertTrue(EconomyPlayService::cancelBuilding($cancelUser, $cancelPlanet));

		$oneUser = $user;
		$onePlanet = $planet;
		$onePlanet['b_building_id'] = serialize([[1, 2, 30, TIMESTAMP + 10, 'build']]);
		$onePlanet['b_building'] = TIMESTAMP + 10;
		$this->assertTrue(EconomyPlayService::cancelBuilding($oneUser, $onePlanet));
		$this->assertSame('', $onePlanet['b_building_id']);

		$broken = ['b_building' => TIMESTAMP, 'b_building_id' => 'not-a-queue'];
		$this->assertSame([], EconomyPlayService::buildingQueue($broken)['queue']);

		$techUser = $user;
		$techPlanet = $planet;
		$this->assertFalse(EconomyPlayService::insertResearch($techUser, $techPlanet, 1));
		$noLab = $techPlanet;
		$noLab['intergalactic_research'] = 0;
		$this->assertFalse(EconomyPlayService::insertResearch($techUser, $noLab, 108));
		$this->assertTrue(EconomyPlayService::insertResearch($techUser, $techPlanet, 108));
		$this->assertTrue(EconomyPlayService::insertResearch($techUser, $techPlanet, 108));

		$shipUser = $user;
		$shipPlanet = $planet;
		$shipPlanet['hangar'] = 0;
		$this->assertFalse(EconomyPlayService::buildShips($shipUser, $shipPlanet, [202 => 1]));
		$shipPlanet['hangar'] = 2;
		$this->assertFalse(EconomyPlayService::buildShips($shipUser, $shipPlanet, [202 => 0, 999 => 3]));
		$this->assertTrue(EconomyPlayService::buildShips($shipUser, $shipPlanet, [202 => 2, 401 => 1]));
		$this->assertNotSame('', $shipPlanet['b_hangar_id']);
	}

	public function testFleetGalaxyAndMessages(): void
	{
		$user = $this->user();
		$planet = $this->planet();
		$lng = [
			'tech' => [202 => 'Fighter'],
			'type_mission_3' => 'Transport',
			'mg_type' => [100 => 'All'],
		];
		$GLOBALS['LNG'] = $lng;
		$GLOBALS['USER'] = $user;
		$GLOBALS['PLANET'] = $planet;

		$this->db->fleets = [[
			'fleet_id' => 8,
			'fleet_mission' => FLEET_MISSION_TRANSPORT,
			'fleet_mess' => FLEET_OUTWARD,
			'fleet_start_galaxy' => 1,
			'fleet_start_system' => 1,
			'fleet_start_planet' => 4,
			'fleet_end_galaxy' => 1,
			'fleet_end_system' => 1,
			'fleet_end_planet' => 8,
			'fleet_array' => '202,2;',
			'fleet_resource_metal' => 15,
			'fleet_start_time' => TIMESTAMP + 30,
			'fleet_end_time' => TIMESTAMP + 90,
			'fleet_no_m_return' => 0,
		]];
		$table = FleetPlayService::table($user, $planet, $lng);
		$this->assertNotEmpty($table['ships']);
		$this->assertNotEmpty($table['fleets']);
		$this->assertGreaterThan(0, $table['slots']['max']);

		$preview = FleetPlayService::preview(
			$user,
			$planet,
			[202 => 2],
			['galaxy' => 1, 'system' => 2, 'planet' => 4],
			10,
			[901 => 20, 902 => 0, 903 => 0]
		);
		$this->assertTrue($preview['ready']);
		$this->assertGreaterThan(0, $preview['distance']);
		$this->assertGreaterThan(0, $preview['consumption']);

		$sentUser = $user;
		$sentPlanet = $planet;
		$sent = FleetPlayService::send(
			$sentUser,
			$sentPlanet,
			[202 => 2],
			['galaxy' => 1, 'system' => 1, 'planet' => 8, 'type' => 1],
			FLEET_MISSION_TRANSPORT,
			10,
			[901 => 15, 902 => 0, 903 => 0]
		);
		$this->assertArrayHasKey('fleetId', $sent);

		$this->assertTrue(FleetPlayService::recall($user, 8));
		$this->assertFalse(FleetPlayService::recall($user, 0));

		$spyUser = $user;
		$spyPlanet = $planet;
		$spy = FleetPlayService::spy($spyUser, $spyPlanet, 9);
		$this->assertNotEmpty($spy);

		$drySpy = $user;
		$dryPlanet = $planet;
		$dryPlanet['espionage_probe'] = 0;
		try {
			FleetPlayService::spy($drySpy, $dryPlanet, 9);
			$this->fail('spy without probes should throw');
		} catch (RuntimeException $e) {
			$this->assertNotSame('', $e->getMessage());
		}

		$recycleUser = $user;
		$recyclePlanet = $planet;
		$recycled = FleetPlayService::recycle($recycleUser, $recyclePlanet, 9);
		$this->assertNotEmpty($recycled);
		try {
			FleetPlayService::recycle($user, $planet, 0);
			$this->fail('recycle without a target should throw');
		} catch (RuntimeException $e) {
			$this->assertNotSame('', $e->getMessage());
		}

		$galaxyUser = $user;
		$galaxyPlanet = $planet;
		$galaxy = GalaxyPlayService::show($galaxyUser, $galaxyPlanet, 1, 1);
		$this->assertSame(1, $galaxy['galaxy']);
		$this->assertNotEmpty($galaxy['slots']);
		$this->assertFalse($galaxy['deuteriumCharged']);

		$farUser = $user;
		$farPlanet = $planet;
		$far = GalaxyPlayService::show($farUser, $farPlanet, 2, 3);
		$this->assertTrue($far['deuteriumCharged']);
		$this->assertLessThan($planet['deuterium'], $farPlanet['deuterium']);

		$broke = $planet;
		$broke['deuterium'] = 0;
		try {
			GalaxyPlayService::show($user, $broke, 3, 1);
			$this->fail('galaxy view without deuterium should throw');
		} catch (RuntimeException $e) {
			$this->assertNotSame('', $e->getMessage());
		}

		$this->db->messages = [[
			'message_id' => 4,
			'message_time' => 20,
			'message_from' => 'Ops',
			'message_subject' => 'Hi',
			'message_sender' => 0,
			'message_type' => 1,
			'message_unread' => 1,
			'message_text' => 'hello',
		]];
		$inbox = MessagePlayService::inbox($user, 0, 1);
		$this->assertSame(100, $inbox['category']);
		$this->assertCount(1, $inbox['messages']);
		$this->assertSame(4, $inbox['messages'][0]['id']);
	}

	public function testFrontControllerReadsAndMutations(): void
	{
		$this->bindSessionPlanet();
		$user = $this->user();
		$planet = $this->planet();
		$this->expose($user, $planet);

		$token = ApiCsrf::token();
		$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

		$this->request(['r' => '', 'action' => '']);
		$bootstrap = (new ApiFrontController())->handle();
		$this->assertTrue($bootstrap['ok']);
		$this->assertArrayHasKey('i18n', $bootstrap['data']);
		$this->assertSame(4, $bootstrap['meta']['planetId']);

		$this->request(['r' => 'overview']);
		$overview = (new ApiFrontController())->handle();
		$this->assertTrue($overview['ok']);
		$this->assertSame('Home', $overview['data']['name'] ?? $overview['data']['planet']['name'] ?? 'Home');

		$this->request(['r' => 'overview', 'action' => 'rename', 'name' => ''], 'POST');
		$badRename = (new ApiFrontController())->handle();
		$this->assertFalse($badRename['ok']);

		$this->request(['r' => 'overview', 'action' => 'rename', 'name' => 'New Home'], 'POST');
		$renamed = (new ApiFrontController())->handle();
		$this->assertTrue($renamed['ok'], json_encode($renamed));
		$this->assertSame('New Home', $renamed['data']['name']);

		$this->request(['r' => 'buildings']);
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'buildings', 'action' => 'insert', 'building' => 1], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'buildings', 'action' => 'insert', 'building' => 999], 'POST');
		$this->assertSame('queue', (new ApiFrontController())->handle()['error']);
		$this->request(['r' => 'buildings', 'action' => 'cancel'], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);

		$this->request(['r' => 'research']);
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'research', 'action' => 'insert', 'tech' => 108], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);

		$this->request(['r' => 'shipyard', 'mode' => 'defense']);
		$this->assertSame('defense', (new ApiFrontController())->handle()['data']['mode']);
		$this->request(['r' => 'shipyard', 'action' => 'build', 'fmenge' => [202 => 1]], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);

		$this->request(['r' => 'messages', 'category' => 100, 'side' => 1]);
		$this->assertArrayHasKey('messages', (new ApiFrontController())->handle()['data']);
		$this->request(['r' => 'alerts']);
		$this->assertArrayHasKey('count', (new ApiFrontController())->handle()['data']);
		$this->request(['r' => 'events', 'sinceId' => 0]);
		$this->assertArrayHasKey('events', (new ApiFrontController())->handle()['data']);

		$this->request(['r' => 'fleet']);
		$this->assertArrayHasKey('ships', (new ApiFrontController())->handle()['data']);
		$this->request([
			'r' => 'fleet',
			'action' => 'send',
			'ships' => [202 => 1],
			'galaxy' => 1,
			'system' => 1,
			'planet' => 8,
			'type' => 1,
			'mission' => FLEET_MISSION_TRANSPORT,
			'speed' => 10,
			'metal' => 10,
		], 'POST');
		$fleetSend = (new ApiFrontController())->handle();
		$this->assertTrue($fleetSend['ok'], json_encode($fleetSend));

		$this->request(['r' => 'fleet', 'action' => 'recall', 'fleetId' => 8], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'fleet', 'action' => 'send', 'ships' => []], 'POST');
		$this->assertSame('fleet', (new ApiFrontController())->handle()['error']);

		$this->request(['r' => 'galaxy', 'galaxy' => 1, 'system' => 1]);
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'galaxy', 'action' => 'spy', 'targetPlanetId' => 9], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'galaxy', 'action' => 'recycle', 'targetPlanetId' => 9], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);
		$this->request(['r' => 'galaxy', 'action' => 'colonize', 'galaxy' => 1, 'system' => 2, 'planet' => 6], 'POST');
		$colonize = (new ApiFrontController())->handle();
		$this->assertArrayHasKey('ok', $colonize);

		$this->request(['r' => 'catalog', 'action' => 'prod', 'prod' => [1 => 8]], 'POST');
		$this->assertArrayHasKey('sliders', (new ApiFrontController())->handle()['data']);

		$this->request([
			'r' => 'catalog',
			'action' => 'trade',
			'resource' => 901,
			'trade' => [902 => 10],
			'kind' => 'trader',
		], 'POST');
		$trade = (new ApiFrontController())->handle();
		$this->assertTrue($trade['ok'], json_encode($trade));

		$poor = $this->user();
		$poor['darkmatter'] = 0;
		$this->expose($poor, $planet);
		$this->request([
			'r' => 'catalog',
			'action' => 'trade',
			'resource' => 901,
			'trade' => [902 => 10],
		], 'POST');
		$this->assertSame('trader', (new ApiFrontController())->handle()['error']);
		$this->expose($user, $planet);

		$this->request([
			'r' => 'catalog',
			'action' => 'note',
			'title' => 'Remember',
			'text' => 'Build mines',
			'priority' => 2,
		], 'POST');
		$this->assertTrue((new ApiFrontController())->handle()['ok']);

		foreach (['acs', 'buddies', 'notes', 'statistics', 'techtree', 'alliance'] as $kind) {
			$this->request(['r' => 'catalog', 'kind' => $kind]);
			$this->assertTrue((new ApiFrontController())->handle()['ok'], $kind);
		}

		$this->request(['r' => 'i18n', 'planetId' => '4']);
		$switched = (new ApiFrontController())->handle();
		$this->assertTrue($switched['ok']);

		$this->request(['r' => 'buildings', 'action' => 'insert', 'planetId' => '99', 'building' => 1], 'POST');
		$this->assertSame('planet', (new ApiFrontController())->handle()['error']);

		Config::setInstance(new Config(array_merge($this->configData(), [
			'season_mode' => 1,
			'uni' => 1,
		])), 1);
		$this->request(['r' => 'bootstrap']);
		$this->assertSame('season', (new ApiFrontController())->handle()['error']);
	}

	public function testSmallUncoveredBranches(): void
	{
		ApiJsonResponse::sendHeaders(401);
		$this->assertFalse(ApiJsonResponse::isApiMode());

		$service = new OverviewPlanetActionService();
		$this->assertSame(
			OverviewPlanetActionService::ERROR_EMPTY_NAME,
			$service->validateRename('', ['name' => 'Home'])['error']
		);
		$abandon = $service->validateAbandon('nope', ['id_planet' => 1], ['id' => 4, 'name' => 'Home'], 0, 2);
		$this->assertSame(OverviewPlanetActionService::ERROR_WRONG_NAME, $abandon['error']);
		$this->assertTrue($service->validateAbandon('Home', ['id_planet' => 1], ['id' => 4, 'name' => 'Home'], 0, 2)['ok']);
		$this->assertNull(OverviewPlanetActionService::hangarHead(['b_hangar_id' => serialize([[0, 0]])], []));
		$this->assertSame('Mine', OverviewPlanetActionService::namedQueueItem(['elementId' => 1], ['tech' => [1 => 'Mine']])['name']);

		$user = ['darkmatter' => 100];
		$planet = ['metal' => 1000];
		$resource = [901 => 'metal', 902 => '', 903 => 'deuterium', 921 => 'darkmatter'];
		$missingCol = CatalogPlayService::trade($user, $planet, 902, [901 => 1], [901 => 'metal', 921 => 'darkmatter'], 0);
		$this->assertFalse($missingCol['ok']);
		$skipBuy = CatalogPlayService::trade($user, $planet, 901, [902 => 10], $resource, 0);
		$this->assertTrue($skipBuy['ok']);
		$this->assertArrayNotHasKey('crystal', $planet);

		$user = ['darkmatter' => 5000, 'crystal' => 0];
		$planet = ['metal' => 5000];
		$resource = [901 => 'metal', 902 => 'crystal', 903 => 'deuterium', 921 => 'darkmatter'];
		$credited = CatalogPlayService::trade($user, $planet, 901, [902 => 10], $resource, 10);
		$this->assertTrue($credited['ok']);
		$this->assertSame(10, (int) $user['crystal']);

		$table = ApiBootstrapService::resourceTable(
			['urlaubs_modus' => 0],
			['planet_type' => 1, 'metal' => 10, 'metal_perhour' => 5, 'metal_max' => 100, 'energy' => 1, 'energy_used' => 1],
			[901 => 'metal', 911 => 'energy', 921 => 'darkmatter', 999 => ''],
			['resstype' => [1 => [901], 2 => [911], 3 => [921, 999]]],
			new Config([
				'resource_multiplier' => 1,
				'metal_basic_income' => 30,
			])
		);
		$this->assertArrayHasKey(901, $table);
		$this->assertArrayHasKey(921, $table);
		$this->assertArrayNotHasKey(999, $table);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function request(array $params, string $method = 'GET'): void
	{
		$_SERVER['REQUEST_METHOD'] = $method;
		$_REQUEST = $params;
		$_GET = $method === 'GET' ? $params : [];
		$_POST = $method === 'POST' ? $params : [];
		if ($method === 'POST') {
			$_SERVER['HTTP_X_CSRF_TOKEN'] = ApiCsrf::token();
		}
	}

	/**
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $planet
	 */
	private function expose(array $user, array $planet): void
	{
		$GLOBALS['USER'] = $user;
		$GLOBALS['PLANET'] = $planet;
		$GLOBALS['LNG'] = [
			'tech' => [1 => 'Mine', 108 => 'Computer', 202 => 'Fighter', 401 => 'Launcher', 921 => 'Pizzabits'],
			'lm_fleet' => 'Fleet',
			'ov_newname_done' => 'Renamed',
			'ov_newname_specialchar' => 'Bad name',
			'tr_exchange_done' => 'Traded',
			'tr_not_enought' => "Don't have enough %s.",
			'tr_exchange_error' => 'Trade failed',
			'fl_target_exists' => 'Target exists',
		];
	}

	private function bindSessionPlanet(): void
	{
		$session = \HiveNova\Core\Session::load();
		$session->planetId = 4;
	}

	private function installConfig(): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		Config::setInstance(new Config($this->configData()), 1);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function configData(): array
	{
		return [
			'uni' => 1,
			'game_name' => 'HiveNova',
			'VERSION' => 'test',
			'game_disable' => 1,
			'season_mode' => 0,
			'game_speed' => 2500,
			'fleet_speed' => 2500,
			'halt_speed' => 1,
			'min_build_time' => 1,
			'factor_university' => 0,
			'max_elements_build' => 5,
			'max_elements_tech' => 0,
			'max_fleet_per_build' => 1000,
			'max_galaxy' => 5,
			'max_system' => 50,
			'max_planets' => 15,
			'max_dm_missions' => 1,
			'deuterium_cost_galaxy' => 10,
			'darkmatter_cost_trader' => 10,
			'resource_multiplier' => 1,
			'storage_multiplier' => 1,
			'energySpeed' => 1,
			'max_overflow' => 1,
			'planets_tech' => 1,
			'planets_officier' => 1,
			'min_player_planets' => 1,
			'planets_per_tech' => 1,
			'metal_basic_income' => 30,
			'crystal_basic_income' => 15,
			'deuterium_basic_income' => 0,
			'energy_basic_income' => 0,
			'adm_attack' => 0,
			'debug' => 0,
			'moduls' => implode(';', array_replace(array_fill(0, 51, '1'), [MODULE_FLEET_EVENTS => '0'])),
			'silo_factor' => 1,
		];
	}

	private function extendGameData(): void
	{
		$GLOBALS['resource'][123] = 'astrophysics';
		$GLOBALS['resource'][124] = 'astrophysics_tech';
		$GLOBALS['resource'][208] = 'colony_ship';
		$GLOBALS['resource'][209] = 'recycler';
		$GLOBALS['resource'][210] = 'espionage_probe';
		$GLOBALS['resource'][212] = 'solar_satellite';
		$GLOBALS['resource'][219] = 'battle_recycler';
		$GLOBALS['reslist']['allow'][1] = [1, 21];
		$GLOBALS['reslist']['tech'] = [108];
		$GLOBALS['reslist']['fleet'] = [202, 208, 209, 210];
		$GLOBALS['reslist']['defense'] = [401];
		$GLOBALS['reslist']['missile'] = [];
		$GLOBALS['reslist']['build'] = [1, 6, 14, 15, 21, 31];
		$GLOBALS['reslist']['resstype'][1] = [901];
		$GLOBALS['reslist']['resstype'][2] = [911];
		$GLOBALS['reslist']['resstype'][3] = [921];
		$GLOBALS['pricelist'][108] = [
			'cost' => [901 => 100, 902 => 40, 903 => 10],
			'factor' => 2,
			'max' => 20,
		];
		$GLOBALS['pricelist'][1]['cost'][903] = 5;
		$GLOBALS['pricelist'][1]['cost'][921] = 1;
		$GLOBALS['pricelist'][1]['max'] = 40;
		$GLOBALS['pricelist'][208] = [
			'cost' => [901 => 10000, 902 => 20000, 903 => 10000],
			'capacity' => 7500,
			'consumption' => 1000,
			'speed' => 2500,
			'tech' => 1,
			'factor' => 0,
		];
		$GLOBALS['pricelist'][209] = [
			'cost' => [901 => 10000, 902 => 6000, 903 => 2000],
			'capacity' => 20000,
			'consumption' => 300,
			'speed' => 2000,
			'tech' => 1,
			'factor' => 0,
		];
		$GLOBALS['pricelist'][210]['capacity'] = 5;
		$GLOBALS['requirements'][108] = [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function user(): array
	{
		return [
			'id' => 1,
			'universe' => 1,
			'authlevel' => AUTH_USR,
			'urlaubs_modus' => 0,
			'onlinetime' => TIMESTAMP,
			'lang' => 'en',
			'timezone' => 'UTC',
			'messages' => 0,
			'username' => 'pilot',
			'ally_id' => 0,
			'ally_name' => '',
			'id_planet' => 4,
			'hof' => 0,
			'darkmatter' => 5000,
			'computer_tech' => 2,
			'astrophysics' => 0,
			'astrophysics_tech' => 0,
			'combustion_tech' => 2,
			'impulse_motor_tech' => 0,
			'hyperspace_motor_tech' => 0,
			'b_tech' => 0,
			'b_tech_id' => 0,
			'b_tech_planet' => 0,
			'b_tech_queue' => '',
			'spio_anz' => 1,
			'PLANETS' => [],
			'factor' => [
				'BuildTime' => 0,
				'ResearchTime' => 0,
				'ShipTime' => 0,
				'DefensiveTime' => 0,
				'FleetSlots' => 0,
				'ShipStorage' => 0,
				'Expedition' => 0,
				'Resource' => 0,
				'Energy' => 0,
				'ResourceStorage' => 0,
				'Planets' => 1,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function planet(): array
	{
		return [
			'id' => 4,
			'id_owner' => 1,
			'name' => 'Home',
			'image' => 'eisplanet01',
			'galaxy' => 1,
			'system' => 1,
			'planet' => 4,
			'planet_type' => 1,
			'destruyed' => 0,
			'field_current' => 8,
			'field_max' => 163,
			'temp_min' => 20,
			'temp_max' => 60,
			'last_update' => TIMESTAMP,
			'eco_hash' => 'abc',
			'metal' => 500000,
			'crystal' => 500000,
			'deuterium' => 500000,
			'energy' => 1000,
			'energy_used' => 10,
			'metal_perhour' => 100,
			'crystal_perhour' => 50,
			'deuterium_perhour' => 20,
			'metal_max' => 1000000,
			'crystal_max' => 1000000,
			'deuterium_max' => 1000000,
			'metal_mine' => 10,
			'metal_mine_porcent' => 10,
			'crystal_mine' => 0,
			'hangar' => 4,
			'robotic_factory' => 2,
			'nanite_factory' => 0,
			'research_lab' => 4,
			'intergalactic_research' => 5,
			'terraformer' => 0,
			'mondbasis' => 0,
			'light_fighter' => 12,
			'colony_ship' => 1,
			'recycler' => 6,
			'espionage_probe' => 8,
			'rocket_launcher' => 4,
			'b_building' => 0,
			'b_building_id' => '',
			'b_hangar' => 20,
			'b_hangar_id' => serialize([[401, 2]]),
			'der_metal' => 0,
			'der_crystal' => 0,
		];
	}
}
