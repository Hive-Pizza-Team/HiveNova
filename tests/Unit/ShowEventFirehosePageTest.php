<?php

declare(strict_types=1);

use HiveNova\Core\Database;
use HiveNova\Core\EventFirehoseFeed;
use HiveNova\Page\Game\ShowEventFirehosePage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * @internal
 */
final class TestableShowEventFirehosePage extends ShowEventFirehosePage
{
	public function __construct()
	{
	}

	protected function sendJSON($data): void
	{
	}

	protected function save(): void
	{
	}

	public function publicEvents(): array
	{
		return $this->loadEvents();
	}
}

class ShowEventFirehosePageTest extends TestCase
{
	use SwapDatabaseInstance;

	protected function setUp(): void
	{
		global $USER, $LNG;

		$USER = ['id' => 1, 'universe' => 1, 'timezone' => 'UTC'];
		$LNG = [
			'php_tdformat' => 'Y-m-d',
			'ef_event_battle' => 'Battle',
			'ef_size_small' => 'Skirmish',
			'ef_outcome_attacker' => 'Attackers prevailed',
		];
		$this->swapDatabaseInstance(new FakeDatabase());
	}

	protected function tearDown(): void
	{
		global $USER, $LNG;
		unset($USER, $LNG);
		$_REQUEST = [];
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_page_is_not_in_main_menus(): void
	{
		$nav = file_get_contents(__DIR__ . '/../../styles/templates/game/main.navigation.tpl');
		$bottom = file_get_contents(__DIR__ . '/../../styles/templates/game/main.bottomnav.tpl');
		$this->assertStringNotContainsString('eventFirehose', $nav);
		$this->assertStringNotContainsString('eventFirehose', $bottom);
		$this->assertStringNotContainsString('ef_title', $nav);
		$this->assertStringNotContainsString('ef_title', $bottom);
	}

	public function test_feed_ignores_client_universe_param(): void
	{
		$_REQUEST['universe'] = 2;
		$_REQUEST['sinceId'] = 0;

		Database::get()->insert(
			'INSERT INTO %%UNIVERSE_EVENTS%% SET universe = :universe, time = :time, event_type = :eventType, size_bucket = :sizeBucket, outcome = :outcome;',
			[
				':universe' => 1,
				':time' => 100,
				':eventType' => 'battle',
				':sizeBucket' => 'small',
				':outcome' => 'attacker',
			]
		);
		Database::get()->insert(
			'INSERT INTO %%UNIVERSE_EVENTS%% SET universe = :universe, time = :time, event_type = :eventType, size_bucket = :sizeBucket, outcome = :outcome;',
			[
				':universe' => 2,
				':time' => 100,
				':eventType' => 'battle',
				':sizeBucket' => 'small',
				':outcome' => 'defender',
			]
		);

		$page = new TestableShowEventFirehosePage();
		$events = $page->publicEvents();
		$this->assertCount(1, $events);
		$this->assertSame(EventFirehoseFeed::JSON_KEYS, array_keys($events[0]));
	}
}
