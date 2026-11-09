<?php

declare(strict_types=1);

use HiveNova\Core\HiveAccountUpgradeService;
use PHPUnit\Framework\TestCase;

class HiveAccountUpgradeServiceTest extends TestCase
{
	/** @var list<array{userId: int, account: string}> */
	private array $binds = [];

	/** @var array<string, int> account@universe => count */
	private array $taken = [];

	/** @var array<string, bool> */
	private array $validSignatures = [];

	/** @var array<string, string> token => username */
	private array $signerUsers = [];

	private function service(?array $hiveSigner = null): HiveAccountUpgradeService
	{
		return new HiveAccountUpgradeService(
			function (string $account, string $signature): bool {
				return ($this->validSignatures[$account.'|'.$signature] ?? false) === true;
			},
			function (string $account, int $universe): int {
				return (int) ($this->taken[$account.'@'.$universe] ?? 0);
			},
			function (int $userId, string $account): void {
				$this->binds[] = ['userId' => $userId, 'account' => $account];
			},
			function (string $token): ?string {
				return $this->signerUsers[$token] ?? null;
			},
			$hiveSigner ?? ['clientId' => 'moon.app', 'authorizeUrl' => 'https://hivesigner.com/oauth2/authorize', 'meUrl' => 'https://hivesigner.com/api/me', 'scope' => 'login'],
		);
	}

	public function test_password_player_detection(): void
	{
		$this->assertTrue(HiveAccountUpgradeService::isPasswordPlayer([
			'email' => 'player@example.com',
		]));
		$this->assertTrue(HiveAccountUpgradeService::isPasswordPlayer([
			'email' => '',
		]));
		$this->assertFalse(HiveAccountUpgradeService::isPasswordPlayer([
			'email' => 'alice@hive.blog',
		]));
		$this->assertFalse(HiveAccountUpgradeService::isPasswordPlayer([
			'email' => 'Alice@Hive.Blog',
		]));
	}

	public function test_needs_upgrade_only_for_unlinked_password_players(): void
	{
		$this->assertTrue(HiveAccountUpgradeService::needsUpgrade([
			'email' => 'p@example.com',
			'hive_account' => '',
		]));
		$this->assertFalse(HiveAccountUpgradeService::needsUpgrade([
			'email' => 'p@example.com',
			'hive_account' => 'alice',
		]));
		$this->assertFalse(HiveAccountUpgradeService::needsUpgrade([
			'email' => 'alice@hive.blog',
			'hive_account' => '',
		]));
		// Invalid stored value is treated as unlinked.
		$this->assertTrue(HiveAccountUpgradeService::needsUpgrade([
			'email' => 'p@example.com',
			'hive_account' => 'Not Valid!',
		]));
	}

	public function test_ownership_message_and_normalize(): void
	{
		$this->assertSame('alice', HiveAccountUpgradeService::normalizeAccount(' Alice '));
		$this->assertSame('alice is my account.', HiveAccountUpgradeService::ownershipMessage(' Alice '));
	}

	public function test_soft_prompt_for_gated_surfaces(): void
	{
		$prompt = $this->service()->softPrompt([
			'email' => 'p@example.com',
			'hive_account' => '',
		], 'game.php?page=settings#hive-upgrade');

		$this->assertTrue($prompt['show']);
		$this->assertTrue($prompt['keepPlaying']);
		$this->assertTrue($prompt['canHiveSigner']);
		$this->assertSame(HiveAccountUpgradeService::CREATE_STATUS_PENDING, $prompt['createStatus']);
		$this->assertSame('game.php?page=settings#hive-upgrade', $prompt['settingsUrl']);
	}

	public function test_upgrade_panel_and_hivesigner_url(): void
	{
		$panel = $this->service()->upgradePanel(
			['email' => 'p@example.com', 'hive_account' => ''],
			'https://moon.example/uni1/game.php?page=settings&mode=hiveSignerReturn'
		);

		$this->assertTrue($panel['needsUpgrade']);
		$this->assertTrue($panel['canHiveSigner']);
		$this->assertFalse($panel['createAvailable']);
		$this->assertStringContainsString('client_id=moon.app', $panel['hiveSignerUrl']);
		$this->assertStringContainsString('scope=login', $panel['hiveSignerUrl']);
		$this->assertStringContainsString('response_type=token', $panel['hiveSignerUrl']);
		$this->assertStringContainsString(rawurlencode('https://moon.example/uni1/game.php?page=settings&mode=hiveSignerReturn'), $panel['hiveSignerUrl']);
	}

	public function test_hivesigner_disabled_without_client_id(): void
	{
		$svc = $this->service(['clientId' => '', 'authorizeUrl' => HiveAccountUpgradeService::DEFAULT_HIVESIGNER_AUTHORIZE, 'meUrl' => HiveAccountUpgradeService::DEFAULT_HIVESIGNER_ME, 'scope' => 'login']);
		$this->assertFalse($svc->isHiveSignerConfigured());
		$this->assertSame('', $svc->buildHiveSignerAuthorizeUrl('https://example/callback'));
	}

	public function test_link_with_signature_success(): void
	{
		$this->validSignatures['alice|sigblob'] = true;
		$result = $this->service()->linkWithSignature(
			['id' => 10, 'email' => 'p@example.com', 'hive_account' => ''],
			'Alice',
			'sigblob',
			1
		);

		$this->assertTrue($result['ok']);
		$this->assertSame('alice', $result['account']);
		$this->assertSame(HiveAccountUpgradeService::METHOD_KEYCHAIN, $result['method']);
		$this->assertSame([['userId' => 10, 'account' => 'alice']], $this->binds);
	}

	public function test_link_with_signature_rejects_bad_inputs(): void
	{
		$svc = $this->service();
		$user = ['id' => 10, 'email' => 'p@example.com', 'hive_account' => ''];

		$this->assertSame(HiveAccountUpgradeService::ERR_EMPTY, $svc->linkWithSignature($user, '', '', 1)['error']);
		$this->assertSame(HiveAccountUpgradeService::ERR_INVALID_ACCOUNT, $svc->linkWithSignature($user, '!!!', 'sig', 1)['error']);
		$this->assertSame(HiveAccountUpgradeService::ERR_INVALID_SIGNATURE, $svc->linkWithSignature($user, 'alice', 'bad', 1)['error']);
	}

	public function test_link_with_signature_rejects_taken_and_already_linked(): void
	{
		$svc = $this->service();
		$this->validSignatures['alice|sig'] = true;
		$this->taken['alice@1'] = 1;

		$taken = $svc->linkWithSignature(
			['id' => 10, 'email' => 'p@example.com', 'hive_account' => ''],
			'alice',
			'sig',
			1
		);
		$this->assertFalse($taken['ok']);
		$this->assertSame(HiveAccountUpgradeService::ERR_TAKEN, $taken['error']);

		$already = $svc->linkWithSignature(
			['id' => 10, 'email' => 'p@example.com', 'hive_account' => 'bob'],
			'alice',
			'sig',
			1
		);
		$this->assertFalse($already['ok']);
		$this->assertSame(HiveAccountUpgradeService::ERR_ALREADY_LINKED, $already['error']);
	}

	public function test_link_with_signature_idempotent_when_same_account(): void
	{
		$result = $this->service()->linkWithSignature(
			['id' => 10, 'email' => 'p@example.com', 'hive_account' => 'alice'],
			'alice',
			'sig',
			1
		);
		$this->assertTrue($result['ok']);
		$this->assertSame([], $this->binds);
	}

	public function test_link_with_hivesigner_token(): void
	{
		$this->signerUsers['tok-1'] = 'Carol';
		$result = $this->service()->linkWithHiveSignerToken(
			['id' => 22, 'email' => 'p@example.com', 'hive_account' => ''],
			'tok-1',
			3
		);

		$this->assertTrue($result['ok']);
		$this->assertSame('carol', $result['account']);
		$this->assertSame(HiveAccountUpgradeService::METHOD_HIVESIGNER, $result['method']);
		$this->assertSame([['userId' => 22, 'account' => 'carol']], $this->binds);
	}

	public function test_link_with_hivesigner_token_errors(): void
	{
		$svc = $this->service();
		$user = ['id' => 22, 'email' => 'p@example.com', 'hive_account' => ''];

		$this->assertSame(HiveAccountUpgradeService::ERR_EMPTY, $svc->linkWithHiveSignerToken($user, '  ', 1)['error']);
		$this->assertSame(HiveAccountUpgradeService::ERR_INVALID_TOKEN, $svc->linkWithHiveSignerToken($user, 'missing', 1)['error']);

		$this->signerUsers['tok'] = '!!!';
		$this->assertSame(HiveAccountUpgradeService::ERR_INVALID_ACCOUNT, $svc->linkWithHiveSignerToken($user, 'tok', 1)['error']);

		$linked = $svc->linkWithHiveSignerToken(
			['id' => 22, 'email' => 'p@example.com', 'hive_account' => 'alice'],
			'tok',
			1
		);
		$this->assertSame(HiveAccountUpgradeService::ERR_ALREADY_LINKED, $linked['error']);
	}
}
