<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\UniverseSwitchService;
use PHPUnit\Framework\TestCase;

class UniverseSwitchServiceTest extends TestCase
{
	private string $cacheDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->cacheDir = sys_get_temp_dir() . '/hn_uni_switch_' . bin2hex(random_bytes(4));
		mkdir($this->cacheDir, 0775, true);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->cacheDir . '/universe_switch_*.json') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->cacheDir);
		parent::tearDown();
	}

	public function test_accounts_linked_by_hive_or_email(): void
	{
		$service = new UniverseSwitchService();

		$this->assertTrue($service->accountsLinked(
			['hive_account' => 'aliceplayer', 'email_2' => ''],
			['hive_account' => 'AlicePlayer', 'email_2' => 'other@example.com']
		));
		$this->assertTrue($service->accountsLinked(
			['hive_account' => '', 'email_2' => 'Alice@Example.com'],
			['hive_account' => 'bobplayer', 'email_2' => 'alice@example.com']
		));
		$this->assertFalse($service->accountsLinked(
			['hive_account' => 'aliceplayer', 'email_2' => 'a@example.com'],
			['hive_account' => 'bobplayer', 'email_2' => 'b@example.com']
		));
	}

	public function test_list_linked_accounts_groups_by_universe(): void
	{
		$service = new UniverseSwitchService();
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn([
			['id' => 10, 'universe' => 1, 'username' => 'Alice', 'hive_account' => 'aliceplayer', 'email_2' => 'a@example.com', 'bana' => 0],
			['id' => 20, 'universe' => 2, 'username' => 'Alice2', 'hive_account' => 'aliceplayer', 'email_2' => 'a@example.com', 'bana' => 0],
			['id' => 30, 'universe' => 3, 'username' => 'Banned', 'hive_account' => 'aliceplayer', 'email_2' => 'a@example.com', 'bana' => 1],
		]);

		$accounts = $service->listLinkedAccounts(
			['id' => 10, 'universe' => 1, 'username' => 'Alice', 'hive_account' => 'aliceplayer', 'email_2' => 'a@example.com'],
			$db,
			[1, 2, 3],
			static fn (int $id): string => 'Universe ' . $id
		);

		$this->assertCount(2, $accounts);
		$this->assertTrue($accounts[0]['isCurrent']);
		$this->assertSame(1, $accounts[0]['universeId']);
		$this->assertSame(2, $accounts[1]['universeId']);
		$this->assertFalse($accounts[1]['isCurrent']);
	}

	public function test_find_linked_target_skips_current_and_unknown(): void
	{
		$service = new UniverseSwitchService();
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn([
			['id' => 10, 'universe' => 1, 'username' => 'Alice', 'hive_account' => 'aliceplayer', 'email_2' => '', 'bana' => 0],
			['id' => 20, 'universe' => 2, 'username' => 'Alice2', 'hive_account' => 'aliceplayer', 'email_2' => '', 'bana' => 0],
		]);

		$user = ['id' => 10, 'universe' => 1, 'username' => 'Alice', 'hive_account' => 'aliceplayer', 'email_2' => ''];
		$this->assertNull($service->findLinkedTarget($user, 1, $db, [1, 2]));
		$this->assertNull($service->findLinkedTarget($user, 9, $db, [1, 2]));
		$this->assertSame(
			['userId' => 20, 'universe' => 2],
			$service->findLinkedTarget($user, 2, $db, [1, 2])
		);
	}

	public function test_token_round_trip_and_peek_does_not_consume(): void
	{
		$service = new UniverseSwitchService();
		$token = $service->issueToken(10, 20, 2, $this->cacheDir);

		$this->assertTrue($service->isValidTokenFormat($token));
		$peeked = $service->peekToken($token, $this->cacheDir);
		$this->assertSame([
			'fromUserId' => 10,
			'toUserId'   => 20,
			'toUniverse' => 2,
		], $peeked);
		$this->assertNotNull($service->peekToken($token, $this->cacheDir));

		$consumed = $service->consumeToken($token, $this->cacheDir);
		$this->assertSame($peeked, $consumed);
		$this->assertNull($service->peekToken($token, $this->cacheDir));
		$this->assertNull($service->consumeToken($token, $this->cacheDir));
	}

	public function test_expired_token_is_rejected(): void
	{
		$service = new UniverseSwitchService();
		$token = bin2hex(random_bytes(16));
		$path = $this->cacheDir . '/universe_switch_' . $token . '.json';
		file_put_contents($path, json_encode([
			'fromUserId' => 1,
			'toUserId'   => 2,
			'toUniverse' => 3,
			'exp'        => time() - 5,
		], JSON_THROW_ON_ERROR));

		$this->assertNull($service->peekToken($token, $this->cacheDir));
	}

	public function test_build_redeem_url_path_and_wildcast(): void
	{
		$service = new UniverseSwitchService();
		$token = str_repeat('ab', 16);

		$this->assertSame(
			'https://example.com/game/uni3/index.php?page=universeSwitch&token=' . $token,
			$service->buildRedeemUrl('https://example.com/game/', 3, $token, false)
		);
		$this->assertSame(
			'https://uni3.example.com/game/index.php?page=universeSwitch&token=' . $token,
			$service->buildRedeemUrl('https://example.com/game', 3, $token, true)
		);
	}
}
