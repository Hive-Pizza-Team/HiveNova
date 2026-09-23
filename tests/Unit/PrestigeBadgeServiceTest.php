<?php

use HiveNova\Core\HiveEngineClient;
use HiveNova\Core\NullSeasonGrantBadgeSource;
use HiveNova\Core\PrestigeBadgeService;
use HiveNova\Core\PrestigeChainCache;
use HiveNova\Core\SeasonGrantBadgeSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrestigeBadgeServiceTest extends TestCase
{
	private string $cacheDir;

	protected function setUp(): void
	{
		PrestigeChainCache::reset();
		HiveEngineClient::setFetcher(null);
		$this->cacheDir = sys_get_temp_dir().'/prestige-badge-'.bin2hex(random_bytes(4));
		mkdir($this->cacheDir, 0777, true);
	}

	protected function tearDown(): void
	{
		PrestigeChainCache::reset();
		HiveEngineClient::setFetcher(null);
		$this->removeTree($this->cacheDir);
		parent::tearDown();
	}

	#[DataProvider('hivePowerCases')]
	public function testHivePowerTierBoundaries(float $hivePower, ?string $id, ?string $label = null): void
	{
		$tier = PrestigeBadgeService::hivePowerTier($hivePower);
		if ($id === null) {
			$this->assertNull($tier);
			return;
		}

		$this->assertNotNull($tier);
		$this->assertSame($id, $tier['id']);
		if ($label !== null) {
			$this->assertSame($label, $tier['label']);
		}
		$this->assertSame($id, PrestigeBadgeService::hivePowerTier($hivePower)['id']);
	}

	public static function hivePowerCases(): array
	{
		return [
			'negative' => [-1, null],
			'zero' => [0, null],
			'just below floor' => [2499, null],
			'worker min' => [2500, 'worker_bee', 'Worker Bee'],
			'worker top' => [4999, 'worker_bee'],
			'honey min exclusive of worker' => [5000, 'honey_bee', 'Honey Bee'],
			'honey top' => [14999, 'honey_bee'],
			'bumble min' => [15000, 'bumblebee', 'Bumblebee'],
			'bumble top' => [49999, 'bumblebee'],
			'yellowjacket min' => [50000, 'yellowjacket', 'Yellowjacket'],
			'yellowjacket top' => [199999, 'yellowjacket'],
			'giant min' => [200000, 'giant_hornet', 'Giant Hornet'],
			'giant top' => [999999, 'giant_hornet'],
			'killer min' => [1000000, 'killer_bee', 'Killer Bee'],
			'killer top' => [4999999, 'killer_bee'],
			'queen min' => [5000000, 'queen_bee', 'Queen Bee'],
			'queen far above' => [50000000, 'queen_bee'],
			'nan' => [NAN, null],
			'infinity' => [INF, null],
		];
	}

	#[DataProvider('pizzaStakeCases')]
	public function testPizzaStakeTierBoundaries(float $stake, ?string $id, ?string $label = null): void
	{
		$tier = PrestigeBadgeService::pizzaStakeTier($stake);
		if ($id === null) {
			$this->assertNull($tier);
			return;
		}

		$this->assertNotNull($tier);
		$this->assertSame($id, $tier['id']);
		if ($label !== null) {
			$this->assertSame($label, $tier['label']);
		}
	}

	public static function pizzaStakeCases(): array
	{
		return [
			'negative' => [-5, null],
			'zero' => [0, null],
			'just below floor' => [19.999, null],
			'driver l1' => [20, 'pizza_driver_l1', 'Delivery Driver Lv 1'],
			'driver l1 top' => [199, 'pizza_driver_l1'],
			'driver l2' => [200, 'pizza_driver_l2', 'Level Two Driver'],
			'driver l2 top' => [499, 'pizza_driver_l2'],
			'senior' => [500, 'pizza_senior', 'Senior Delivery Driver'],
			'senior top' => [999, 'pizza_senior'],
			'zupervisor' => [1000, 'pizza_zupervisor', 'Zupervisor'],
			'zupervisor top' => [2999, 'pizza_zupervisor'],
			'shift manager' => [3000, 'pizza_shift_manager', 'Shift Manager'],
			'shift top' => [4999, 'pizza_shift_manager'],
			'champions' => [5000, 'pizza_champions', 'Champions Club'],
			'champions top' => [9999, 'pizza_champions'],
			'baron' => [10000, 'pizza_baron', 'Za Baron'],
			'baron top' => [24999, 'pizza_baron'],
			'big cheese' => [25000, 'pizza_big_cheese', 'The Big Cheese'],
			'big cheese top' => [49999, 'pizza_big_cheese'],
			'power 50k' => [50000, 'pizza_50k', 'Pizza Power 50k'],
			'power 50k top' => [99999, 'pizza_50k'],
			'grand barony' => [100000, 'pizza_grand_barony', 'The Grand Barony'],
			'above grand' => [400000, 'pizza_grand_barony'],
			'nan' => [NAN, null],
		];
	}

	public function testHighestTierOnly(): void
	{
		$this->assertSame('honey_bee', PrestigeBadgeService::hivePowerTier(6000)['id']);
		$this->assertSame('pizza_grand_barony', PrestigeBadgeService::pizzaStakeTier(150000)['id']);
	}

	public function testMemberSinceUsesUtcMonthAndLocaleNames(): void
	{
		$registered = gmmktime(0, 0, 0, 9, 15, 2024);
		$this->assertSame('Sep 2024', PrestigeBadgeService::memberSinceLabel($registered));
		$this->assertSame(
			'Mai 2024',
			PrestigeBadgeService::memberSinceLabel(gmmktime(0, 0, 0, 5, 1, 2024), [
				'Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez',
			])
		);
		$this->assertSame('', PrestigeBadgeService::memberSinceLabel(0));
		$this->assertSame('', PrestigeBadgeService::memberSinceLabel(-10));
	}

	public function testLinkedHiveAccountRequiresExistingField(): void
	{
		$this->assertSame('', PrestigeBadgeService::linkedHiveAccount([]));
		$this->assertSame('', PrestigeBadgeService::linkedHiveAccount(['hive_account' => '']));
		$this->assertSame('', PrestigeBadgeService::linkedHiveAccount(['hive_account' => 'Not Valid!']));
		$this->assertSame('alice', PrestigeBadgeService::linkedHiveAccount([
			'username' => 'local-player',
			'hive_account' => ' Alice ',
		]));
	}

	public function testHivePowerOverflowIsASoftNull(): void
	{
		$this->assertNull(PrestigeBadgeService::hivePowerFromAccount(
			['vesting_shares' => 1.0e308],
			['total_vesting_shares' => 1, 'total_vesting_fund_hive' => 1.0e308]
		));
	}

	public function testChainCacheFailureModes(): void
	{
		$thrown = PrestigeChainCache::resolve('alice', static function (): array {
			throw new RuntimeException('lookup failed');
		}, $this->cacheDir, 100);
		$this->assertSame(['hp' => null, 'pizza' => null], $thrown);

		$junk = PrestigeChainCache::resolve('alice', static fn () => 'nope', $this->cacheDir, 200);
		$this->assertSame(['hp' => null, 'pizza' => null], $junk);

		$blank = PrestigeChainCache::resolve('   ', static fn () => ['hp' => 5.0, 'pizza' => 6.0], $this->cacheDir, 300);
		$this->assertSame(5.0, $blank['hp']);
		$this->assertSame(6.0, $blank['pizza']);

		file_put_contents($this->cacheDir.'/'.hash('sha256', 'bob').'.json', '');
		PrestigeChainCache::reset();
		$rebuilt = PrestigeChainCache::resolve('bob', static fn () => ['hp' => 1.0, 'pizza' => 2.0], $this->cacheDir, 400);
		$this->assertSame(1.0, $rebuilt['hp']);
		$this->assertSame(2.0, $rebuilt['pizza']);
	}

	public function testChainAmountAndHivePowerConversion(): void
	{
		$this->assertSame(123.45, PrestigeBadgeService::parseChainAmount('123.450000 VESTS'));
		$this->assertSame(10.0, PrestigeBadgeService::parseChainAmount(' 10 HIVE'));
		$this->assertSame(4.0, PrestigeBadgeService::parseChainAmount(4));
		$this->assertNull(PrestigeBadgeService::parseChainAmount('VESTS'));
		$this->assertNull(PrestigeBadgeService::parseChainAmount(null));
		$this->assertNull(PrestigeBadgeService::parseChainAmount(INF));

		$props = [
			'total_vesting_shares' => '1000000.000000 VESTS',
			'total_vesting_fund_hive' => '500000.000 HIVE',
		];
		$this->assertSame(5000.0, PrestigeBadgeService::hivePowerFromAccount([
			'vesting_shares' => '10000.000000 VESTS',
		], $props));
		$this->assertSame(5000.0, PrestigeBadgeService::hivePowerFromAccount([
			'vesting_shares' => '10000.000000 VESTS',
		], [
			'total_vesting_shares' => '1000000 VESTS',
			'total_vesting_fund_steem' => '500000.000 HIVE',
		]));
		$this->assertNull(PrestigeBadgeService::hivePowerFromAccount(['vesting_shares' => '1 VESTS'], []));
		$this->assertNull(PrestigeBadgeService::hivePowerFromAccount(
			['vesting_shares' => '1 VESTS'],
			['total_vesting_shares' => '0 VESTS', 'total_vesting_fund_hive' => '1 HIVE']
		));
		$this->assertNull(PrestigeBadgeService::hivePowerFromAccount(
			['vesting_shares' => '-1 VESTS'],
			$props
		));
		$this->assertSame('2,500', PrestigeBadgeService::formatAmount(2500));
		$this->assertSame('20.500', PrestigeBadgeService::formatAmount(20.5));
	}

	public function testNoHiveLinkOmitsStakeBadgesButKeepsMemberSince(): void
	{
		HiveEngineClient::setFetcher(function () {
			$this->fail('unlinked profiles must not call Hive-Engine');
		});
		$calls = 0;
		$service = $this->service(function () use (&$calls) {
			$calls++;
			$this->fail('unlinked profiles must not call Hive');
		});

		$view = $service->forProfile([
			'id' => 9,
			'username' => 'password-player',
			'hive_account' => '',
			'register_time' => gmmktime(12, 0, 0, 9, 2, 2024),
		], ['months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']]);

		$this->assertSame(0, $calls);
		$this->assertSame([], $view['badges']);
		$this->assertSame('', $view['hiveAccount']);
		$this->assertSame('', $view['hiveLine']);
		$this->assertSame('Sep 2024', $view['memberSinceDate']);
		$this->assertSame('Member since Sep 2024', $view['memberSince']);
	}

	public function testInvalidHiveLinkOmitsStakeBadges(): void
	{
		HiveEngineClient::setFetcher(function () {
			$this->fail('invalid Hive names must not be looked up');
		});
		$view = $this->service(function () {
			$this->fail('invalid Hive names must not be looked up');
		})->forProfile([
			'id' => 1,
			'hive_account' => 'Not Valid!',
			'register_time' => gmmktime(0, 0, 0, 1, 1, 2020),
		]);

		$this->assertSame([], $view['badges']);
		$this->assertSame('Jan 2020', $view['memberSinceDate']);
	}

	public function testLinkedWalletShowsHighestBeeAndPizzaBadges(): void
	{
		$this->stubChain(5000);
		$view = $this->service($this->rpcForHp(6000))->forProfile([
			'id' => 4,
			'username' => 'ingame',
			'hive_account' => 'Alice',
			'register_time' => gmmktime(0, 0, 0, 9, 1, 2024),
		], [
			'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
		]);

		$this->assertSame('alice', $view['hiveAccount']);
		$this->assertSame('Hive: @alice', $view['hiveLine']);
		$this->assertSame('Sep 2024', $view['memberSinceDate']);
		$this->assertCount(2, $view['badges']);
		$this->assertSame('honey_bee', $view['badges'][0]['id']);
		$this->assertSame('hive_power', $view['badges'][0]['kind']);
		$this->assertSame('Honey Bee — 5,000–15,000 HP', $view['badges'][0]['tooltip']);
		$this->assertSame('pizza_champions', $view['badges'][1]['id']);
		$this->assertSame('pizza_stake', $view['badges'][1]['kind']);
		$this->assertSame('Champions Club — 5,000+ PIZZA staked', $view['badges'][1]['tooltip']);
	}

	public function testBelowFloorLinkedWalletHasNoStakeBadge(): void
	{
		$this->stubChain(19);
		$view = $this->service($this->rpcForHp(100))->forProfile([
			'id' => 4,
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 9, 1, 2024),
		]);

		$this->assertSame([], $view['badges']);
		$this->assertSame('Hive: @alice', $view['hiveLine']);
		$this->assertSame('Member since Sep 2024', $view['memberSince']);
	}

	public function testQueenAndGrandBaronyTooltips(): void
	{
		$this->stubChain(100000);
		$view = $this->service($this->rpcForHp(6000000))->forProfile([
			'hive_account' => 'alice',
			'register_time' => 0,
		]);

		$this->assertSame('', $view['memberSince']);
		$this->assertSame('queen_bee', $view['badges'][0]['id']);
		$this->assertSame('Queen Bee — 5,000,000+ HP', $view['badges'][0]['tooltip']);
		$this->assertSame('pizza_grand_barony', $view['badges'][1]['id']);
		$this->assertSame('The Grand Barony — 100,000+ PIZZA staked', $view['badges'][1]['tooltip']);
	}

	public function testSoftFailOmitsBadgesWhenLookupsFail(): void
	{
		HiveEngineClient::setFetcher(static fn () => false);
		$service = $this->service(static fn (string $method, string $params = '') => ['code' => 1, 'message' => 'rpc down']);
		$view = $service->forProfile([
			'id' => 8,
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 3, 1, 2023),
		]);

		$this->assertSame([], $view['badges']);
		$this->assertSame('Mar 2023', $view['memberSinceDate']);
		$this->assertSame('Hive: @alice', $view['hiveLine']);
	}

	public function testPartialLookupStillShowsTheMetricThatSucceeded(): void
	{
		$this->stubChain(25);
		$view = $this->service(static fn (string $method, string $params = '') => null)->forProfile([
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 1, 1, 2022),
		]);

		$this->assertCount(1, $view['badges']);
		$this->assertSame('pizza_driver_l1', $view['badges'][0]['id']);
	}

	public function testBrokenCopyFallsBackToEnglishTooltip(): void
	{
		$this->stubChain(20);
		$view = $this->service($this->rpcForHp(6000))->forProfile([
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 9, 1, 2024),
		], [
			'member_since' => '%s %s',
			'hive_linked' => '',
			'hp_range' => '%s %s %s %s',
			'pizza_open' => '%s %s %s %s',
			'months' => 'not-an-array',
		]);

		$this->assertSame('Member since Sep 2024', $view['memberSince']);
		$this->assertSame('Hive: @alice', $view['hiveLine']);
		$this->assertSame('Honey Bee — 5,000–15,000 HP', $view['badges'][0]['tooltip']);
		$this->assertSame('Delivery Driver Lv 1 — 20+ PIZZA staked', $view['badges'][1]['tooltip']);
	}

	public function testSeasonGrantHookIsEmptyUntilTableExists(): void
	{
		$source = new NullSeasonGrantBadgeSource();
		$this->assertSame([], $source->badgesForUser(1));

		$this->stubChain(0);
		$view = $this->service($this->rpcForHp(100), $source)->forProfile([
			'id' => 3,
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 1, 1, 2021),
		]);
		$this->assertSame([], $view['badges']);
	}

	public function testSeasonGrantHookAppendsAndNameplateCapsAtFour(): void
	{
		$this->stubChain(20);
		$source = new class implements SeasonGrantBadgeSource {
			public function badgesForUser(int $userId): array
			{
				return [
					'skip-me',
					['id' => '', 'label' => 'Missing'],
					['id' => 'season_winner', 'label' => 'Uni3 Season 1 Winner', 'kind' => 'season'],
					['id' => 'season_winner_2', 'label' => 'Uni3 Season 2 Winner', 'tooltip' => 'Uni3 Season 2 Winner'],
					['id' => 'season_winner_3', 'label' => 'Uni3 Season 3 Winner'],
				];
			}
		};

		$view = $this->service($this->rpcForHp(6000), $source)->forProfile([
			'id' => 11,
			'hive_account' => 'alice',
			'register_time' => 10,
		]);

		$this->assertCount(PrestigeBadgeService::NAMEPLATE_CAP, $view['badges']);
		$this->assertSame(
			['honey_bee', 'pizza_driver_l1', 'season_winner', 'season_winner_2'],
			array_column($view['badges'], 'id')
		);
		$this->assertSame('season', $view['badges'][2]['kind']);
		$this->assertSame('Uni3 Season 1 Winner', $view['badges'][2]['tooltip']);
	}

	public function testSeasonGrantFailuresAreIgnored(): void
	{
		$source = new class implements SeasonGrantBadgeSource {
			public function badgesForUser(int $userId): array
			{
				throw new RuntimeException('grants table missing');
			}
		};
		$this->stubChain(0);
		$view = $this->service($this->rpcForHp(100), $source)->forProfile([
			'id' => 2,
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 1, 1, 2021),
		]);
		$this->assertSame('Jan 2021', $view['memberSinceDate']);
		$this->assertSame([], $view['badges']);
	}

	public function testUserWithoutIdSkipsSeasonSource(): void
	{
		$source = new class implements SeasonGrantBadgeSource {
			public function badgesForUser(int $userId): array
			{
				throw new RuntimeException('should not be asked');
			}
		};
		$view = $this->service(static fn () => null, $source)->forProfile([
			'hive_account' => '',
			'register_time' => gmmktime(0, 0, 0, 6, 1, 2024),
		]);
		$this->assertSame('Jun 2024', $view['memberSinceDate']);
	}

	public function testCacheSkipsFreshLookupsAndServesStaleOnFailure(): void
	{
		$hp = 6000.0;
		$pizza = 20.0;
		$fail = false;
		HiveEngineClient::setFetcher(function () use (&$pizza, &$fail) {
			if ($fail) {
				return false;
			}
			return json_encode(['jsonrpc' => '2.0', 'result' => [['stake' => $pizza]]]);
		});
		$service = $this->service(function (string $method, string $params = '') use (&$hp, &$fail) {
			if ($fail) {
				throw new RuntimeException('hive down');
			}
			if ($method === 'condenser_api.get_dynamic_global_properties') {
				return [
					'total_vesting_shares' => '1000000 VESTS',
					'total_vesting_fund_hive' => '1000000 HIVE',
				];
			}

			return [[
				'name' => 'alice',
				'vesting_shares' => $hp.' VESTS',
			]];
		});

		$t0 = 1_700_000_000;
		$first = $service->metrics('alice', $t0);
		$this->assertSame(6000.0, $first['hp']);
		$this->assertSame(20.0, $first['pizza']);

		$cached = $service->metrics('alice', $t0 + PrestigeChainCache::TTL_SECONDS - 1);
		$this->assertSame($first, $cached);

		$fail = true;
		$staleAt = $t0 + PrestigeChainCache::TTL_SECONDS;
		$stale = $service->metrics('alice', $staleAt);
		$this->assertSame(6000.0, $stale['hp']);
		$this->assertSame(20.0, $stale['pizza']);

		$cooled = $service->metrics('alice', $staleAt + 10);
		$this->assertSame($stale, $cooled);

		PrestigeChainCache::reset();
		$fromDisk = $service->metrics('alice', $staleAt + 11);
		$this->assertSame($stale, $fromDisk);
	}

	public function testCacheDropsValuesOlderThanStaleWindow(): void
	{
		HiveEngineClient::setFetcher(static fn () => false);
		$service = $this->service(static fn () => null);
		$t0 = 1_700_000_000;
		$this->seedCacheFile('alice', [
			'hp' => 8000,
			'hp_at' => $t0,
			'pizza' => 50,
			'pizza_at' => $t0,
		]);

		$expired = $service->metrics('alice', $t0 + PrestigeChainCache::STALE_SECONDS);
		$this->assertNull($expired['hp']);
		$this->assertNull($expired['pizza']);
	}

	public function testCacheIgnoresCorruptFileAndNonArrayFetch(): void
	{
		file_put_contents($this->cacheDir.'/'.hash('sha256', 'alice').'.json', '{not-json');
		$calls = 0;
		HiveEngineClient::setFetcher(function () use (&$calls) {
			$calls++;
			return 'nope';
		});
		$service = $this->service(static fn (string $method, string $params = '') => 'nope');
		$view = $service->metrics('alice', 1_800_000_000);
		$this->assertNull($view['hp']);
		$this->assertNull($view['pizza']);
		$this->assertSame(1, $calls);

		$again = $service->metrics('alice', 1_800_000_010);
		$this->assertSame($view, $again);
	}

	public function testMalformedAccountResponses(): void
	{
		HiveEngineClient::setFetcher(static fn () => json_encode([
			'jsonrpc' => '2.0',
			'result' => [],
		]));

		$missing = $this->service(static fn (string $method, string $params = '') => $method === 'condenser_api.get_accounts' ? [] : null)
			->metrics('alice', 50);
		$this->assertSame(0.0, $missing['hp']);
		$this->assertSame(0.0, $missing['pizza']);

		PrestigeChainCache::reset();
		$this->removeTree($this->cacheDir);
		mkdir($this->cacheDir, 0777, true);
		$badRow = $this->service(static fn (string $method, string $params = '') => $method === 'condenser_api.get_accounts' ? ['nope'] : ['code' => 1, 'message' => 'x'])
			->metrics('bob', 50);
		$this->assertSame(0.0, $badRow['hp']);

		PrestigeChainCache::reset();
		$propsDown = $this->service(static function (string $method, string $params = '') {
			if ($method === 'condenser_api.get_accounts') {
				return [['name' => 'alice', 'vesting_shares' => '1000 VESTS']];
			}
			return ['code' => 10, 'message' => 'props down'];
		})->metrics('carol', 50);
		$this->assertNull($propsDown['hp']);

		PrestigeChainCache::reset();
		$unparsed = $this->service(static function (string $method, string $params = '') {
			if ($method === 'condenser_api.get_accounts') {
				return [['name' => 'alice', 'vesting_shares' => '1000 VESTS']];
			}
			return ['total_vesting_shares' => '0'];
		})->metrics('dave', 50);
		$this->assertNull($unparsed['hp']);
	}

	public function testEngineExceptionIsSoftFailure(): void
	{
		$engine = new class extends HiveEngineClient {
			public function tokenStake(string $account, string $symbol = 'PIZZA'): ?float
			{
				throw new RuntimeException('engine exploded');
			}
		};
		$service = new PrestigeBadgeService($engine, $this->rpcForHp(2500), null, $this->cacheDir);
		$view = $service->forProfile([
			'hive_account' => 'alice',
			'register_time' => gmmktime(0, 0, 0, 2, 1, 2024),
		]);
		$this->assertSame('worker_bee', $view['badges'][0]['id']);
		$this->assertCount(1, $view['badges']);
	}

	public function testCapDropsJunkAndHonorsZero(): void
	{
		$this->assertSame([], PrestigeBadgeService::capBadges([
			'nope',
			['id' => '', 'label' => 'x'],
			['label' => 'only'],
		], 4));
		$this->assertSame([], PrestigeBadgeService::capBadges([
			['id' => 'worker_bee', 'label' => 'Worker Bee', 'kind' => ''],
		], 0));
		$capped = PrestigeBadgeService::capBadges([
			['id' => 'worker_bee', 'label' => 'Worker Bee', 'kind' => ''],
		]);
		$this->assertSame('season', $capped[0]['kind']);
		$this->assertSame('Worker Bee', $capped[0]['tooltip']);
	}

	public function testDefaultCacheDirectoryAndUnwritableDir(): void
	{
		$dir = PrestigeChainCache::defaultCacheDir();
		$this->assertStringContainsString('prestige-chain', $dir);

		$blocker = $this->cacheDir.'/not-a-dir';
		file_put_contents($blocker, 'x');
		HiveEngineClient::setFetcher(static fn () => json_encode([
			'jsonrpc' => '2.0',
			'result' => [['stake' => 20]],
		]));
		$service = new PrestigeBadgeService(new HiveEngineClient(), $this->rpcForHp(2500), null, $blocker);
		$metrics = $service->metrics('alice', 10);
		$this->assertSame(2500.0, $metrics['hp']);
		PrestigeChainCache::reset();
		$refetched = $service->metrics('alice', 11);
		$this->assertSame(2500.0, $refetched['hp']);
	}

	public function testProfileTemplateRendersBadgesAndMemberSince(): void
	{
		$card = (string) file_get_contents(ROOT_PATH.'styles/templates/game/page.playerCard.default.tpl');
		$this->assertStringContainsString('shared.prestige.badges.tpl', $card);
		$this->assertStringContainsString('pl_member_since_label', $card);
		$this->assertStringContainsString('register_time', (string) file_get_contents(ROOT_PATH.'includes/pages/game/ShowPlayerCardPage.php'));

		$smarty = new Smarty();
		$compile = $this->cacheDir.'/smarty';
		mkdir($compile, 0777, true);
		$smarty->setTemplateDir(ROOT_PATH.'styles/templates/game/');
		$smarty->setCompileDir($compile);
		$smarty->assign('badges', [
			['id' => 'honey_bee', 'kind' => 'hive_power', 'label' => 'Honey Bee', 'tooltip' => 'Honey Bee — 5,000–15,000 HP'],
			['id' => 'pizza_driver_l1', 'kind' => 'pizza_stake', 'label' => 'Delivery Driver Lv 1', 'tooltip' => 'Delivery Driver Lv 1 — 20+ PIZZA staked'],
			['id' => 'season_winner', 'kind' => 'season', 'label' => 'Winner', 'tooltip' => 'Winner'],
			['id' => 'extra_1', 'kind' => 'season', 'label' => 'Extra 1', 'tooltip' => 'Extra 1'],
			['id' => 'extra_2', 'kind' => 'season', 'label' => 'Extra 2', 'tooltip' => 'Extra 2'],
		]);
		$html = $smarty->fetch('shared.prestige.badges.tpl');
		$this->assertStringContainsString('prestige-badge--honey_bee', $html);
		$this->assertStringContainsString('data-tooltip-content="Honey Bee — 5,000–15,000 HP"', $html);
		$this->assertStringContainsString('prestige-badge--pizza_driver_l1', $html);
		$this->assertStringContainsString('prestige-badge--season_winner', $html);
		$this->assertStringNotContainsString('extra_2', $html);
		$this->assertSame(4, substr_count($html, 'prestige-badge prestige-badge--'));
	}

	/**
	 * @param array<string, mixed> $record
	 */
	private function seedCacheFile(string $account, array $record): void
	{
		file_put_contents(
			$this->cacheDir.'/'.hash('sha256', $account).'.json',
			json_encode($record)
		);
	}

	private function stubChain(float $stake): void
	{
		HiveEngineClient::setFetcher(static function () use ($stake) {
			return json_encode([
				'jsonrpc' => '2.0',
				'result' => [['stake' => $stake]],
			]);
		});
	}

	private function rpcForHp(float $hivePower): callable
	{
		return function (string $method, string $params = '') use ($hivePower) {
			if ($method === 'condenser_api.get_dynamic_global_properties') {
				return [
					'total_vesting_shares' => '1000000 VESTS',
					'total_vesting_fund_hive' => '1000000 HIVE',
				];
			}

			return [[
				'name' => 'alice',
				'vesting_shares' => $hivePower.' VESTS',
			]];
		};
	}

	/**
	 * @param SeasonGrantBadgeSource|null $grants
	 */
	private function service(?callable $rpc = null, ?SeasonGrantBadgeSource $grants = null, ?string $dir = null): PrestigeBadgeService
	{
		return new PrestigeBadgeService(
			new HiveEngineClient(),
			$rpc,
			$grants,
			$dir ?? $this->cacheDir
		);
	}

	private function removeTree(string $path): void
	{
		if (!file_exists($path)) {
			return;
		}
		if (is_file($path)) {
			unlink($path);
			return;
		}
		foreach (scandir($path) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$this->removeTree($path.'/'.$entry);
		}
		rmdir($path);
	}
}
