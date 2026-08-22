<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\Universe;
use HiveNova\Page\Game\ShowCommanderAjaxPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

final class TestableShowCommanderAjaxPage extends ShowCommanderAjaxPage
{
	public ?array $jsonResponse = null;

	public function __construct()
	{
	}

	protected function sendJSON($data): void
	{
		$this->jsonResponse = $data;
	}
}

class ShowCommanderAjaxPageTest extends TestCase
{
	use SwapDatabaseInstance;

	private CommanderDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
		global $USER, $PLANET, $LNG;
		$USER = ['id' => 4, 'universe' => 1];
		$PLANET = ['id' => 9];
		$LNG = [];
		$_SESSION = [];
		$_REQUEST = [];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_HOST'] = 'moon.hive.pizza';
		$_SERVER['HTTP_ORIGIN'] = 'https://moon.hive.pizza';

		$this->db = new CommanderDatabaseStub();
		$this->swapDatabaseInstance($this->db);
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', array_fill(0, 49, 1)),
		]), 1);
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, [1]);
	}

	protected function tearDown(): void
	{
		unset($GLOBALS['USER'], $GLOBALS['PLANET'], $GLOBALS['LNG']);
		$_SESSION = [];
		$_REQUEST = [];
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	private function page(): TestableShowCommanderAjaxPage
	{
		return new TestableShowCommanderAjaxPage();
	}

	public function testStatusReturnsJsonWithoutMutation(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$page = $this->page();
		$page->status();
		$this->assertTrue($page->jsonResponse['ok']);
		$this->assertTrue($page->jsonResponse['enabled']);
	}

	public function testSelectDirectiveSucceedsWithCsrf(): void
	{
		$token = DirectiveService::issueCsrfToken();
		$_REQUEST['token'] = $token;
		$_REQUEST['directive_key'] = DirectiveCatalog::INDUSTRIAL;
		$page = $this->page();
		$page->selectDirective();
		$this->assertTrue($page->jsonResponse['ok']);
		$this->assertSame(DirectiveCatalog::INDUSTRIAL, $page->jsonResponse['directive_key']);
	}

	public function testSelectDirectiveRejectsUnknownKey(): void
	{
		$_REQUEST['token'] = DirectiveService::issueCsrfToken();
		$_REQUEST['directive_key'] = 'nope';
		$page = $this->page();
		$page->selectDirective();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame(DirectiveService::ERROR_UNKNOWN, $page->jsonResponse['error']);
	}

	public function testSelectDirectiveRejectsReselect(): void
	{
		DirectiveService::selectDirective(4, 1, DirectiveCatalog::TRADE);
		$_REQUEST['token'] = DirectiveService::issueCsrfToken();
		$_REQUEST['directive_key'] = DirectiveCatalog::INDUSTRIAL;
		$page = $this->page();
		$page->selectDirective();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame(DirectiveService::ERROR_LOCKED, $page->jsonResponse['error']);
	}

	public function testSelectDirectiveRejectsCrossOrigin(): void
	{
		$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
		$_REQUEST['token'] = DirectiveService::issueCsrfToken();
		$_REQUEST['directive_key'] = DirectiveCatalog::INDUSTRIAL;
		$page = $this->page();
		$page->selectDirective();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame('cross_origin', $page->jsonResponse['error']);
	}

	public function testResolveBranchHttpLayer(): void
	{
		$this->db->planets[9] = ['id' => 9, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];
		ExpeditionChoiceService::createPendingBranch(22, 4, 9, 'resource_find', 'balanced', [
			'metal' => 400,
			'crystal' => 0,
			'deuterium' => 0,
		], []);
		$_REQUEST['token'] = DirectiveService::issueCsrfToken();
		$_REQUEST['fleet_id'] = 22;
		$_REQUEST['branch_key'] = 'balanced';
		$page = $this->page();
		$page->resolveBranch();
		$this->assertTrue($page->jsonResponse['ok']);

		$_REQUEST['token'] = DirectiveService::issueCsrfToken();
		$page = $this->page();
		$page->resolveBranch();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame(ExpeditionChoiceService::ERROR_ALREADY_RESOLVED, $page->jsonResponse['error']);
	}

	public function testResolveBranchWrongUser(): void
	{
		ExpeditionChoiceService::createPendingBranch(23, 99, 9, 'resource_find', 'balanced', [
			'metal' => 10,
			'crystal' => 0,
			'deuterium' => 0,
		], []);
		$_REQUEST['token'] = DirectiveService::issueCsrfToken();
		$_REQUEST['fleet_id'] = 23;
		$_REQUEST['branch_key'] = 'balanced';
		$page = $this->page();
		$page->resolveBranch();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame(ExpeditionChoiceService::ERROR_FORBIDDEN, $page->jsonResponse['error']);
	}

	public function testModuleDisabledHidesStatus(): void
	{
		$modules = array_fill(0, 49, 1);
		$modules[MODULE_COMMANDER] = 0;
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', $modules),
		]), 1);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$page = $this->page();
		$page->status();
		$this->assertTrue($page->jsonResponse['ok']);
		$this->assertFalse($page->jsonResponse['enabled']);
	}
}
