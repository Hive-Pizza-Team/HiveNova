<?php

declare(strict_types=1);

use HiveNova\Core\ArraySeasonSbtGrantStore;
use HiveNova\Core\DatabaseSeasonGrantBadgeSource;
use HiveNova\Core\NftTrackerClient;
use HiveNova\Core\PrestigeBadgeService;
use HiveNova\Core\SeasonSbtBadgeMapper;
use HiveNova\Core\SeasonSbtGrantStore;
use HiveNova\Core\SeasonSbtInventoryService;
use HiveNova\Core\SeasonSbtIssuerService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class SeasonSbtInventoryServiceTest extends TestCase
{
	protected function tearDown(): void
	{
		NftTrackerClient::setFetcher(null);
		parent::tearDown();
	}

	public function testInventoryListsGrantsForUserAndBuildsLegend(): void
	{
		$store = new ArraySeasonSbtGrantStore([
			[
				'universe' => 3,
				'user_id' => 42,
				'hive_user' => 'ga-sm',
				'season_id' => 'u3-s1',
				'tier' => SeasonSbtGrantStore::TIER_RANK,
				'rank' => 1,
				'nft_namespace' => 'sablephalanx',
				'nft_symbol' => 'MOONU3S1',
				'recap_url' => 'https://peakd.com/@moon/s1',
				'image_url' => 'https://example.com/s1.png',
				'game_name' => 'GA',
				'retroactive' => 1,
				'issued_at' => 1700000000,
			],
			[
				'universe' => 3,
				'user_id' => 42,
				'hive_user' => 'ga-sm',
				'season_id' => 'u3-s1',
				'tier' => SeasonSbtGrantStore::TIER_PARTICIPANT,
				'nft_namespace' => 'sablephalanx',
				'nft_symbol' => 'MOONU3S1',
				'issued_at' => 1700000000,
			],
		]);

		$users = [
			42 => [
				'id' => 42,
				'username' => 'CommanderGA',
				'hive_account' => 'ga-sm',
				'register_time' => gmmktime(0, 0, 0, 8, 1, 2026),
			],
		];

		$service = new SeasonSbtInventoryService(
			$store,
			null,
			static fn (int $id, int $uni): ?array => $users[$id] ?? null,
			static fn (string $hive, int $uni): ?array => null,
			false
		);

		$view = $service->inventory(3, 99, 42, null);
		$this->assertSame(42, $view['owner']['userId']);
		$this->assertSame('CommanderGA', $view['owner']['username']);
		$this->assertSame('ga-sm', $view['owner']['hiveAccount']);
		$this->assertFalse($view['owner']['isSelf']);
		$this->assertSame('Aug 2026', $view['owner']['memberSinceDate']);
		$this->assertCount(2, $view['medals']);
		$this->assertSame('Uni3 Season 1 Winner', $view['medals'][0]['title']);
		$this->assertTrue($view['medals'][0]['retroactive']);
		$this->assertSame('https://peakd.com/@moon/s1', $view['medals'][0]['recapUrl']);
		$this->assertSame('', $view['emptyReason']);
		$this->assertCount(4, $view['legend']);
		$this->assertSame('season_winner', $view['legend'][0]['id']);
		$this->assertSame('season_winner_u3_s1', $view['badges'][0]['id']);
	}

	public function testEmptyStatesForMissingHiveAndNoGrants(): void
	{
		$store = new ArraySeasonSbtGrantStore();
		$service = new SeasonSbtInventoryService(
			$store,
			null,
			static fn (int $id, int $uni): ?array => [
				'id' => $id,
				'username' => 'PasswordPilot',
				'hive_account' => '',
				'register_time' => gmmktime(0, 0, 0, 1, 1, 2026),
			],
			static fn (): ?array => null,
			false
		);

		$noHive = $service->inventory(1, 7, 7, null, [
			'empty_no_hive' => 'NO_HIVE',
			'empty_none' => 'NONE',
		]);
		$this->assertSame('NO_HIVE', $noHive['emptyReason']);

		$withHive = new SeasonSbtInventoryService(
			$store,
			null,
			static fn (int $id, int $uni): ?array => [
				'id' => $id,
				'username' => 'Linked',
				'hive_account' => 'alice',
				'register_time' => 1,
			],
			static fn (): ?array => null,
			false
		);
		$none = $withHive->inventory(1, 8, 8, null, ['empty_none' => 'NONE']);
		$this->assertSame('NONE', $none['emptyReason']);
	}

	public function testLookupByHiveFallsBackWhenUserMissing(): void
	{
		$store = new ArraySeasonSbtGrantStore([
			[
				'user_id' => 0,
				'hive_user' => 'frugal-fun',
				'season_id' => 'u3-s2',
				'tier' => SeasonSbtGrantStore::TIER_RANK,
				'rank' => 1,
				'nft_namespace' => 'sablephalanx',
				'nft_symbol' => 'MOONU3S2',
			],
		]);
		$service = new SeasonSbtInventoryService(
			$store,
			null,
			static fn (): ?array => null,
			static fn (): ?array => null,
			false
		);

		$view = $service->inventory(3, 1, null, 'frugal-fun');
		$this->assertSame('frugal-fun', $view['owner']['hiveAccount']);
		$this->assertCount(1, $view['medals']);
		$this->assertSame('Uni3 Season 2 Winner', $view['medals'][0]['title']);
	}

	public function testSoftVerifyMarksMedalWhenTrackerReturnsInstance(): void
	{
		NftTrackerClient::setFetcher(static function (string $url): string {
			self::assertStringContainsString('/nfts/sablephalanx/MOONU3S1', $url);
			self::assertStringContainsString('holder=ga-sm', $url);

			return json_encode([['id' => 'inst-1', 'holder' => 'ga-sm']], JSON_THROW_ON_ERROR);
		});

		$store = new ArraySeasonSbtGrantStore([
			[
				'user_id' => 1,
				'hive_user' => 'ga-sm',
				'season_id' => 'u3-s1',
				'tier' => SeasonSbtGrantStore::TIER_PARTICIPANT,
				'nft_namespace' => 'sablephalanx',
				'nft_symbol' => 'MOONU3S1',
				'instance_id' => 'inst-1',
			],
		]);
		$service = new SeasonSbtInventoryService(
			$store,
			new NftTrackerClient(),
			static fn (): ?array => [
				'id' => 1,
				'username' => 'GA',
				'hive_account' => 'ga-sm',
				'register_time' => 10,
			],
			static fn (): ?array => null,
			true
		);

		$view = $service->inventory(3, 1, 1, null);
		$this->assertTrue($view['trackerChecked']);
		$this->assertTrue($view['medals'][0]['verified']);
	}

	public function testBadgeMapperOrdersWinnerBeforeParticipant(): void
	{
		$badges = SeasonSbtBadgeMapper::badgesFromGrants([
			['season_id' => 'u3-s1', 'tier' => 'participant'],
			['season_id' => 'u3-s1', 'tier' => 'rank', 'rank' => 1],
			['season_id' => 'u3-s2', 'tier' => 'rank', 'rank' => 3],
		]);
		$this->assertSame(
			['season_winner_u3_s1', 'season_top_u3_s2', 'season_participant_u3_s1'],
			array_column($badges, 'id')
		);
	}

	public function testDatabaseSeasonGrantBadgeSourceFeedsNameplate(): void
	{
		$store = new ArraySeasonSbtGrantStore([
			[
				'user_id' => 11,
				'hive_user' => 'alice',
				'season_id' => 'u3-s1',
				'tier' => 'rank',
				'rank' => 1,
			],
		]);
		$source = new DatabaseSeasonGrantBadgeSource($store);
		$badges = $source->badgesForUser(11);
		$this->assertSame('season_winner_u3_s1', $badges[0]['id']);

		$view = (new PrestigeBadgeService(
			null,
			static fn () => null,
			$source
		))->forProfile([
			'id' => 11,
			'hive_account' => '',
			'register_time' => gmmktime(0, 0, 0, 2, 1, 2025),
		]);
		$this->assertSame('Feb 2025', $view['memberSinceDate']);
		$this->assertSame('season_winner_u3_s1', $view['badges'][0]['id']);
	}

	public function testIssuerDryRunIsIdempotentAndBuildsSoulboundPayload(): void
	{
		$store = new ArraySeasonSbtGrantStore();
		$issuer = new SeasonSbtIssuerService($store);
		$csv = <<<CSV
hive_user,tier,rank,game_name
ga-sm,rank,1,GA
ga-sm,participant,,GA
ga-sm,rank,1,GA
not-a-hive!!,participant,,
CSV;
		$rows = SeasonSbtIssuerService::parseCsv($csv);
		$plan = $issuer->plan($rows, [
			'season_id' => 'u3-s1',
			'nft_namespace' => 'sablephalanx',
			'nft_symbol' => 'MOONU3S1',
			'retroactive' => true,
			'recap_url' => 'https://peakd.com/s1',
		], false);

		$this->assertCount(2, $plan['payloads']);
		$this->assertSame('issue', $plan['payloads'][0]['custom_json']['action']);
		$this->assertTrue($plan['payloads'][0]['custom_json']['soulbound']);
		$this->assertContains('RETRO', $plan['payloads'][0]['custom_json']['tags']);
		$this->assertContains('TOP', $plan['payloads'][0]['custom_json']['tags']);
		$this->assertSame(0, $plan['recorded']);

		$reasons = array_column($plan['skipped'], 'reason');
		$this->assertContains('duplicate_input', $reasons);
		$this->assertContains('invalid_hive', $reasons);

		$record = $issuer->plan([$rows[0]], [
			'season_id' => 'u3-s1',
			'nft_namespace' => 'sablephalanx',
			'nft_symbol' => 'MOONU3S1',
			'retroactive' => true,
		], true);
		$this->assertSame(1, $record['recorded']);
		$this->assertTrue($store->exists('u3-s1', 'ga-sm', 'rank'));

		$again = $issuer->plan([$rows[0]], [
			'season_id' => 'u3-s1',
			'nft_namespace' => 'sablephalanx',
			'nft_symbol' => 'MOONU3S1',
		], true);
		$this->assertSame(0, $again['recorded']);
		$this->assertContains('already_granted', array_column($again['skipped'], 'reason'));
	}

	public function testNftTrackerClientRejectsBadInputs(): void
	{
		$client = new NftTrackerClient();
		$this->assertSame([], $client->nftsForHolder('', 'MOONU3S1', 'alice'));
		$this->assertSame([], $client->nftsForHolder('sablephalanx', 'bad-symbol', 'alice'));
		$this->assertSame([], $client->nftsForHolder('sablephalanx', 'MOONU3S1', ''));
	}

	public function testSeasonLabelHelpers(): void
	{
		$this->assertSame('Season 1', SeasonSbtBadgeMapper::seasonLabel('u3-s1'));
		$this->assertSame('Season 12', SeasonSbtBadgeMapper::seasonLabel('moon-season-12'));
		$this->assertSame('u3_s1', SeasonSbtBadgeMapper::slug('u3-s1'));
	}
}
