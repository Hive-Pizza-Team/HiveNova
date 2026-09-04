<?php

use HiveNova\Core\FeatCatalog;
use HiveNova\Core\SeasonReportComposer;

use PHPUnit\Framework\TestCase;

class SeasonReportComposerTest extends TestCase
{
	public function testComposeBuildsStablePermlinkAndSectionOrder(): void
	{
		$composer = new SeasonReportComposer();
		$report = $composer->compose(
			[
				'universe' => 2,
				'season_id' => 12,
				'starts_at' => 1_724_025_600,
				'closes_at' => 1_724_630_400,
				'pool_pizza' => 4.8,
				'house_cut_pizza' => 0.48,
				'payout_budget' => 4.32,
				'entrants' => 48,
				'game_name' => 'HiveNova',
			],
			[
				['rank' => 1, 'username' => 'NovaQueen', 'hive_account' => 'novaqueen', 'points' => 1842500, 'pizza_amount' => 0.92],
				['rank' => 2, 'username' => 'PizzaFleet', 'hive_account' => 'pizzafleet', 'points' => 1610200, 'pizza_amount' => 0.804],
			],
			[
				['feat_key' => FeatCatalog::FIRST_SHIP, 'username' => 'ScoutSam', 'hive_account' => 'scoutsam', 'claimed_at' => 1_724_100_000],
				['feat_key' => FeatCatalog::FIRST_MOON, 'username' => 'NovaQueen', 'hive_account' => 'novaqueen', 'claimed_at' => 1_724_200_000],
			],
			[
				['units' => 2450000, 'result' => 'a', 'attacker' => 'NovaQueen', 'defender' => 'MoonRaider'],
			]
		);

		$this->assertSame('HiveNova Universe 2 Season 12 Recap', $report['title']);
		$this->assertSame('hivenova-u2-season-12', $report['permlink']);
		$this->assertSame(['moon', 'hive-pizza', 'gaming', 'season'], $report['tags']);
		$this->assertStringContainsString('4.800 PIZZA', $report['body']);
		$this->assertStringContainsString('0.480', $report['body']);
		$this->assertStringContainsString('4.320', $report['body']);

		$rankPos = strpos($report['body'], '## Top 20 Ranking');
		$featPos = strpos($report['body'], '## Feats of Strength');
		$hofPos = strpos($report['body'], '## Top 10 Hall of Fame');
		$this->assertNotFalse($rankPos);
		$this->assertNotFalse($featPos);
		$this->assertNotFalse($hofPos);
		$this->assertLessThan($featPos, $rankPos);
		$this->assertLessThan($hofPos, $featPos);
		$this->assertStringContainsString('First ship', $report['body']);
		$this->assertStringContainsString('@novaqueen', $report['body']);
		$this->assertStringContainsString('2,450,000', $report['body']);
		$this->assertStringContainsString('| attacker |', $report['body']);
	}

	public function testRankingIsTruncatedToTwenty(): void
	{
		$ranking = [];
		for ($i = 1; $i <= 25; $i++) {
			$ranking[] = [
				'rank' => $i,
				'username' => 'P' . $i,
				'hive_account' => 'player' . $i,
				'points' => 1000 - $i,
				'pizza_amount' => 0.01,
			];
		}
		$body = (new SeasonReportComposer())->compose(
			[
				'universe' => 1,
				'season_id' => 1,
				'starts_at' => 100,
				'closes_at' => 200,
				'pool_pizza' => 1,
				'house_cut_pizza' => 0.1,
				'payout_budget' => 0.9,
			],
			$ranking,
			[],
			[]
		)['body'];

		$this->assertStringContainsString('| 20 |', $body);
		$this->assertStringNotContainsString('| 21 |', $body);
	}

	public function testFeatsOutsideWindowAreFiltered(): void
	{
		$composer = new SeasonReportComposer();
		$report = $composer->compose(
			[
				'universe' => 1,
				'season_id' => 3,
				'starts_at' => 1000,
				'closes_at' => 2000,
				'pool_pizza' => 0,
				'house_cut_pizza' => 0,
				'payout_budget' => 0,
			],
			[],
			[
				['feat_key' => FeatCatalog::FIRST_SHIP, 'username' => 'In', 'hive_account' => 'inacct', 'claimed_at' => 1500],
				['feat_key' => FeatCatalog::FIRST_COLONY, 'username' => 'Before', 'hive_account' => 'before', 'claimed_at' => 500],
				['feat_key' => FeatCatalog::FIRST_MOON, 'username' => 'After', 'hive_account' => 'after', 'claimed_at' => 2500],
			],
			[]
		);

		$this->assertStringContainsString('First ship', $report['body']);
		$this->assertStringContainsString('@inacct', $report['body']);
		$this->assertStringNotContainsString('First colony', $report['body']);
		$this->assertStringNotContainsString('First moon', $report['body']);
	}

	public function testTitleUsesConfiguredGameNameAndUniverse(): void
	{
		$report = (new SeasonReportComposer())->compose(
			[
				'universe' => 3,
				'season_id' => 1,
				'starts_at' => 100,
				'closes_at' => 200,
				'pool_pizza' => 0,
				'house_cut_pizza' => 0,
				'payout_budget' => 0,
				'game_name' => 'Moon',
			],
			[],
			[],
			[]
		);

		$this->assertSame('Moon Universe 3 Season 1 Recap', $report['title']);
		$this->assertSame('moon-u3-season-1', $report['permlink']);
		$this->assertStringContainsString('*— Moon automated season log. Immutable on Hive.*', $report['body']);
	}

	public function testEmptyHofAndFeatsSections(): void
	{
		$body = (new SeasonReportComposer())->compose(
			[
				'universe' => 1,
				'season_id' => 1,
				'starts_at' => 1,
				'closes_at' => 2,
				'pool_pizza' => 0,
				'house_cut_pizza' => 0,
				'payout_budget' => 0,
			],
			[],
			[],
			[]
		)['body'];

		$this->assertStringContainsString('_No ranked entrants this season._', $body);
		$this->assertStringContainsString('_No feats claimed during this season window._', $body);
		$this->assertStringContainsString('_No Hall of Fame battles recorded this season._', $body);
	}

	public function testFeatNamesCoverEntireCatalog(): void
	{
		$names = SeasonReportComposer::featNames();
		foreach (FeatCatalog::keys() as $key) {
			$this->assertArrayHasKey($key, $names, 'Missing English label for ' . $key);
			$this->assertNotSame($key, $names[$key]);
		}
	}

	public function testShipFeatNamesAppearInBody(): void
	{
		$body = (new SeasonReportComposer())->compose(
			[
				'universe' => 3,
				'season_id' => 1,
				'starts_at' => 100,
				'closes_at' => 200,
				'pool_pizza' => 0,
				'house_cut_pizza' => 0,
				'payout_budget' => 0,
			],
			[],
			[
				['feat_key' => FeatCatalog::FIRST_SMALL_CARGO, 'username' => 'Cargo', 'hive_account' => 'cargo', 'claimed_at' => 150],
				['feat_key' => FeatCatalog::FIRST_SPY_PROBE, 'username' => 'Spy', 'hive_account' => 'spy', 'claimed_at' => 160],
			],
			[]
		)['body'];

		$this->assertStringContainsString('First Small Cargo', $body);
		$this->assertStringContainsString('First Spy Probe', $body);
		$this->assertStringNotContainsString('feat_first_small_cargo', $body);
		$this->assertStringNotContainsString('feat_first_spy_probe', $body);
	}

	public function testHallOfFameDropsZeroUnitRowsAndLabelsResults(): void
	{
		$body = (new SeasonReportComposer())->compose(
			[
				'universe' => 3,
				'season_id' => 1,
				'starts_at' => 100,
				'closes_at' => 200,
				'pool_pizza' => 0,
				'house_cut_pizza' => 0,
				'payout_budget' => 0,
			],
			[],
			[],
			[
				['units' => 217000, 'result' => 'a', 'attacker' => 'frugal-fun', 'defender' => 'alkalineyo'],
				['units' => 17000, 'result' => 'w', 'attacker' => 'ga-sm', 'defender' => 'gameexp'],
				['units' => 0, 'result' => 'a', 'attacker' => 'ga-sm', 'defender' => 'gameexp'],
				['units' => 5000, 'result' => 'r', 'attacker' => 'raid', 'defender' => 'hold'],
			]
		)['body'];

		$this->assertStringContainsString('| 1 | 217,000 | attacker |', $body);
		$this->assertStringContainsString('| 2 | 17,000 | draw |', $body);
		$this->assertStringContainsString('| 3 | 5,000 | defender |', $body);
		$this->assertStringNotContainsString('| 0 |', $body);
		$this->assertStringNotContainsString('| a |', $body);
		$this->assertStringNotContainsString('| w |', $body);
		$this->assertStringNotContainsString('| r |', $body);
	}
}
