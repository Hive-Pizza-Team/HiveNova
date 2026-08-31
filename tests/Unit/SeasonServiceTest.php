<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DiscordWebhookService;
use HiveNova\Core\HiveCommentPoster;
use HiveNova\Core\HiveEngineClient;
use HiveNova\Core\HiveEngineTransfer;
use HiveNova\Core\SeasonService;
use HiveNova\Core\Universe;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/InMemorySeasonStore.php';
require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class SeasonServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private InMemorySeasonStore $store;
	private RecordingDatabase $db;
	private int $now = 1_700_000_000;

	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> */
	private array $sends = [];

	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}> */
	private array $blogPosts = [];

	private bool $sendFail = false;

	private bool $blogFail = false;

	/** @var list<array{0: int, 1: string, 2: string, 3: int}> */
	private array $messages = [];

	/** @var list<array{url: string, json: string}> */
	private array $discordPosts = [];

	protected function setUp(): void
	{
		parent::setUp();
		if (!defined('AUTH_USR')) {
			define('AUTH_USR', 0);
		}
		if (!defined('AUTH_ADM')) {
			define('AUTH_ADM', 3);
		}

		$this->store = new InMemorySeasonStore();
		$this->db = new RecordingDatabase();
		$this->swapDatabaseInstance($this->db);
		$this->sends = [];
		$this->blogPosts = [];
		$this->sendFail = false;
		$this->blogFail = false;
		$this->messages = [];
		$this->discordPosts = [];
		DiscordWebhookService::setPoster(function (string $url, string $json): int {
			$this->discordPosts[] = ['url' => $url, 'json' => $json];
			return 204;
		});

		HiveEngineTransfer::setBroadcaster(function (...$args) {
			if ($this->sendFail) {
				return ['error' => 'fail'];
			}
			$this->sends[] = $args;
			return ['trx_id' => 'pay' . count($this->sends)];
		});
		HiveCommentPoster::setBroadcaster(function (...$args) {
			$this->blogPosts[] = $args;
			if ($this->blogFail) {
				return ['error' => 'blog_fail'];
			}
			return ['trx_id' => 'blog' . count($this->blogPosts)];
		});
		HiveCommentPoster::setErrorLogger(static function (): void {
		});
		HiveEngineClient::setFetcher(null);

		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, [1, 2]);
	}

	protected function tearDown(): void
	{
		HiveEngineTransfer::setBroadcaster(null);
		HiveCommentPoster::setBroadcaster(null);
		HiveCommentPoster::setErrorLogger(null);
		HiveEngineClient::setFetcher(null);
		DiscordWebhookService::setPoster(null);
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, []);
		$uni = new ReflectionProperty(Universe::class, 'availableUniverses');
		$uni->setAccessible(true);
		$uni->setValue(null, []);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	private function makeConfig(array $override = []): Config
	{
		return new Config(array_merge([
			'uni' => 2,
			'season_mode' => 1,
			'season_length_seconds' => 604800,
			'season_preclose_seconds' => 14400,
			'season_house_cut_percent' => '10.00',
			'season_min_points' => 50,
			'season_entry_pizza' => '1.000',
			'season_wallet_account' => 'season.wallet',
			'season_wallet_active_key' => '5Ktestwifxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
			'season_blog_account' => 'season.blog',
			'season_blog_posting_key' => '5Kblogwifxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
			'season_id' => 1,
			'season_starts_at' => $this->now,
			'season_closes_at' => $this->now + 604800,
			'season_status' => SeasonService::STATUS_RUNNING,
			'season_last_reminder' => 'start',
			'game_name' => 'LocalMoon',
			'OverviewNewsFrame' => 0,
			'OverviewNewsText' => '',
			'discord_feat_webhook' => '',
			'game_disable' => 1,
			'close_reason' => '',
			'metal_start' => 500,
			'crystal_start' => 500,
			'deuterium_start' => 0,
			'darkmatter_start' => 0,
			'game_speed' => 20000,
			'fleet_speed' => 20000,
			'resource_multiplier' => 8,
		], $override));
	}

	private function service(?int $now = null): SeasonService
	{
		$svc = new SeasonService($this->store, new HiveEngineTransfer(), new HiveEngineClient(), $now ?? $this->now);
		$svc->setMessageSender(function (int $userId, string $subject, string $text, int $uni): void {
			$this->messages[] = [$userId, $subject, $text, $uni];
		});

		return $svc;
	}

	private function transferFor(int $userId, string $from, float $qty = 1.0): array
	{
		return [
			'from'      => $from,
			'to'        => 'season.wallet',
			'quantity'  => $qty,
			'symbol'    => 'PIZZA',
			'memo'      => 'hn-s2-1-' . $userId,
			'trx_id'    => 'trx' . $userId,
			'timestamp' => $this->now + 10,
		];
	}

	public function testLongRunningUniverseIsNotGated(): void
	{
		$config = $this->makeConfig(['season_mode' => 0, 'uni' => 1]);
		$svc = $this->service();
		$this->assertTrue($svc->canPlay(['id' => 9, 'hive_account' => '', 'authlevel' => 0], $config));
		$this->assertFalse($svc->mustRedirect(['id' => 9, 'authlevel' => 0], $config, 'overview'));
		$this->assertSame('skip', $svc->tickUniverse($config));
	}

	public function testDepositWalletUsesSeasonFeeWalletWhenConfigured(): void
	{
		$svc = $this->service();
		$config = $this->makeConfig(['season_wallet_account' => 'moon.uni.three']);
		$this->assertSame('moon.uni.three', $svc->depositWallet($config));
	}

	public function testDepositWalletFallsBackWhenSeasonWalletEmpty(): void
	{
		$svc = $this->service();
		$config = $this->makeConfig(['season_wallet_account' => '  ']);
		$this->assertSame('moon.deposit', $svc->depositWallet($config));
	}

	public function testDepositWalletFallsBackForNonSeasonalUniverse(): void
	{
		$svc = $this->service();
		$config = $this->makeConfig([
			'season_mode' => 0,
			'season_wallet_account' => 'moon.uni.three',
		]);
		$this->assertSame('moon.deposit', $svc->depositWallet($config));
	}

	public function testPlayerNeedsHiveAndEntry(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		$noHive = ['id' => 10, 'hive_account' => '', 'authlevel' => 0];
		$this->assertFalse($svc->canPlay($noHive, $config));
		$this->assertTrue($svc->mustRedirect($noHive, $config, 'overview'));
		$this->assertFalse($svc->mustRedirect($noHive, $config, 'season'));

		$linked = ['id' => 10, 'hive_account' => 'playerone', 'authlevel' => 0];
		$this->assertFalse($svc->canPlay($linked, $config));

		$this->store->insertEntry([
			'universe' => 2, 'season_id' => 1, 'user_id' => 10, 'hive_account' => 'playerone',
			'pizza_amount' => 1, 'trx_id' => 'a', 'created_at' => $this->now,
		]);
		$this->assertTrue($svc->canPlay($linked, $config));
	}

	public function testAdminBypassesGate(): void
	{
		$config = $this->makeConfig();
		$this->assertTrue($this->service()->canPlay(['id' => 1, 'hive_account' => '', 'authlevel' => AUTH_ADM], $config));
	}

	public function testAcceptsEntryDepositOncePerWeek(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		$user = ['id' => 10, 'hive_account' => 'playerone'];
		$ok = $svc->acceptTransfer($config, $user, $this->transferFor(10, 'playerone', 1.5));
		$this->assertTrue($ok['ok']);
		$this->assertSame(1.5, $this->store->sumPool(2, 1));
		$again = $svc->acceptTransfer($config, $user, $this->transferFor(10, 'playerone', 1.5));
		$this->assertSame('already', $again['reason']);
	}

	public function testRejectsEntryAfterCloseAndWrongSeasonMemo(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service($this->now + 604801);
		$result = $svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'playerone'], $this->transferFor(10, 'playerone'));
		$this->assertSame('closed', $result['reason']);

		$svc = $this->service();
		$tx = $this->transferFor(10, 'playerone');
		$tx['memo'] = 'hn-s2-99-10';
		$this->assertSame('wrong_season', $svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'playerone'], $tx)['reason']);
	}

	public function testPayoutsScaleByPointsAndSkipBelowMin(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		foreach ([10 => 'aliceaaa', 11 => 'bobbbbbb', 12 => 'carolccc', 13 => 'daveeeee'] as $id => $hive) {
			$svc->acceptTransfer($config, ['id' => $id, 'hive_account' => $hive], $this->transferFor($id, $hive, 10));
		}
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 300, 'rank' => 1],
			['user_id' => 11, 'hive_account' => 'bobbbbbb', 'authlevel' => 0, 'points' => 200, 'rank' => 2],
			['user_id' => 12, 'hive_account' => 'carolccc', 'authlevel' => 0, 'points' => 100, 'rank' => 3],
			['user_id' => 13, 'hive_account' => 'daveeeee', 'authlevel' => 0, 'points' => 10, 'rank' => 4],
		];
		$this->store->players = [
			['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en'],
		];

		$result = $svc->closeWeek($config);
		$this->assertSame('wiped', $result);
		$this->assertSame([2], $this->store->wiped);

		$sent = array_filter($this->store->payouts, static fn ($row) => $row['status'] === 'sent');
		$this->assertCount(3, $sent);
		$byUser = [];
		foreach ($sent as $row) {
			$byUser[$row['user_id']] = $row['pizza_amount'];
		}
		$this->assertArrayNotHasKey(13, $byUser);
		$pool = 40.0;
		$budget = 36.0;
		$this->assertEqualsWithDelta($budget * 300 / 600, $byUser[10], 0.001);
		$this->assertEqualsWithDelta($budget * 200 / 600, $byUser[11], 0.001);
		$this->assertSame(3, count($this->sends));
		$this->assertSame('LocalMoon season 1 prize', $this->sends[0][4]);
		$this->assertSame('18.00', sprintf('%.2f', $this->sends[0][2]));
		$this->assertSame(1, count($this->blogPosts));
		$this->assertSame('localmoon-u2-season-1', $this->blogPosts[0][3]);
		$week = $this->store->getWeek(2, 1);
		$this->assertSame('blog1', $week['blog_trx_id']);
		$this->assertSame(2, (int) $config->season_id);
		$this->assertSame(SeasonService::STATUS_RUNNING, $config->season_status);
	}

	public function testFailedPayoutDoesNotWipe(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		$svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'aliceaaa'], $this->transferFor(10, 'aliceaaa', 10));
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 100, 'rank' => 1],
		];
		$this->sendFail = true;
		$result = $svc->closeWeek($config);
		$this->assertSame(SeasonService::STATUS_PAYOUT_HOLD, $result);
		$this->assertSame([], $this->store->wiped);
		$this->assertSame(1, (int) $config->season_id);
		$this->assertSame(SeasonService::STATUS_PAYOUT_HOLD, $config->season_status);

		$late = $svc->acceptTransfer($config, ['id' => 11, 'hive_account' => 'bobbbbbb'], $this->transferFor(11, 'bobbbbbb', 10));
		$this->assertSame('closed', $late['reason']);
	}

	public function testRetryAfterFailureWipesAndStartsNextWeek(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		$svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'aliceaaa'], $this->transferFor(10, 'aliceaaa', 10));
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 100, 'rank' => 1],
		];
		$this->sendFail = true;
		$svc->closeWeek($config);
		$this->sendFail = false;
		$result = $svc->retryPayouts($config);
		$this->assertSame('wiped', $result);
		$this->assertSame([2], $this->store->wiped);
		$this->assertSame(2, (int) $config->season_id);
		$this->assertNull($this->store->findEntry(2, 2, 10));
	}

	public function testIdleSeasonalUniverseStartsAWeek(): void
	{
		$config = $this->makeConfig([
			'season_id' => 0,
			'season_status' => SeasonService::STATUS_IDLE,
			'season_starts_at' => 0,
			'season_closes_at' => 0,
			'season_last_reminder' => '',
		]);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];
		$result = $this->service()->tickUniverse($config);
		$this->assertSame('started', $result);
		$this->assertSame(1, (int) $config->season_id);
		$this->assertSame(SeasonService::STATUS_RUNNING, $config->season_status);
		$this->assertNotEmpty($this->messages);
		$this->assertStringContainsString('started', strtolower($config->OverviewNewsText));
	}

	public function testDailyAndPrecloseReminders(): void
	{
		$config = $this->makeConfig(['season_last_reminder' => 'start']);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];
		$svc = $this->service($this->now + 86400);
		$svc->fireReminders($config);
		$this->assertStringContainsString('daily:', $config->season_last_reminder);

		$svc = $this->service($this->now + 604800 - 1000);
		$svc->fireReminders($config);
		$this->assertStringContainsString('preclose', $config->season_last_reminder);
		$this->assertStringContainsString('daily:', $config->season_last_reminder);
	}

	public function testPrecloseDoesNotRetriggerDaily(): void
	{
		$config = $this->makeConfig([
			'season_last_reminder' => 'start|daily:' . gmdate('Y-m-d', $this->now + 604800 - 3600),
		]);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];
		$svc = $this->service($this->now + 604800 - 3600);
		$svc->fireReminders($config);
		$this->assertStringContainsString('preclose', $config->season_last_reminder);
		$this->assertCount(1, $this->messages);
		$this->assertStringContainsString('hour', strtolower($this->messages[0][2]));

		$this->messages = [];
		$svc = $this->service($this->now + 604800 - 2700);
		$svc->fireReminders($config);
		$this->assertSame([], $this->messages);
		$this->assertStringContainsString('daily:', $config->season_last_reminder);
		$this->assertStringContainsString('preclose', $config->season_last_reminder);
	}

	public function testDailySkippedOnceInsidePrecloseWindow(): void
	{
		$config = $this->makeConfig(['season_last_reminder' => 'start']);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];
		$svc = $this->service($this->now + 604800 - 3600);
		$svc->fireReminders($config);
		$this->assertCount(1, $this->messages);
		$this->assertStringContainsString('hour', strtolower($this->messages[0][2]));
		$this->assertStringNotContainsString('day(s)', strtolower($this->messages[0][2]));
		$this->assertStringContainsString('preclose', $config->season_last_reminder);
		$this->assertStringNotContainsString('daily:', $config->season_last_reminder);
	}

	public function testTickSkipsNonSeasonalUniverses(): void
	{
		$main = $this->makeConfig(['uni' => 1, 'season_mode' => 0, 'season_id' => 0, 'season_status' => 'idle']);
		$season = $this->makeConfig(['uni' => 2, 'season_id' => 0, 'season_status' => 'idle', 'season_last_reminder' => '']);
		Config::setInstance($main, 1);
		Config::setInstance($season, 2);
		$this->service()->tick();
		$this->assertSame(0, (int) $main->season_id);
		$this->assertSame(SeasonService::STATUS_IDLE, $main->season_status);
		$this->assertSame(1, (int) $season->season_id);
	}

	public function testConfirmTxHappyPath(): void
	{
		HiveEngineClient::setFetcher(static function () {
			return json_encode([
				'jsonrpc' => '2.0',
				'id' => 1,
				'result' => [
					'action' => 'transfer',
					'sender' => 'playerone',
					'payload' => '{"to":"season.wallet","symbol":"PIZZA","quantity":"2.000","memo":"hn-s2-1-10"}',
					'transactionId' => 'deadbeef',
					'timestamp' => 1700000010,
				],
			]);
		});
		$config = $this->makeConfig();
		$result = $this->service()->confirmTx($config, ['id' => 10, 'hive_account' => 'playerone'], 'deadbeef');
		$this->assertTrue($result['ok']);
		$this->assertNotNull($this->store->findEntry(2, 1, 10));
	}

	public function testConfirmTxFallsBackToWalletHistory(): void
	{
		HiveEngineClient::setFetcher(static function (string $url) {
			if (str_contains($url, 'accountHistory')) {
				return json_encode([[
					'operation' => 'tokens_transfer',
					'from' => 'playerone',
					'to' => 'season.wallet',
					'symbol' => 'PIZZA',
					'quantity' => '1.000',
					'memo' => 'hn-s2-1-10',
					'transactionId' => 'hist1',
					'timestamp' => 1700000010,
				]]);
			}

			return json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => null]);
		});
		$config = $this->makeConfig();
		$this->store->users[10] = ['id' => 10, 'hive_account' => 'playerone', 'universe' => 2, 'authlevel' => 0];
		$result = $this->service()->confirmTx($config, ['id' => 10, 'hive_account' => 'playerone'], 'hist1');
		$this->assertTrue($result['ok']);
		$this->assertNotNull($this->store->findEntry(2, 1, 10));
	}

	public function testIngestWalletRecordsMatchingHistory(): void
	{
		HiveEngineClient::setFetcher(static function () {
			return json_encode([[
				'action' => 'transfer',
				'sender' => 'playerone',
				'payload' => [
					'to' => 'season.wallet',
					'symbol' => 'PIZZA',
					'quantity' => '1.000',
					'memo' => 'hn-s2-1-10',
				],
				'transactionId' => 'hist1',
				'timestamp' => 1700000010,
			]]);
		});
		$config = $this->makeConfig();
		$this->store->users[10] = ['id' => 10, 'hive_account' => 'playerone', 'universe' => 2, 'authlevel' => 0];
		$count = $this->service()->ingestWallet($config);
		$this->assertSame(1, $count);
		$this->assertNotNull($this->store->findEntry(2, 1, 10));
	}

	public function testIngestWalletRejectsHiveMismatch(): void
	{
		HiveEngineClient::setFetcher(static function () {
			return json_encode([[
				'action' => 'transfer',
				'sender' => 'attacker',
				'payload' => [
					'to' => 'season.wallet',
					'symbol' => 'PIZZA',
					'quantity' => '1.000',
					'memo' => 'hn-s2-1-10',
				],
				'transactionId' => 'bad1',
				'timestamp' => 1700000010,
			]]);
		});
		$config = $this->makeConfig();
		$this->store->users[10] = ['id' => 10, 'hive_account' => 'playerone', 'universe' => 2, 'authlevel' => 0];
		$count = $this->service()->ingestWallet($config);
		$this->assertSame(0, $count);
		$this->assertNull($this->store->findEntry(2, 1, 10));
	}

	public function testMemoRoundTrip(): void
	{
		$svc = $this->service();
		$memo = $svc->entryMemo(2, 4, 99);
		$this->assertSame('hn-s2-4-99', $memo);
		$this->assertSame(['universe' => 2, 'season_id' => 4, 'user_id' => 99], $svc->parseMemo($memo));
		$this->assertNull($svc->parseMemo('u2'));
	}

	public function testAllocatePayoutsRemainderGoesToLast(): void
	{
		$svc = $this->service();
		$out = $svc->allocatePayouts([
			['user_id' => 1, 'hive_account' => 'aliceaaa', 'rank' => 1, 'points' => 1],
			['user_id' => 2, 'hive_account' => 'bobbbbbb', 'rank' => 2, 'points' => 1],
			['user_id' => 3, 'hive_account' => 'carolccc', 'rank' => 3, 'points' => 1],
		], 1.000);
		$sum = array_sum(array_column($out, 'pizza_amount'));
		$this->assertEqualsWithDelta(1.000, $sum, 0.0001);
	}

	public function testLoginPanelIsEmptyForLongRunningUniverse(): void
	{
		$panel = $this->service()->loginPanel($this->makeConfig(['season_mode' => 0]));
		$this->assertFalse($panel['seasonal']);
		$this->assertFalse($panel['wipe_live']);
		$this->assertSame('', $panel['entry_pizza']);
		$this->assertSame('', $panel['wallet']);
		$this->assertSame(0, $panel['season_id']);
	}

	public function testLoginPanelShowsLiveWipeCountdownWhileRunning(): void
	{
		$panel = $this->service()->loginPanel($this->makeConfig());
		$this->assertTrue($panel['seasonal']);
		$this->assertTrue($panel['can_enter']);
		$this->assertTrue($panel['wipe_live']);
		$this->assertFalse($panel['wipe_urgent']);
		$this->assertSame(1, $panel['season_id']);
		$this->assertSame(604800, $panel['wipe_seconds']);
		$this->assertSame('1.000', $panel['entry_pizza']);
		$this->assertSame('season.wallet', $panel['wallet']);
	}

	public function testLoginPanelMarksWipeUrgentInsidePrecloseWindow(): void
	{
		$panel = $this->service($this->now + 604800 - 3600)->loginPanel($this->makeConfig());
		$this->assertTrue($panel['wipe_live']);
		$this->assertTrue($panel['wipe_urgent']);
		$this->assertSame(3600, $panel['wipe_seconds']);
	}

	public function testLoginPanelStopsCountdownAfterClose(): void
	{
		$panel = $this->service()->loginPanel($this->makeConfig([
			'season_status' => SeasonService::STATUS_PAYING,
			'season_closes_at' => $this->now - 10,
		]));
		$this->assertFalse($panel['can_enter']);
		$this->assertFalse($panel['wipe_live']);
		$this->assertSame(SeasonService::STATUS_PAYING, $panel['status']);
		$this->assertSame(0, $panel['wipe_seconds']);
	}

	public function testOverviewWipeCountdownWhileRunning(): void
	{
		$wipe = $this->service()->overviewWipeCountdown($this->makeConfig());
		$this->assertTrue($wipe['show']);
		$this->assertSame($this->now + 604800, $wipe['closes_at']);
		$this->assertSame(604800, $wipe['wipe_seconds']);
	}

	public function testOverviewWipeCountdownHiddenWhenNotRunning(): void
	{
		$paying = $this->service()->overviewWipeCountdown($this->makeConfig([
			'season_status' => SeasonService::STATUS_PAYING,
		]));
		$this->assertFalse($paying['show']);

		$permanent = $this->service()->overviewWipeCountdown($this->makeConfig(['season_mode' => 0]));
		$this->assertFalse($permanent['show']);
	}

	public function testDailyReminderKeyedByDaysLeftNotCalendarDate(): void
	{
		// ~6.5 days remaining → ceil = 7. Fifteen minutes later (UTC date rolled)
		// still ceil = 7, so a date-keyed daily would spam the same text.
		$nearMidnight = gmmktime(23, 50, 0, 8, 28, 2026);
		$closes = $nearMidnight + (int) (6.5 * 86400);
		$config = $this->makeConfig([
			'season_last_reminder' => 'start',
			'season_closes_at' => $closes,
			'season_starts_at' => $closes - 604800,
		]);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];

		$this->service($nearMidnight)->fireReminders($config);
		$this->assertCount(1, $this->messages);
		$this->assertStringContainsString('7 day(s)', $this->messages[0][2]);
		$this->assertStringContainsString('daily:7', $config->season_last_reminder);

		$this->messages = [];
		$this->service($nearMidnight + 900)->fireReminders($config);
		$this->assertSame([], $this->messages);
		$this->assertStringContainsString('daily:7', $config->season_last_reminder);

		$this->service($nearMidnight + 86400)->fireReminders($config);
		$this->assertCount(1, $this->messages);
		$this->assertStringContainsString('6 day(s)', $this->messages[0][2]);
		$this->assertStringContainsString('daily:6', $config->season_last_reminder);
		$this->assertStringNotContainsString('daily:7', $config->season_last_reminder);
	}

	public function testBroadcastReminderPostsDiscordOnce(): void
	{
		$token = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
		$config = $this->makeConfig([
			'season_last_reminder' => 'start',
			'discord_feat_webhook' => 'https://discord.com/api/webhooks/123456789012345678/' . $token,
		]);
		Config::setInstance($config, 2);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];

		$svc = $this->service($this->now + 86400);
		$svc->fireReminders($config);
		$this->assertCount(1, $this->discordPosts);
		$payload = json_decode($this->discordPosts[0]['json'], true);
		$this->assertStringContainsString('countdown', strtolower((string) ($payload['content'] ?? '')));
		$this->assertStringContainsString('daily:', $config->season_last_reminder);

		$svc->fireReminders($config);
		$this->assertCount(1, $this->discordPosts);
	}

	public function testBroadcastReminderSkipsDiscordWithoutWebhook(): void
	{
		$config = $this->makeConfig(['season_last_reminder' => 'start', 'discord_feat_webhook' => '']);
		Config::setInstance($config, 2);
		$this->store->players = [['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en']];
		$this->service($this->now + 86400)->fireReminders($config);
		$this->assertSame([], $this->discordPosts);
		$this->assertNotEmpty($this->messages);
		$this->assertNotEmpty($config->OverviewNewsText);
	}

	public function testFailedBlogDoesNotWipe(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		$svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'aliceaaa'], $this->transferFor(10, 'aliceaaa', 10));
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 100, 'rank' => 1],
		];
		$this->blogFail = true;
		$result = $svc->closeWeek($config);
		$this->assertSame(SeasonService::STATUS_BLOG_HOLD, $result);
		$this->assertSame([], $this->store->wiped);
		$this->assertSame(1, (int) $config->season_id);
		$this->assertSame(SeasonService::STATUS_BLOG_HOLD, $config->season_status);
		$this->assertSame(1, count($this->sends));
		$this->assertSame(1, count($this->blogPosts));
	}

	public function testRetryBlogAfterHoldWipesAndStartsNextWeek(): void
	{
		$config = $this->makeConfig();
		$svc = $this->service();
		$svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'aliceaaa'], $this->transferFor(10, 'aliceaaa', 10));
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 100, 'rank' => 1],
		];
		$this->blogFail = true;
		$svc->closeWeek($config);
		$this->blogFail = false;
		$result = $svc->tickUniverse($config);
		$this->assertSame('wiped', $result);
		$this->assertSame([2], $this->store->wiped);
		$this->assertSame(2, (int) $config->season_id);
		$this->assertSame(2, count($this->blogPosts));
		$week = $this->store->getWeek(2, 1);
		$this->assertSame('blog2', $week['blog_trx_id']);
	}

	public function testExistingBlogReceiptSkipsSecondBroadcast(): void
	{
		$config = $this->makeConfig(['season_status' => SeasonService::STATUS_BLOG_HOLD]);
		$this->store->upsertWeek([
			'universe' => 2,
			'season_id' => 1,
			'starts_at' => $this->now,
			'closes_at' => $this->now + 604800,
			'status' => SeasonService::STATUS_BLOG_HOLD,
			'pool_pizza' => 1,
			'house_cut_pizza' => 0.1,
			'payout_budget' => 0.9,
			'blog_permlink' => 'hivenova-u2-season-1',
			'blog_trx_id' => 'already-posted',
		]);
		$result = $this->service()->publishAndWipe($config);
		$this->assertSame('wiped', $result);
		$this->assertSame([2], $this->store->wiped);
		$this->assertSame([], $this->blogPosts);
		$this->assertSame(2, (int) $config->season_id);
	}

	public function testMissingBlogCredentialsEnterBlogHold(): void
	{
		$config = $this->makeConfig([
			'season_blog_account' => '',
			'season_blog_posting_key' => '',
		]);
		$svc = $this->service();
		$svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'aliceaaa'], $this->transferFor(10, 'aliceaaa', 10));
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 100, 'rank' => 1],
		];
		$result = $svc->closeWeek($config);
		$this->assertSame(SeasonService::STATUS_BLOG_HOLD, $result);
		$this->assertSame([], $this->store->wiped);
		$this->assertSame([], $this->blogPosts);
	}

	public function testWipeClosesServerLogsOutAndReopensWithDiscord(): void
	{
		$token = str_repeat('A', 68);
		$config = $this->makeConfig([
			'discord_feat_webhook' => 'https://discord.com/api/webhooks/123456789012345678/' . $token,
		]);
		Config::setInstance($config, 2);
		$svc = $this->service();
		$svc->acceptTransfer($config, ['id' => 10, 'hive_account' => 'aliceaaa'], $this->transferFor(10, 'aliceaaa', 10));
		$this->store->ranking = [
			['user_id' => 10, 'hive_account' => 'aliceaaa', 'authlevel' => 0, 'points' => 100, 'rank' => 1],
		];
		$this->store->players = [
			['id' => 10, 'hive_account' => 'aliceaaa', 'lang' => 'en'],
		];

		$result = $svc->closeWeek($config);
		$this->assertSame('wiped', $result);
		$this->assertSame([2], $this->store->wiped);
		$this->assertSame([2], $this->store->loggedOut);
		$this->assertSame([0], $this->store->gameDisableAtWipe);
		$this->assertSame(1, (int) $config->game_disable);
		$this->assertSame('', (string) $config->close_reason);

		$contents = array_map(
			static fn (array $post): string => (string) (json_decode($post['json'], true)['content'] ?? ''),
			$this->discordPosts
		);
		$this->assertTrue(
			(bool) array_filter($contents, static fn (string $c): bool => str_contains($c, 'Pizza payouts have been sent')),
			'expected payouts Discord: ' . implode(' | ', $contents)
		);
		$this->assertTrue(
			(bool) array_filter($contents, static fn (string $c): bool => str_contains($c, 'recap posted:') && str_contains($c, 'peakd.com/@season.blog/')),
			'expected blog Discord: ' . implode(' | ', $contents)
		);
		$this->assertTrue(
			(bool) array_filter($contents, static fn (string $c): bool => str_contains($c, 'wipe starting')),
			'expected wipe-start Discord: ' . implode(' | ', $contents)
		);
		$this->assertTrue(
			(bool) array_filter($contents, static fn (string $c): bool => str_contains($c, 'wipe complete')),
			'expected wipe-done Discord: ' . implode(' | ', $contents)
		);
	}
}
