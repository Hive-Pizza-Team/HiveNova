<?php

declare(strict_types=1);

use HiveNova\Core\EmailRegistrationService;
use HiveNova\Core\EventFirehoseWriter;
use HiveNova\Core\LobbyActivityFeed;
use HiveNova\Core\LoginUniverseDefaults;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class LobbyActivityFeedTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	/** @var array<string, string> */
	private array $lng;

	protected function setUp(): void
	{
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
		$this->lng = [
			'php_tdformat' => 'Y-m-d',
			'ef_event_battle' => 'Battle',
			'ef_event_moon' => 'Moon',
			'ef_size_small' => 'Skirmish',
			'ef_size_medium' => 'Clash',
			'ef_size_large' => 'Major battle',
			'ef_size_moon_small' => 'Small',
			'ef_size_moon_medium' => 'Medium',
			'ef_size_moon_large' => 'Large',
			'ef_outcome_attacker' => 'Attackers prevailed',
			'ef_outcome_defender' => 'Defenders held',
			'ef_outcome_draw' => 'Stalemate',
			'ef_outcome_formed' => 'Formed',
		];
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_present_includes_universe_and_ts(): void
	{
		$presented = LobbyActivityFeed::present([
			'id' => 9,
			'universe' => 3,
			'time' => 1_700_000_000,
			'event_type' => 'battle',
			'size_bucket' => 'large',
			'outcome' => 'attacker',
		], $this->lng, 'UTC', [3 => 'Season Arena']);

		$this->assertSame(LobbyActivityFeed::JSON_KEYS, array_keys($presented));
		$this->assertSame(9, $presented['id']);
		$this->assertSame(1_700_000_000, $presented['ts']);
		$this->assertSame(3, $presented['universeId']);
		$this->assertSame('Season Arena', $presented['universe']);
		$this->assertSame('Major battle', $presented['size']);
	}

	public function test_fetch_across_universes(): void
	{
		EventFirehoseWriter::record(1, 100, 10, 'a');
		EventFirehoseWriter::record(3, 101, 10, 'r');
		EventFirehoseWriter::record(2, 102, 10, 'w');

		$rows = LobbyActivityFeed::fetch(
			[1, 3],
			$this->lng,
			'UTC',
			[1 => 'Classic', 3 => 'Season']
		);

		$this->assertCount(2, $rows);
		$this->assertSame(3, $rows[0]['universeId']);
		$this->assertSame('Season', $rows[0]['universe']);
		$this->assertSame(1, $rows[1]['universeId']);
	}

	public function test_empty_universe_list_returns_empty(): void
	{
		$this->assertSame([], LobbyActivityFeed::fetch([], $this->lng, 'UTC'));
	}
}

class EmailRegistrationServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_build_verify_url_embeds_universe(): void
	{
		$url = EmailRegistrationService::buildVerifyUrl(3, 42, 'abcKEY');
		$this->assertStringContainsString('/uni3/index.php?', $url);
		$this->assertStringContainsString('page=vertify', $url);
		$this->assertStringContainsString('i=42', $url);
		$this->assertStringContainsString('k=abcKEY', $url);
		$this->assertStringContainsString('uni=3', $url);
	}

	public function test_find_pending_validation_ignores_current_universe(): void
	{
		$this->fake->achievement->usersValidRows = [[
			'validationID' => 7,
			'validationKey' => 'secret',
			'universe' => 3,
			'userName' => 'Commander',
		]];

		$found = EmailRegistrationService::findPendingValidation(7, 'secret');
		$this->assertIsArray($found);
		$this->assertSame(3, (int) $found['universe']);

		$this->assertFalse(EmailRegistrationService::findPendingValidation(7, 'wrong'));
		$this->assertFalse(EmailRegistrationService::findPendingValidation(0, 'secret'));
	}
}
