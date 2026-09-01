<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\HiveTransfer;
use HiveNova\Core\InactiveHiveMemoService;
use HiveNova\Core\Universe;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/InactiveHiveMemoDatabaseStub.php';

class InactiveHiveMemoServiceTest extends TestCase
{
	private InactiveHiveMemoDatabaseStub $db;

	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
	private array $sends = [];

	private bool $sendShouldFail = false;

	protected function setUp(): void
	{
		parent::setUp();
		if (!defined('AUTH_USR')) {
			define('AUTH_USR', 0);
		}
		if (!defined('AUTH_ADM')) {
			define('AUTH_ADM', 3);
		}

		$this->db = new InactiveHiveMemoDatabaseStub();
		$this->swapDatabase($this->db);
		$this->sends = [];
		$this->sendShouldFail = false;
		HiveTransfer::setBroadcaster(function (...$args) {
			if ($this->sendShouldFail) {
				return ['code' => -32000, 'message' => 'fail'];
			}
			$this->sends[] = $args;
			return ['trx_id' => 'trx' . count($this->sends)];
		});

		Config::setInstance($this->makeConfig(), 1);
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue([1]);
	}

	protected function tearDown(): void
	{
		HiveTransfer::setBroadcaster(null);
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		$this->restoreDatabase();
		parent::tearDown();
	}

	private function makeConfig(array $override = []): Config
	{
		return new Config(array_merge([
			'uni' => 1,
			'game_name' => 'Moon',
			'del_user_automatic' => 90,
			'hive_inactive_memo_active' => 1,
			'hive_inactive_memo_armed' => 1,
			'hive_inactive_memo_account' => 'gameacct',
			'hive_inactive_memo_active_key' => '5Ktestwifxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
			'hive_inactive_memo_asset' => 'HIVE',
			'hive_inactive_memo_amount' => 0.003,
		], $override));
	}

	private function swapDatabase(DatabaseInterface $fake): void
	{
		$ref = new ReflectionProperty(Database::class, 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, $fake);
	}

	private function restoreDatabase(): void
	{
		$ref = new ReflectionProperty(Database::class, 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);
	}

	private function ltiUser(array $override = []): array
	{
		return array_merge([
			'id' => 10,
			'authlevel' => AUTH_USR,
			'hive_account' => 'playerone',
			'onlinetime' => TIMESTAMP - INACTIVE_LONG - 10,
			'universe' => 1,
			'lang' => 'en',
			'inactive_mail' => 0,
			'inactive_hive_memo_onlinetime' => null,
		], $override);
	}

	public function testFirstArmMarksExistingLtiAndSendsNothing(): void
	{
		Config::setInstance($this->makeConfig(['hive_inactive_memo_armed' => 0]), 1);
		$this->db->users = [$this->ltiUser()];

		(new InactiveHiveMemoService())->run();

		$this->assertSame([], $this->sends);
		$this->assertSame(
			(int) $this->db->users[0]['onlinetime'],
			$this->db->users[0]['inactive_hive_memo_onlinetime']
		);
		$this->assertSame(1, (int) Config::get(1)->hive_inactive_memo_armed);
	}

	public function testArmedLinkedLtiWithWipeSendsOnceAndSetsMarker(): void
	{
		$this->db->users = [$this->ltiUser()];

		(new InactiveHiveMemoService())->run();

		$this->assertCount(1, $this->sends);
		$this->assertSame('playerone', $this->sends[0][1]);
		$this->assertStringContainsString('(I)', $this->sends[0][3]);
		$this->assertStringContainsString('removed', $this->sends[0][3]);
		$this->assertSame(
			(int) $this->db->users[0]['onlinetime'],
			$this->db->users[0]['inactive_hive_memo_onlinetime']
		);
	}

	public function testWipeOffOmitsRemovalSentence(): void
	{
		Config::setInstance($this->makeConfig(['del_user_automatic' => 0]), 1);
		$this->db->users = [$this->ltiUser()];

		(new InactiveHiveMemoService())->run();

		$this->assertCount(1, $this->sends);
		$this->assertStringContainsString('(I)', $this->sends[0][3]);
		$this->assertStringNotContainsString('removed', $this->sends[0][3]);
	}

	public function testUnlinkedPlayerIsSkipped(): void
	{
		$this->db->users = [$this->ltiUser(['hive_account' => ''])];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
	}

	public function testFeatureOffSendsNothing(): void
	{
		Config::setInstance($this->makeConfig(['hive_inactive_memo_active' => 0]), 1);
		$this->db->users = [$this->ltiUser()];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
	}

	public function testSameStretchDoesNotResend(): void
	{
		$online = TIMESTAMP - INACTIVE_LONG - 10;
		$this->db->users = [$this->ltiUser([
			'onlinetime' => $online,
			'inactive_hive_memo_onlinetime' => $online,
		])];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
	}

	public function testNewLapseAfterLoginSendsAgain(): void
	{
		$oldOnline = TIMESTAMP - INACTIVE_LONG - 100000;
		$newOnline = TIMESTAMP - INACTIVE_LONG - 10;
		$this->db->users = [$this->ltiUser([
			'onlinetime' => $newOnline,
			'inactive_hive_memo_onlinetime' => $oldOnline,
		])];
		(new InactiveHiveMemoService())->run();
		$this->assertCount(1, $this->sends);
		$this->assertSame($newOnline, $this->db->users[0]['inactive_hive_memo_onlinetime']);
	}

	public function testFailedTransferReleasesClaim(): void
	{
		$this->sendShouldFail = true;
		$this->db->users = [$this->ltiUser()];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
		$this->assertNull($this->db->users[0]['inactive_hive_memo_onlinetime']);
	}

	public function testShortInactiveIsSkipped(): void
	{
		$this->db->users = [$this->ltiUser([
			'onlinetime' => TIMESTAMP - INACTIVE - 10,
		])];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
	}

	public function testStaffAccountIsSkipped(): void
	{
		$this->db->users = [$this->ltiUser(['authlevel' => AUTH_ADM])];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
	}

	public function testSelfTransferIsSkipped(): void
	{
		$this->db->users = [$this->ltiUser(['hive_account' => 'gameacct'])];
		(new InactiveHiveMemoService())->run();
		$this->assertSame([], $this->sends);
	}

	public function testInactiveMailColumnIsUntouched(): void
	{
		$this->db->users = [$this->ltiUser(['inactive_mail' => 0])];
		(new InactiveHiveMemoService())->run();
		$this->assertSame(0, $this->db->users[0]['inactive_mail']);
	}

	public function testBuildMemoNeverStartsWithHash(): void
	{
		$memo = InactiveHiveMemoService::buildMemo([], 'Moon', true);
		$this->assertStringStartsNotWith('#', $memo);
		$this->assertStringContainsString('(I)', $memo);
	}
}
