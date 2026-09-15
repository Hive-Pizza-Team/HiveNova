<?php

declare(strict_types=1);

use HiveNova\Core\HiveUsernameExistsCache;
use PHPUnit\Framework\TestCase;

class HiveUsernameExistsCacheTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		HiveUsernameExistsCache::reset();
		$this->dir = sys_get_temp_dir().'/hivenova-hive-user-cache-'.bin2hex(random_bytes(4)).'/';
		mkdir($this->dir, 0777, true);
	}

	protected function tearDown(): void
	{
		HiveUsernameExistsCache::reset();
		foreach (glob($this->dir.'*.json') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dir);
		parent::tearDown();
	}

	public function test_lookup_fetches_missing_names_once_and_reuses_memory(): void
	{
		$calls = 0;
		$fetch = function (array $names) use (&$calls): array {
			$calls++;
			$this->assertSame(['alice', 'bob'], $names);
			return ['alice' => true, 'bob' => false];
		};

		$first = HiveUsernameExistsCache::lookup(['Alice', 'bob', 'Alice'], $fetch, $this->dir, 1000);
		$this->assertSame(['alice' => true, 'bob' => false], $first);
		$this->assertSame(1, $calls);

		$second = HiveUsernameExistsCache::lookup(['alice'], $fetch, $this->dir, 1010);
		$this->assertSame(['alice' => true], $second);
		$this->assertSame(1, $calls);
	}

	public function test_lookup_reads_unexpired_file_after_memory_reset(): void
	{
		$fetch = function (array $names): array {
			return ['carol' => true];
		};

		HiveUsernameExistsCache::lookup(['carol'], $fetch, $this->dir, 1000);
		HiveUsernameExistsCache::reset();

		$calls = 0;
		$again = HiveUsernameExistsCache::lookup(['carol'], function () use (&$calls): array {
			$calls++;
			return [];
		}, $this->dir, 1030);

		$this->assertSame(['carol' => true], $again);
		$this->assertSame(0, $calls);
	}

	public function test_lookup_refetches_after_ttl_expires(): void
	{
		$calls = 0;
		$fetch = function (array $names) use (&$calls): array {
			$calls++;
			return ['dave' => $calls === 1];
		};

		HiveUsernameExistsCache::lookup(['dave'], $fetch, $this->dir, 1000);
		HiveUsernameExistsCache::reset();
		$later = HiveUsernameExistsCache::lookup(
			['dave'],
			$fetch,
			$this->dir,
			1000 + HiveUsernameExistsCache::TTL_SECONDS + 1
		);

		$this->assertSame(['dave' => false], $later);
		$this->assertSame(2, $calls);
	}

	public function test_lookup_ignores_blank_names_and_non_array_fetch(): void
	{
		$calls = 0;
		$result = HiveUsernameExistsCache::lookup(
			['', '  ', 'erin'],
			function (array $names) use (&$calls) {
				$calls++;
				$this->assertSame(['erin'], $names);
				return 'nope';
			},
			$this->dir,
			2000
		);

		$this->assertSame(['erin' => false], $result);
		$this->assertSame(1, $calls);
	}

	public function test_default_cache_dir_uses_cache_path(): void
	{
		$this->assertStringContainsString('reg-hive-user', HiveUsernameExistsCache::defaultCacheDir());
	}
}
