<?php

use HiveNova\Core\ApiCsrf;
use HiveNova\Core\ApiFrontController;
use HiveNova\Core\Config;
use PHPUnit\Framework\TestCase;

class ApiFrontControllerTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$_GET = [];
		$_POST = [];
		$_REQUEST = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
		$_SERVER['HTTP_X_CSRF_TOKEN'] = '';
		$GLOBALS['USER'] = ['id' => 1, 'urlaubs_modus' => 0, 'authlevel' => AUTH_USR];
		$GLOBALS['PLANET'] = ['id' => 4, 'name' => 'Home', 'galaxy' => 1, 'system' => 1, 'planet' => 1];
		$GLOBALS['LNG'] = ['lm_fleet' => 'Fleet'];
		Config::setInstance(new Config([
			'uni' => 1,
			'game_name' => 'HiveNova',
			'darkmatter_cost_trader' => 2500,
		]), 1);
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		$_SESSION = [];
	}

	protected function tearDown(): void
	{
		$ref = new \ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		parent::tearDown();
	}

	private function request(array $params, string $method = 'GET'): void
	{
		$_SERVER['REQUEST_METHOD'] = $method;
		$_REQUEST = $params;
		if ($method === 'POST') {
			$_POST = $params;
		} else {
			$_GET = $params;
		}
	}

	public function testUnknownModule(): void
	{
		$this->request(['r' => 'alliance']);
		$result = (new ApiFrontController())->handle();
		$this->assertFalse($result['ok']);
		$this->assertSame('module', $result['error']);
		$this->assertSame(404, $result['status']);
	}

	public function testUnknownAction(): void
	{
		$this->request(['r' => 'overview', 'action' => 'delete']);
		$result = (new ApiFrontController())->handle();
		$this->assertFalse($result['ok']);
		$this->assertSame('action', $result['error']);
	}

	public function testPublicConfig(): void
	{
		$this->request(['r' => 'config']);
		$result = (new ApiFrontController())->handle();
		$this->assertTrue($result['ok']);
		$this->assertSame('HiveNova', $result['data']['gameName']);
		$this->assertSame(0, $result['meta']['planetId']);
	}

	public function testI18nPoll(): void
	{
		$this->request(['r' => 'i18n']);
		$result = (new ApiFrontController())->handle();
		$this->assertTrue($result['ok']);
		$this->assertSame('Fleet', $result['data']['lm_fleet']);
	}

	public function testMutateRequiresPost(): void
	{
		$this->request(['r' => 'catalog', 'action' => 'prod']);
		$result = (new ApiFrontController())->handle();
		$this->assertSame('method', $result['error']);
		$this->assertSame(405, $result['status']);
	}

	public function testInvalidPlanetIdOnPoll(): void
	{
		$this->request(['r' => 'i18n', 'planetId' => 'nope']);
		$result = (new ApiFrontController())->handle();
		$this->assertSame('planet', $result['error']);
		$this->assertSame(422, $result['status']);
	}

	public function testVacationBlocksMutate(): void
	{
		$GLOBALS['USER']['urlaubs_modus'] = 1;
		$token = ApiCsrf::token();
		$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
		$this->request(['r' => 'catalog', 'action' => 'prod'], 'POST');
		$result = (new ApiFrontController())->handle();
		$this->assertSame('vacation', $result['error']);
		$this->assertSame(403, $result['status']);
	}

	public function testCsrfRejectsCrossSiteMutate(): void
	{
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
		$this->request(['r' => 'catalog', 'action' => 'note'], 'POST');
		$result = (new ApiFrontController())->handle();
		$this->assertSame('csrf', $result['error']);
	}

	public function testFleetPreviewIsPollPayload(): void
	{
		$this->request(['r' => 'fleet', 'action' => 'preview', 'speed' => '10']);
		$result = (new ApiFrontController())->handle();
		$this->assertTrue($result['ok']);
		$this->assertFalse($result['data']['ready']);
		$this->assertSame(0, $result['data']['consumption']);
	}

	public function testCatalogKinds(): void
	{
		$this->request(['r' => 'catalog', 'kind' => 'officers']);
		$officers = (new ApiFrontController())->handle();
		$this->assertTrue($officers['ok']);
		$this->assertArrayHasKey('items', $officers['data']);

		$this->request(['r' => 'catalog', 'kind' => 'resources']);
		$this->assertArrayHasKey('sliders', (new ApiFrontController())->handle()['data']);

		$this->request(['r' => 'catalog', 'kind' => 'missiles']);
		$this->assertArrayHasKey('interceptor', (new ApiFrontController())->handle()['data']);

		$this->request(['r' => 'catalog', 'kind' => 'phalanx']);
		$this->assertArrayHasKey('range', (new ApiFrontController())->handle()['data']);

		$this->request(['r' => 'catalog', 'kind' => 'alliance']);
		$this->assertArrayHasKey('member', (new ApiFrontController())->handle()['data']);

		$this->request(['r' => 'catalog', 'kind' => 'trader']);
		$trader = (new ApiFrontController())->handle();
		$this->assertSame(2500, $trader['data']['cost']);
		$this->assertCount(3, $trader['data']['items']);
		$this->assertArrayHasKey('canCall', $trader['data']);

		$this->request(['r' => 'catalog', 'kind' => 'empire']);
		$this->assertSame([], (new ApiFrontController())->handle()['data']['bodies']);
	}
}
