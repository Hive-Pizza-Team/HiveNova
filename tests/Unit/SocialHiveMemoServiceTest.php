<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\HiveMemo;
use HiveNova\Core\HiveTransfer;
use HiveNova\Core\SocialHiveMemoService;
use HiveNova\Core\Universe;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/SocialHiveMemoDatabaseStub.php';

class SocialHiveMemoServiceTest extends TestCase
{
	private SocialHiveMemoDatabaseStub $db;

	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5?: bool}> */
	private array $sends = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new SocialHiveMemoDatabaseStub();
		$this->swapDatabase($this->db);
		$this->sends = [];
		HiveTransfer::setBroadcaster(function (...$args) {
			$this->sends[] = $args;
			return ['trx_id' => 'trx' . count($this->sends)];
		});
		SocialHiveMemoService::setMemoKeyFetcher(static function (string $account): string {
			if ($account === 'gameacct') {
				return 'STM6TqSJaS1aRj6p6yZEo5xicX7bvLhrfdVqi5ToNrKxHU3FRBEdW';
			}

			return 'STM8LbCRyqtXk5VKbdFwK1YBgiafqprAd7yysN49PnDwAsyoMqQME';
		});
		SocialHiveMemoService::setEncryptor(static fn (string $wif, string $pub, string $memo): string => '#enc:' . $memo);
		SocialHiveMemoService::setNow(1_000_000);
		Config::setInstance($this->makeConfig(), 1);
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue([1]);
	}

	protected function tearDown(): void
	{
		HiveTransfer::setBroadcaster(null);
		SocialHiveMemoService::setMemoKeyFetcher(null);
		SocialHiveMemoService::setEncryptor(null);
		SocialHiveMemoService::setNow(null);
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
			'hive_inactive_memo_account' => 'gameacct',
			'hive_inactive_memo_active_key' => '5Ktestwifxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
			'hive_inactive_memo_asset' => 'HIVE',
			'hive_inactive_memo_amount' => 0.003,
			'hive_social_memo_active' => 1,
			'hive_social_memo_memo_key' => '5J15npVK6qABGsbdsLnJdaF5esrEWxeejeE3KUx6r534ug4tyze',
		], $override));
	}

	private function addOfflineLinkedUser(int $id = 7): void
	{
		$this->db->users[] = [
			'id' => $id,
			'hive_account' => 'playerone',
			'universe' => 1,
			'onlinetime' => 1_000_000 - SocialHiveMemoService::OFFLINE_AFTER - 1,
			'lang' => 'en',
		];
	}

	public function testBuildMemoPrefixesHashAndNamesSender(): void
	{
		$buddy = SocialHiveMemoService::buildMemo([], SocialHiveMemoService::KIND_BUDDY, 'Alice', 'Moon');
		$pm = SocialHiveMemoService::buildMemo([], SocialHiveMemoService::KIND_PM, 'Bob', 'Moon');
		$this->assertStringStartsWith('#', $buddy);
		$this->assertStringContainsString('Alice', $buddy);
		$this->assertStringContainsString('friend request', $buddy);
		$this->assertStringContainsString('Moon', $buddy);
		$this->assertStringStartsWith('#', $pm);
		$this->assertStringContainsString('Bob', $pm);
		$this->assertStringContainsString('private message', $pm);
		$this->assertStringContainsString('Moon', $pm);
	}

	public function testDisabledConfigDoesNotEnqueue(): void
	{
		Config::setInstance($this->makeConfig(['hive_social_memo_active' => 0]), 1);
		$this->addOfflineLinkedUser();
		(new SocialHiveMemoService())->notifyBuddyRequest(7, 'Alice');
		$this->assertSame([], $this->db->queue);
	}

	public function testOnlineRecipientIsSkipped(): void
	{
		$this->db->users[] = [
			'id' => 7,
			'hive_account' => 'playerone',
			'onlinetime' => 1_000_000,
			'lang' => 'en',
		];
		(new SocialHiveMemoService())->notifyBuddyRequest(7, 'Alice');
		$this->assertSame([], $this->db->queue);
	}

	public function testUnlinkedRecipientIsSkipped(): void
	{
		$this->db->users[] = [
			'id' => 7,
			'hive_account' => '',
			'onlinetime' => 1,
			'lang' => 'en',
		];
		(new SocialHiveMemoService())->notifyPrivateMessage(7, 'Alice');
		$this->assertSame([], $this->db->queue);
	}

	public function testBuddyRequestEnqueuesOfflineLinkedPlayer(): void
	{
		$this->addOfflineLinkedUser();
		(new SocialHiveMemoService())->notifyBuddyRequest(7, 'Alice');
		$this->assertCount(1, $this->db->queue);
		$this->assertSame('buddy', $this->db->queue[0]['kind']);
		$this->assertSame('Alice', $this->db->queue[0]['sender_name']);
	}

	public function testPrivateMessageCooldownSkipsSecondEnqueue(): void
	{
		$this->addOfflineLinkedUser();
		$service = new SocialHiveMemoService();
		$service->notifyPrivateMessage(7, 'Alice');
		$service->notifyPrivateMessage(7, 'Alice');
		$this->assertCount(1, $this->db->queue);
	}

	public function testCronSendsEncryptedTransferAndMarksSent(): void
	{
		$this->addOfflineLinkedUser();
		$service = new SocialHiveMemoService();
		$service->notifyBuddyRequest(7, 'Alice');
		$service->run();

		$this->assertCount(1, $this->sends);
		$this->assertSame('gameacct', $this->sends[0][0]);
		$this->assertSame('playerone', $this->sends[0][1]);
		$this->assertSame('0.003 HIVE', $this->sends[0][2]);
		$this->assertStringStartsWith('#enc:#', $this->sends[0][3]);
		$this->assertNotNull($this->db->queue[0]['sent_at']);
	}

	public function testCronEncryptsWithWalletCompatibleMemo(): void
	{
		SocialHiveMemoService::setEncryptor(null);
		$this->addOfflineLinkedUser();
		$service = new SocialHiveMemoService();
		$service->notifyBuddyRequest(7, 'Alice');
		$service->run();

		$this->assertCount(1, $this->sends);
		$decoded = HiveMemo::decode(
			'5J15npVK6qABGsbdsLnJdaF5esrEWxeejeE3KUx6r534ug4tyze',
			$this->sends[0][3]
		);
		$this->assertStringContainsString('Alice', $decoded);
		$this->assertStringContainsString('Moon', $decoded);
	}

	public function testWrongMemoKeyDoesNotBroadcastUndecryptableMemo(): void
	{
		SocialHiveMemoService::setEncryptor(null);
		Config::setInstance($this->makeConfig([
			'hive_social_memo_memo_key' => '5K1gv5rEtHiACVTFq9ikhEijezMh4rkbbTPqu4CAGMnXcTLC1su',
		]), 1);
		$this->addOfflineLinkedUser();
		$service = new SocialHiveMemoService();
		$service->notifyBuddyRequest(7, 'Alice');
		$service->run();

		$this->assertSame([], $this->sends);
		$this->assertNull($this->db->queue[0]['sent_at']);
	}

	public function testFailedBroadcastLeavesRowRetryable(): void
	{
		HiveTransfer::setBroadcaster(static fn () => ['code' => -32000, 'message' => 'fail']);
		$this->addOfflineLinkedUser();
		$service = new SocialHiveMemoService();
		$service->notifyBuddyRequest(7, 'Alice');
		$service->run();
		$this->assertNull($this->db->queue[0]['sent_at']);
		$this->assertSame(1, $this->db->queue[0]['attempts']);
	}

	public function testSelfTransferIsNotEnqueued(): void
	{
		$this->db->users[] = [
			'id' => 7,
			'hive_account' => 'gameacct',
			'onlinetime' => 1,
			'lang' => 'en',
		];
		(new SocialHiveMemoService())->notifyBuddyRequest(7, 'Alice');
		$this->assertSame([], $this->db->queue);
	}

	public function testEnqueueDoesNotThrowWhenDatabaseFails(): void
	{
		$ref = new ReflectionProperty(Database::class, 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, new class implements DatabaseInterface {
			public function select($qry, array $params = array()) { throw new RuntimeException('db'); }
			public function selectSingle($qry, array $params = array(), $field = false) { throw new RuntimeException('db'); }
			public function insert($qry, array $params = array()) { throw new RuntimeException('db'); }
			public function update($qry, array $params = array()) { throw new RuntimeException('db'); }
			public function delete($qry, array $params = array()) { return true; }
			public function replace($qry, array $params = array()) { return true; }
			public function query($qry) { return true; }
			public function nativeQuery($qry) { return []; }
			public function lastInsertId() { return 0; }
			public function rowCount() { return 0; }
			public function getQueryCounter() { return 0; }
			public function quote($str) { return $str; }
			public function disconnect() {}
			public function getHandle(): ?PDO { return null; }
			public function beginTransaction(): void {}
			public function commit(): void {}
			public function rollback(): void {}
		});

		(new SocialHiveMemoService())->notifyBuddyRequest(7, 'Alice');
		$this->assertTrue(true);
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
}
