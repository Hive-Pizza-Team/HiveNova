<?php

use HiveNova\Core\Config;
use HiveNova\Core\SeasonWipeService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class SeasonWipeServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private RecordingDatabase $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new RecordingDatabase();
		$this->swapDatabaseInstance($this->db);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testFromGlobalsIncludesBuildingAndTechColumns(): void
	{
		$config = new Config([
			'uni' => 2,
			'metal_start' => 500,
			'crystal_start' => 400,
			'deuterium_start' => 0,
			'darkmatter_start' => 10,
		]);
		$wipe = SeasonWipeService::fromGlobals($GLOBALS['reslist'], $GLOBALS['resource'], $config);
		$this->assertStringContainsString('`metal_mine` = \'0\'', $wipe->planetSetSql());
		$this->assertStringContainsString('`metal` = :metal', $wipe->planetSetSql());
		$this->assertStringContainsString('`last_update` = :now', $wipe->planetSetSql());
		$this->assertStringContainsString('`b_hangar_plus` = \'0\'', $wipe->planetSetSql());
		$this->assertStringContainsString('`darkmatter` = :darkmatter', $wipe->userSetSql());
		$this->assertStringContainsString('`b_tech_queue` = \'\'', $wipe->userSetSql());
		$this->assertStringContainsString('`wons` = \'0\'', $wipe->userSetSql());
		$this->assertStringContainsString('`loos` = \'0\'', $wipe->userSetSql());
		$this->assertStringContainsString('`draws` = \'0\'', $wipe->userSetSql());
		$this->assertStringContainsString('`kbmetal` = \'0\'', $wipe->userSetSql());
		$this->assertStringContainsString('`kbcrystal` = \'0\'', $wipe->userSetSql());
		$this->assertStringContainsString('`lostunits` = \'0\'', $wipe->userSetSql());
		$this->assertStringContainsString('`desunits` = \'0\'', $wipe->userSetSql());
	}

	public function testWipeDeletesFleetsExtraPlanetsAndStats(): void
	{
		$moduls = array_fill(0, MODULE_AMOUNT, 0);
		$moduls[MODULE_FEATS] = 1;
		$config = new Config([
			'uni' => 2,
			'metal_start' => 500,
			'crystal_start' => 400,
			'deuterium_start' => 0,
			'darkmatter_start' => 10,
			'moduls' => implode(';', $moduls),
			'feat_tracking_from_start' => 1,
		]);
		Config::setInstance($config, 2);
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', $moduls),
		]), 1);

		$wipe = new SeasonWipeService('`metal` = :metal', '`darkmatter` = :darkmatter');
		$wipe->wipe(2, $config);

		$deleteSql = array_column($this->db->deletes, 0);
		$this->assertTrue($this->containsHay($deleteSql, '%%FLEETS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%PLANETS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%STATPOINTS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%USER_DIRECTIVES%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%EXPEDITION_PENDING_CHOICES%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%DIRECTIVE_PERIODS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%RECORDS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%TOPKB_USERS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%RW%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%MESSAGES%%'));
		$this->assertTrue($this->containsHay($deleteSql, 'FIND_IN_SET'));
		$this->assertTrue($this->containsHay($deleteSql, 'message_owner'));
		$this->assertTrue($this->containsHay($deleteSql, '%%SALVAGE_PACKAGES%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%UNIVERSE_EVENTS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%USER_ACHIEVEMENTS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%ALLIANCE%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%TRADES%%'));
		$this->assertTrue($this->containsHay($deleteSql, 'planet_type'));
		$this->assertTrue($this->containsHay($deleteSql, '%%FEAT_CLAIMS%%'));
		$this->assertTrue($this->containsHay($deleteSql, '%%FEAT_STATES%%'));

		$updateSql = array_column($this->db->updates, 0);
		$this->assertTrue($this->containsHay($updateSql, '%%PLANETS%%'));
		$this->assertTrue($this->containsHay($updateSql, '%%USERS%%'));
		$this->assertSame(0, $this->db->transactionDepth);
	}

	public function testWipeDeletesMessagesByUniverseOrOwnerAndRaportsByCombatants(): void
	{
		$moduls = array_fill(0, MODULE_AMOUNT, 0);
		$moduls[MODULE_FEATS] = 1;
		$config = new Config([
			'uni' => 3,
			'metal_start' => 500,
			'crystal_start' => 400,
			'deuterium_start' => 0,
			'darkmatter_start' => 10,
			'moduls' => implode(';', $moduls),
			'feat_tracking_from_start' => 1,
		]);
		Config::setInstance($config, 3);
		Config::setInstance(new Config([
			'uni' => 1,
			'moduls' => implode(';', $moduls),
		]), 1);

		$wipe = new SeasonWipeService('`metal` = :metal', '`darkmatter` = :darkmatter');
		$wipe->wipe(3, $config);

		$messageDeletes = array_values(array_filter(
			$this->db->deletes,
			static fn (array $row): bool => str_contains($row[0], '%%MESSAGES%%')
		));
		$this->assertCount(1, $messageDeletes);
		$this->assertStringContainsString('message_universe', $messageDeletes[0][0]);
		$this->assertStringContainsString('message_owner', $messageDeletes[0][0]);
		$this->assertSame(3, $messageDeletes[0][1][':uni']);
		$this->assertSame(3, $messageDeletes[0][1][':uni2']);

		$raportByCombatant = array_values(array_filter(
			$this->db->deletes,
			static fn (array $row): bool => str_contains($row[0], 'FIND_IN_SET')
		));
		$this->assertCount(1, $raportByCombatant);
		$this->assertStringContainsString('%%RW%%', $raportByCombatant[0][0]);
		$this->assertStringContainsString('r.attacker', $raportByCombatant[0][0]);
		$this->assertStringContainsString('r.defender', $raportByCombatant[0][0]);
		$this->assertSame(3, $raportByCombatant[0][1][':uni']);
	}

	/**
	 * @param list<string> $haystacks
	 */
	private function containsHay(array $haystacks, string $needle): bool
	{
		foreach ($haystacks as $sql) {
			if (str_contains($sql, $needle)) {
				return true;
			}
		}

		return false;
	}
}
