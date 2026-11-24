<?php

declare(strict_types=1);

use HiveNova\Core\ReleaseNotesService;
use HiveNova\Core\ReleaseSeenRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class ReleaseNotesRecordingFakeDatabase extends FakeDatabase
{
	/** @var list<array{sql: string, params: array<string, mixed>}> */
	public array $updateCalls = [];

	public function update($qry, array $params = [])
	{
		$this->updateCalls[] = ['sql' => $qry, 'params' => $params];
		return parent::update($qry, $params);
	}
}

class ReleaseNotesServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private string $tmpDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tmpDir = sys_get_temp_dir() . '/hn-release-notes-' . uniqid('', true);
		mkdir($this->tmpDir, 0777, true);
	}

	protected function tearDown(): void
	{
		if (is_dir($this->tmpDir)) {
			foreach (scandir($this->tmpDir) ?: [] as $entry) {
				if ($entry === '.' || $entry === '..') {
					continue;
				}
				@unlink($this->tmpDir . '/' . $entry);
			}
			@rmdir($this->tmpDir);
		}
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	private function writeRelease(string $filename, string $body): void
	{
		file_put_contents($this->tmpDir . '/' . $filename, $body);
	}

	public function test_lists_releases_newest_first_including_double_digit_minor(): void
	{
		$this->writeRelease('v1.9.0.md', "- Nine\n");
		$this->writeRelease('v1.10.0.md', "- Ten\n");
		$this->writeRelease('v1.4.0.md', "- Four\n");
		$this->writeRelease('README.md', "- ignore me\n");
		$this->writeRelease('v2.0.md', "- bad semver\n");
		$this->writeRelease('notes.txt', "- nope\n");

		$svc = new ReleaseNotesService($this->tmpDir . '/');
		$releases = $svc->listReleases();

		$this->assertSame(['v1.10.0', 'v1.9.0', 'v1.4.0'], array_column($releases, 'version'));
		$this->assertSame(['Ten'], $releases[0]['items']);
		$this->assertSame('v1.10.0', $svc->latestVersion());
	}

	public function test_parses_dash_and_star_bullets_ignores_other_lines(): void
	{
		$this->writeRelease(
			'v2.0.0.md',
			"# Title\n\n- First item\n* Second item\nNot a bullet\n-   Third with spaces\n"
		);

		$svc = new ReleaseNotesService($this->tmpDir . '/');
		$releases = $svc->listReleases();

		$this->assertCount(1, $releases);
		$this->assertSame(['First item', 'Second item', 'Third with spaces'], $releases[0]['items']);
	}

	public function test_empty_directory_returns_null_latest_and_no_unseen(): void
	{
		$svc = new ReleaseNotesService($this->tmpDir . '/');
		$this->assertSame([], $svc->listReleases());
		$this->assertNull($svc->latestVersion());
		$this->assertSame([], $svc->unseenVersions(null));
		$this->assertFalse($svc->hasUnseen(null));
	}

	public function test_missing_directory_is_empty(): void
	{
		$svc = new ReleaseNotesService($this->tmpDir . '/does-not-exist/');
		$this->assertSame([], $svc->listReleases());
		$this->assertNull($svc->latestVersion());
	}

	/**
	 * @dataProvider unseenProvider
	 */
	public function test_unseen_versions(?string $lastSeen, array $expected): void
	{
		$this->writeRelease('v1.4.0.md', "- a\n");
		$this->writeRelease('v1.5.0.md', "- b\n");
		$this->writeRelease('v2.0.0.md', "- c\n");

		$svc = new ReleaseNotesService($this->tmpDir . '/');
		$this->assertSame($expected, $svc->unseenVersions($lastSeen));
		$this->assertSame($expected !== [], $svc->hasUnseen($lastSeen));
	}

	public static function unseenProvider(): array
	{
		return [
			'null means all unseen' => [null, ['v2.0.0', 'v1.5.0', 'v1.4.0']],
			'empty string means all unseen' => ['', ['v2.0.0', 'v1.5.0', 'v1.4.0']],
			'garbage means all unseen' => ['not-a-version', ['v2.0.0', 'v1.5.0', 'v1.4.0']],
			'older release leaves newer unseen' => ['v1.4.0', ['v2.0.0', 'v1.5.0']],
			'latest means none unseen' => ['v2.0.0', []],
			'middle release' => ['v1.5.0', ['v2.0.0']],
		];
	}

	public function test_mark_seen_writes_only_when_value_changes(): void
	{
		$fake = new ReleaseNotesRecordingFakeDatabase();
		$this->swapDatabaseInstance($fake);
		$repo = new ReleaseSeenRepository($fake);

		$this->assertFalse($repo->markSeen(42, null, null));
		$this->assertFalse($repo->markSeen(42, '', null));
		$this->assertFalse($repo->markSeen(42, 'v1.4.0', 'v1.4.0'));
		$this->assertSame([], $fake->updateCalls);

		$this->assertTrue($repo->markSeen(42, 'v1.5.0', 'v1.4.0'));
		$this->assertTrue($repo->markSeen(42, 'v1.5.0', null));
		$this->assertCount(2, $fake->updateCalls);

		foreach ($fake->updateCalls as $call) {
			$this->assertStringContainsString('UPDATE %%USERS%% SET last_seen_release = :v WHERE id = :id', $call['sql']);
			$this->assertSame('v1.5.0', $call['params'][':v']);
			$this->assertSame(42, $call['params'][':id']);
		}
	}

	public function test_mark_seen_uses_database_get_when_no_db_injected(): void
	{
		$fake = new ReleaseNotesRecordingFakeDatabase();
		$this->swapDatabaseInstance($fake);
		$repo = new ReleaseSeenRepository();

		$this->assertTrue($repo->markSeen(7, 'v1.4.0', null));
		$this->assertCount(1, $fake->updateCalls);
		$this->assertSame(7, $fake->updateCalls[0]['params'][':id']);
	}
}
