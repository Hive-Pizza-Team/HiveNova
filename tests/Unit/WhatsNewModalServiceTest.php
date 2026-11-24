<?php

declare(strict_types=1);

use HiveNova\Core\ReleaseNotesService;
use HiveNova\Core\WhatsNewModalService;
use PHPUnit\Framework\TestCase;

class WhatsNewModalServiceTest extends TestCase
{
	private string $tmpDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tmpDir = sys_get_temp_dir() . '/hn-whats-new-modal-' . uniqid('', true);
		mkdir($this->tmpDir, 0777, true);
		file_put_contents($this->tmpDir . '/v1.4.0.md', "- Four\n");
		file_put_contents($this->tmpDir . '/v1.5.0.md', "- Five A\n- Five B\n");
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
		parent::tearDown();
	}

	private function service(): WhatsNewModalService
	{
		return new WhatsNewModalService(new ReleaseNotesService($this->tmpDir . '/'));
	}

	public function test_null_last_seen_returns_all_newest_first(): void
	{
		$releases = $this->service()->modalReleases(null, 'overview', false);
		$this->assertSame(['v1.5.0', 'v1.4.0'], array_column($releases, 'version'));
		$this->assertSame(['Five A', 'Five B'], $releases[0]['items']);
	}

	public function test_garbage_last_seen_returns_all(): void
	{
		$releases = $this->service()->modalReleases('not-a-version', 'overview', false);
		$this->assertCount(2, $releases);
	}

	public function test_older_last_seen_returns_only_newer(): void
	{
		$releases = $this->service()->modalReleases('v1.4.0', 'buildings', false);
		$this->assertSame(['v1.5.0'], array_column($releases, 'version'));
	}

	public function test_latest_last_seen_returns_empty(): void
	{
		$this->assertSame([], $this->service()->modalReleases('v1.5.0', 'overview', false));
	}

	public function test_whats_new_page_suppresses_modal(): void
	{
		$this->assertSame([], $this->service()->modalReleases(null, 'whatsNew', false));
	}

	public function test_celebration_pending_suppresses_modal(): void
	{
		$this->assertSame([], $this->service()->modalReleases(null, 'overview', true));
	}
}
