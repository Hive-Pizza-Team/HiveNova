<?php

declare(strict_types=1);

use HiveNova\Core\WhatsNewCsrf;
use HiveNova\Page\Game\ShowWhatsNewPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class WhatsNewDismissRecordingFakeDatabase extends FakeDatabase
{
	/** @var list<array{sql: string, params: array<string, mixed>}> */
	public array $updateCalls = [];

	public function update($qry, array $params = [])
	{
		$this->updateCalls[] = ['sql' => $qry, 'params' => $params];
		return parent::update($qry, $params);
	}
}

final class TestableShowWhatsNewPage extends ShowWhatsNewPage
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

class ShowWhatsNewDismissTest extends TestCase
{
	use SwapDatabaseInstance;

	private WhatsNewDismissRecordingFakeDatabase $db;

	protected function setUp(): void
	{
		parent::setUp();
		$_SESSION = [];
		$_REQUEST = [];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_HOST'] = 'moon.hive.pizza';
		$_SERVER['HTTP_ORIGIN'] = 'https://moon.hive.pizza';
		global $USER;
		$USER = [
			'id' => 7,
			'last_seen_release' => 'v1.0.0',
		];
		$this->db = new WhatsNewDismissRecordingFakeDatabase();
		$this->swapDatabaseInstance($this->db);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	private function page(): TestableShowWhatsNewPage
	{
		return new TestableShowWhatsNewPage();
	}

	public function test_rejects_non_post(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_REQUEST['token'] = WhatsNewCsrf::issueCsrfToken();
		$page = $this->page();
		$page->dismiss();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame('method_not_allowed', $page->jsonResponse['error']);
		$this->assertSame([], $this->db->updateCalls);
	}

	public function test_rejects_cross_origin(): void
	{
		$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
		$_REQUEST['token'] = WhatsNewCsrf::issueCsrfToken();
		$page = $this->page();
		$page->dismiss();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame('cross_origin', $page->jsonResponse['error']);
		$this->assertSame([], $this->db->updateCalls);
	}

	public function test_rejects_bad_token(): void
	{
		WhatsNewCsrf::issueCsrfToken();
		$_REQUEST['token'] = 'bad';
		$page = $this->page();
		$page->dismiss();
		$this->assertFalse($page->jsonResponse['ok']);
		$this->assertSame('invalid_token', $page->jsonResponse['error']);
		$this->assertSame([], $this->db->updateCalls);
	}

	public function test_missing_column_returns_ok_false_without_sql(): void
	{
		global $USER;
		$USER = ['id' => 7];
		$_REQUEST['token'] = WhatsNewCsrf::issueCsrfToken();
		$page = $this->page();
		$page->dismiss();
		$this->assertSame(['ok' => false], $page->jsonResponse);
		$this->assertSame([], $this->db->updateCalls);
	}

	public function test_marks_server_latest_version(): void
	{
		$_REQUEST['token'] = WhatsNewCsrf::issueCsrfToken();
		$_REQUEST['version'] = 'v9.9.9';
		$page = $this->page();
		$page->dismiss();
		$this->assertTrue($page->jsonResponse['ok']);
		$seen = $page->jsonResponse['seen'];
		$this->assertNotNull($seen);
		$this->assertNotSame('v9.9.9', $seen);
		$this->assertCount(1, $this->db->updateCalls);
		$this->assertSame($seen, $this->db->updateCalls[0]['params'][':v']);
		$this->assertSame(7, $this->db->updateCalls[0]['params'][':id']);
		global $USER;
		$this->assertSame($seen, $USER['last_seen_release']);
	}
}
