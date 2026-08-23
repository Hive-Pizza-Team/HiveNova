<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DirectiveCatalog;
use HiveNova\Core\DirectiveService;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';

class DirectiveServiceTest extends TestCase
{
	use SwapDatabaseInstance;
	use RestoreGameGlobals;

	private CommanderDatabaseStub $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->snapshotGameGlobals();
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
		unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_ORIGIN']);
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testPeriodWindowAnchorsToMondayUtc(): void
	{
		$thursday = (new DateTime('2026-08-20 12:00:00', new DateTimeZone('UTC')))->getTimestamp();
		$monday = (new DateTime('2026-08-17 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
		$window = DirectiveService::periodWindow($thursday);
		$this->assertSame($monday, $window['start']);
		$this->assertSame($monday + 7 * 86400, $window['end']);
	}

	public function testSelectDirectiveLocksChoice(): void
	{
		$row = DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
		$this->assertSame(DirectiveCatalog::INDUSTRIAL, $row['directive_key']);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_LOCKED);
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::TRADE);
	}

	public function testUnknownDirectiveRejected(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_UNKNOWN);
		DirectiveService::selectDirective(10, 1, 'not_a_real_directive');
	}

	public function testClaimRewardIsIdempotent(): void
	{
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
		$this->db->userDirectives[0]['completed_at'] = TIMESTAMP;
		$this->db->planets[5] = ['id' => 5, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];
		$this->db->statpoints[10] = DirectiveCatalog::REWARD_REFERENCE_POINTS;

		$first = DirectiveService::claimReward(10, 1, 5);
		$this->assertSame(50000, $first['metal']);
		$this->assertSame(25000, $first['crystal']);
		$this->assertSame(50000, $this->db->planets[5]['metal']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_CLAIMED);
		DirectiveService::claimReward(10, 1, 5);
	}

	public function testClaimRewardScalesDownForDayOnePoints(): void
	{
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
		$this->db->userDirectives[0]['completed_at'] = TIMESTAMP;
		$this->db->planets[5] = ['id' => 5, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];

		$reward = DirectiveService::claimReward(10, 1, 5);
		$expected = DirectiveCatalog::scaledReward(
			DirectiveCatalog::get(DirectiveCatalog::INDUSTRIAL)['reward'],
			0
		);
		$this->assertSame($expected, $reward);
		$this->assertSame(2500, $reward['metal']);
		$this->assertSame(1250, $this->db->planets[5]['crystal']);
	}

	public function testRewardFactorFloorsThenTracksPoints(): void
	{
		$this->assertSame(0.05, DirectiveCatalog::rewardFactor(0));
		$this->assertSame(0.05, DirectiveCatalog::rewardFactor(500));
		$this->assertSame(1.0, DirectiveCatalog::rewardFactor(10000));
		$this->assertSame(2.0, DirectiveCatalog::rewardFactor(20000));
		$this->assertSame(
			['metal' => 80000, 'crystal' => 40000, 'deuterium' => 30000],
			DirectiveCatalog::scaledReward(
				DirectiveCatalog::get(DirectiveCatalog::DEFENSIVE)['reward'],
				20000
			)
		);
	}

	public function testModuleDisabledRejectsSelect(): void
	{
		$modules = array_fill(0, 49, 1);
		$modules[MODULE_COMMANDER] = 0;
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', $modules),
		]), 1);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_DISABLED);
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
	}

	public function testSameOriginRejectsForeignOrigin(): void
	{
		$_SERVER['HTTP_HOST'] = 'moon.hive.pizza';
		$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
		$this->assertFalse(DirectiveService::isSameOriginRequest());

		$_SERVER['HTTP_ORIGIN'] = 'https://moon.hive.pizza';
		$this->assertTrue(DirectiveService::isSameOriginRequest());
		unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_ORIGIN']);
	}

	public function testSameOriginUsesRefererWhenOriginMissing(): void
	{
		$_SERVER['HTTP_HOST'] = 'moon.hive.pizza';
		unset($_SERVER['HTTP_ORIGIN']);
		$_SERVER['HTTP_REFERER'] = '';
		$this->assertTrue(DirectiveService::isSameOriginRequest());

		$_SERVER['HTTP_REFERER'] = 'https://moon.hive.pizza/game.php';
		$this->assertTrue(DirectiveService::isSameOriginRequest());

		$_SERVER['HTTP_REFERER'] = 'https://evil.example/game.php';
		$this->assertFalse(DirectiveService::isSameOriginRequest());
	}

	public function testCsrfTokenRoundTrip(): void
	{
		$this->assertFalse(DirectiveService::validateCsrfToken(null));
		$this->assertFalse(DirectiveService::validateCsrfToken(''));
		$token = DirectiveService::issueCsrfToken();
		$this->assertTrue(DirectiveService::validateCsrfToken($token));
		$this->assertFalse(DirectiveService::validateCsrfToken('deadbeef'));
	}

	public function testClaimRewardRejectsMissingPeriodAndIncomplete(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_NO_PERIOD);
		DirectiveService::claimReward(10, 1, 5);
	}

	public function testClaimRewardRejectsMissingDirective(): void
	{
		DirectiveService::ensureCurrentPeriod(1);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_NO_DIRECTIVE);
		DirectiveService::claimReward(10, 1, 5);
	}

	public function testClaimRewardRejectsIncomplete(): void
	{
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::INDUSTRIAL);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DirectiveService::ERROR_NOT_COMPLETE);
		DirectiveService::claimReward(10, 1, 5);
	}

	public function testGetBriefingDataIncludesSelectedDirectiveAndFleets(): void
	{
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::EXPLORATION);
		$this->db->fleets[] = [
			'fleet_id' => 44,
			'fleet_owner' => 10,
			'fleet_mission' => 15,
			'fleet_meta' => '{"stance":"aggressive"}',
			'fleet_end_stay' => TIMESTAMP + 10,
			'fleet_end_time' => TIMESTAMP + 20,
			'fleet_mess' => 2,
		];
		ExpeditionChoiceService::createPendingBranch(44, 10, 5, 'resource_find', 'aggressive', [
			'metal' => 50,
			'crystal' => 0,
			'deuterium' => 0,
		], []);

		$data = DirectiveService::getBriefingData(10, 1);
		$this->assertTrue($data['enabled']);
		$this->assertSame(DirectiveCatalog::EXPLORATION, $data['directive']['key']);
		$this->assertSame(1500, $data['directive']['reward']['metal']);
		$this->assertSame(1500, $data['directive']['reward']['crystal']);
		$this->assertSame(1000, $data['directive']['reward']['deuterium']);
		$this->assertFalse($data['directive']['completed']);
		$this->assertNotEmpty($data['csrf']);
		$this->assertCount(1, $data['expeditions']);
		$this->assertSame('aggressive', $data['expeditions'][0]['stance']);
		$this->assertCount(1, $data['pending_choices']);
	}

	public function testNotifyPeriodEndingMarksProgressOnce(): void
	{
		DirectiveService::selectDirective(10, 1, DirectiveCatalog::TRADE);
		$this->db->periods[0]['period_end'] = TIMESTAMP + 60;
		DirectiveService::notifyPeriodEndingIfNeeded(1);
		$progress = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress['ending_push_sent']);

		DirectiveService::notifyPeriodEndingIfNeeded(1);
		$progress2 = json_decode((string) $this->db->userDirectives[0]['progress_json'], true);
		$this->assertSame(1, $progress2['ending_push_sent']);
	}

	public function testNotifyPeriodEndingSkipsWhenFarFromEnd(): void
	{
		DirectiveService::ensureCurrentPeriod(1);
		DirectiveService::notifyPeriodEndingIfNeeded(1);
		$this->assertSame([], $this->db->updates);
	}

	public function testEmptyProgressUnknownKey(): void
	{
		$this->assertSame([], DirectiveCatalog::emptyProgress('missing'));
		$this->assertNull(DirectiveCatalog::counterForEvent('missing', 'build_complete'));
	}
}
