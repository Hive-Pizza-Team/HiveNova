<?php

use HiveNova\Core\Config;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CommanderDatabaseStub.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';
require_once __DIR__ . '/../Support/RestoreGameGlobals.php';

class ExpeditionChoiceServiceTest extends TestCase
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
		$GLOBALS['resource'][202] = 'light_cargo';
		ExpeditionChoiceService::setRng(null);
	}

	protected function tearDown(): void
	{
		ExpeditionChoiceService::setRng(null);
		$this->restoreGameGlobals();
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testBranchesDifferGivenFixedReward(): void
	{
		$options = ExpeditionChoiceService::buildOptions('resource_find', 'balanced', [
			'metal' => 1000,
			'crystal' => 500,
			'deuterium' => 0,
		]);
		$this->assertLessThan($options['balanced']['metal'], $options['cautious']['metal']);
		$this->assertGreaterThan($options['balanced']['metal'], $options['aggressive']['metal']);
		$this->assertSame([], $options['cautious']['loss_ships']);
	}

	public function testAggressiveStanceRaisesYieldAndLossChance(): void
	{
		$this->assertGreaterThan(
			ExpeditionChoiceService::yieldMultiplier('cautious'),
			ExpeditionChoiceService::yieldMultiplier('aggressive')
		);
		$this->assertGreaterThan(
			ExpeditionChoiceService::lossChance('cautious'),
			ExpeditionChoiceService::lossChance('aggressive')
		);
	}

	public function testShouldCreateBranchHonorsRateAndEligibility(): void
	{
		$this->assertFalse(ExpeditionChoiceService::shouldCreateBranch('nothing', 1));
		$this->assertTrue(ExpeditionChoiceService::shouldCreateBranch('resource_find', 10));
		$this->assertFalse(ExpeditionChoiceService::shouldCreateBranch('resource_find', 90));
	}

	public function testResolveBranchAppliesPlanetDeltasAndRejectsDoubleResolve(): void
	{
		$this->db->planets[12] = ['id' => 12, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];
		ExpeditionChoiceService::createPendingBranch(77, 4, 12, 'resource_find', 'balanced', [
			'metal' => 1000,
			'crystal' => 0,
			'deuterium' => 0,
		], [202 => 5]);

		$choice = ExpeditionChoiceService::resolveBranch(77, 4, 'aggressive');
		$this->assertGreaterThan(1000, $choice['metal']);
		$this->assertSame($choice['metal'], $this->db->planets[12]['metal']);
		$this->assertNotEmpty($this->db->pendingChoices[77]['resolved_at']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(ExpeditionChoiceService::ERROR_ALREADY_RESOLVED);
		ExpeditionChoiceService::resolveBranch(77, 4, 'aggressive');
	}

	public function testResolveBranchRejectsWrongUserAndInvalidKey(): void
	{
		ExpeditionChoiceService::createPendingBranch(5, 1, 9, 'resource_find', 'balanced', [
			'metal' => 100,
			'crystal' => 0,
			'deuterium' => 0,
		], []);

		try {
			ExpeditionChoiceService::resolveBranch(5, 2, 'balanced');
			$this->fail('expected forbidden');
		} catch (RuntimeException $e) {
			$this->assertSame(ExpeditionChoiceService::ERROR_FORBIDDEN, $e->getMessage());
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(ExpeditionChoiceService::ERROR_INVALID_BRANCH);
		ExpeditionChoiceService::resolveBranch(5, 1, 'not-a-branch');
	}

	public function testAutoResolveExpiredUsesBalanced(): void
	{
		$this->db->planets[3] = ['id' => 3, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];
		ExpeditionChoiceService::createPendingBranch(11, 1, 3, 'resource_find', 'aggressive', [
			'metal' => 1000,
			'crystal' => 0,
			'deuterium' => 0,
		], []);
		$this->db->pendingChoices[11]['created_at'] = TIMESTAMP - 200000;

		$count = ExpeditionChoiceService::autoResolveExpired(172800);
		$this->assertSame(1, $count);
		$options = json_decode((string) $this->db->pendingChoices[11]['options_json'], true);
		$this->assertSame($options['branches']['balanced']['metal'], $this->db->planets[3]['metal']);
	}

	public function testInvalidStanceNormalizesToBalanced(): void
	{
		$this->assertSame('balanced', ExpeditionChoiceService::normalizeStance('turbo'));
		$this->assertTrue(ExpeditionChoiceService::isValidStance('cautious'));
		$this->assertFalse(ExpeditionChoiceService::isValidStance('turbo'));
	}

	public function testRollUsesInjectedRngAndDefault(): void
	{
		ExpeditionChoiceService::setRng(static fn (int $min, int $max): int => 7);
		$this->assertSame(7, ExpeditionChoiceService::roll(1, 10));
		ExpeditionChoiceService::setRng(null);
		$n = ExpeditionChoiceService::roll(1, 1);
		$this->assertSame(1, $n);
	}

	public function testStanceFromMetaVariants(): void
	{
		$this->assertSame('aggressive', ExpeditionChoiceService::stanceFromMeta(['stance' => 'aggressive']));
		$this->assertSame('balanced', ExpeditionChoiceService::stanceFromMeta(''));
		$this->assertSame('balanced', ExpeditionChoiceService::stanceFromMeta('not-json'));
		$this->assertSame('cautious', ExpeditionChoiceService::stanceFromMeta('{"stance":"cautious"}'));
		$encoded = ExpeditionChoiceService::encodeMeta('aggressive', ['note' => 'x']);
		$this->assertSame('aggressive', ExpeditionChoiceService::stanceFromMeta($encoded));
	}

	public function testMaybeDeferOutcomeCreatesPendingWhenEligible(): void
	{
		ExpeditionChoiceService::setRng(static fn (int $min, int $max): int => 1);
		$created = ExpeditionChoiceService::maybeDeferOutcome([
			'fleet_id' => 90,
			'fleet_owner' => 4,
			'fleet_start_id' => 3,
			'fleet_meta' => '{"stance":"cautious"}',
		], 'resource_find', ['metal' => 100, 'crystal' => 0, 'deuterium' => 0], [202 => 2]);
		$this->assertTrue($created);
		$this->assertArrayHasKey(90, $this->db->pendingChoices);

		$this->assertFalse(ExpeditionChoiceService::maybeDeferOutcome([
			'fleet_id' => 91,
			'fleet_owner' => 4,
			'fleet_start_id' => 3,
		], 'nothing', ['metal' => 1], []));
	}

	public function testResolveBranchNotFound(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(ExpeditionChoiceService::ERROR_NOT_FOUND);
		ExpeditionChoiceService::resolveBranch(999, 1, 'balanced');
	}

	public function testAutoResolveSkipsBrokenRows(): void
	{
		ExpeditionChoiceService::createPendingBranch(12, 1, 3, 'resource_find', 'balanced', [
			'metal' => 10,
			'crystal' => 0,
			'deuterium' => 0,
		], []);
		$this->db->pendingChoices[12]['created_at'] = TIMESTAMP - 200000;
		$this->db->pendingChoices[12]['options_json'] = 'broken';
		$this->assertSame(0, ExpeditionChoiceService::autoResolveExpired());
	}

	public function testPendingForUserAndShipDeltas(): void
	{
		$this->db->planets[8] = ['id' => 8, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0];
		ExpeditionChoiceService::createPendingBranch(15, 2, 8, 'ship_salvage', 'aggressive', [
			'metal' => 0,
			'crystal' => 0,
			'deuterium' => 0,
			'ships' => [202 => 10],
		], [202 => 10]);
		$pending = ExpeditionChoiceService::pendingForUser(2);
		$this->assertCount(1, $pending);
		$choice = ExpeditionChoiceService::resolveBranch(15, 2, 'aggressive');
		$this->assertNotEmpty($choice['ships']);
		$this->assertNotEmpty($choice['loss_ships']);
		$this->assertGreaterThan(0, $this->db->planets[8]['ship_delta']);
	}
}
