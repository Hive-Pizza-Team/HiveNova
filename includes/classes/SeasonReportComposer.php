<?php

namespace HiveNova\Core;

/**
 * Builds the immutable season-end Hive blog payload from already-loaded data.
 */
class SeasonReportComposer
{
	public const RANKING_LIMIT = 20;
	public const HOF_LIMIT = 10;

	/**
	 * English display names for feats (on-chain body is English-only).
	 *
	 * @var array<string, string>
	 */
	private const FEAT_NAMES = [
		FeatCatalog::FIRST_SHIP => 'First ship',
		FeatCatalog::FIRST_COLONY => 'First colony',
		FeatCatalog::FIRST_EXPEDITION => 'First expedition',
		FeatCatalog::FIRST_GRAVITON => 'First graviton',
		FeatCatalog::FIRST_HYPERSPACE => 'First hyperspace',
		FeatCatalog::FIRST_MOON => 'First moon',
		FeatCatalog::GIVE_MOON => 'First to give a moon',
		FeatCatalog::MOON_DESTRUCTION => 'First moon destruction',
		FeatCatalog::FIRST_DEATHSTAR => 'First deathstar',
		FeatCatalog::LOSE_DEATHSTAR => 'First to lose a deathstar',
		FeatCatalog::DEFEAT_DEATHSTAR => 'First to defeat a deathstar',
		FeatCatalog::RAID_DEFENSES => 'First raider to overcome defenses',
		FeatCatalog::DEFEND_100_SHIPS => 'First defender to repulse 100 ships',
		FeatCatalog::ABANDON_PLANET => 'First to abandon a planet',
		FeatCatalog::ABANDON_HOME => 'First to abandon a homeworld',
	];

	/**
	 * @param array{
	 *   universe: int,
	 *   season_id: int,
	 *   starts_at: int,
	 *   closes_at: int,
	 *   pool_pizza: float|string,
	 *   house_cut_pizza: float|string,
	 *   payout_budget: float|string,
	 *   entrants?: int,
	 *   game_name?: string
	 * } $week
	 * @param list<array{rank: int, username?: string, hive_account: string, points: int|float|string, pizza_amount?: float|string|null}> $ranking
	 * @param list<array{feat_key: string, username?: string, hive_account?: string, claimed_at: int}> $feats
	 * @param list<array{units: int|float|string, result?: string, attacker?: string, defender?: string}> $hallOfFame
	 * @return array{title: string, permlink: string, body: string, tags: list<string>}
	 */
	public function compose(array $week, array $ranking, array $feats, array $hallOfFame): array
	{
		$uni = (int) ($week['universe'] ?? 0);
		$seasonId = (int) ($week['season_id'] ?? 0);
		$startsAt = (int) ($week['starts_at'] ?? 0);
		$closesAt = (int) ($week['closes_at'] ?? 0);
		$pool = (float) ($week['pool_pizza'] ?? 0);
		$house = (float) ($week['house_cut_pizza'] ?? 0);
		$budget = (float) ($week['payout_budget'] ?? 0);
		$entrants = (int) ($week['entrants'] ?? count($ranking));
		$gameName = $this->resolveGameName((string) ($week['game_name'] ?? ''));

		$ranking = array_slice(array_values($ranking), 0, self::RANKING_LIMIT);
		$feats = $this->filterFeatsInWindow($feats, $startsAt, $closesAt);
		$hallOfFame = array_slice(array_values($hallOfFame), 0, self::HOF_LIMIT);

		$title = sprintf('%s Universe %d Season %d Recap', $gameName, $uni, $seasonId);
		$permlink = sprintf('%s-u%d-season-%d', $this->gameSlug($gameName), $uni, $seasonId);
		$tags = ['moon', 'hive-pizza', 'gaming', 'season'];

		$lines = [];
		$lines[] = '# ' . $title;
		$lines[] = '';
		$lines[] = '**Universe:** ' . $uni . ' (short-lived season)';
		$lines[] = '**Window:** ' . $this->formatUtc($startsAt) . ' → ' . $this->formatUtc($closesAt);
		$lines[] = '**Entrants:** ' . $entrants;
		$lines[] = sprintf(
			'**Prize pool:** %s PIZZA (house cut %s · paid out %s)',
			$this->formatPizza($pool),
			$this->formatPizza($house),
			$this->formatPizza($budget)
		);
		$lines[] = '';
		$lines[] = sprintf(
			'Season %d is closed. Rankings were locked, Pizza prizes were paid on Hive Engine, and the universe is about to wipe for the next week. This post is the permanent on-chain record.',
			$seasonId
		);
		$lines[] = '';
		$lines[] = '## Top 20 Ranking';
		$lines[] = '';
		if ($ranking === []) {
			$lines[] = '_No ranked entrants this season._';
		} else {
			$lines[] = '| Rank | Player | Hive | Points | Prize (PIZZA) |';
			$lines[] = '|-----:|--------|------|-------:|--------------:|';
			foreach ($ranking as $row) {
				$lines[] = sprintf(
					'| %d | %s | @%s | %s | %s |',
					(int) ($row['rank'] ?? 0),
					$this->escapeCell((string) ($row['username'] ?? '')),
					$this->escapeCell(strtolower(trim((string) ($row['hive_account'] ?? '')))),
					number_format((float) ($row['points'] ?? 0), 0, '.', ','),
					isset($row['pizza_amount']) && $row['pizza_amount'] !== null && $row['pizza_amount'] !== ''
						? $this->formatPizza((float) $row['pizza_amount'])
						: '—'
				);
			}
			if (count($ranking) < self::RANKING_LIMIT) {
				$lines[] = '';
				$lines[] = '*(Fewer than 20 rows if the season had fewer ranked entrants.)*';
			}
		}

		$lines[] = '';
		$lines[] = '## Feats of Strength';
		$lines[] = '';
		$lines[] = sprintf('Claimed during Season %d:', $seasonId);
		$lines[] = '';
		if ($feats === []) {
			$lines[] = '_No feats claimed during this season window._';
		} else {
			foreach ($feats as $feat) {
				$key = (string) ($feat['feat_key'] ?? '');
				$name = self::FEAT_NAMES[$key] ?? $key;
				$username = trim((string) ($feat['username'] ?? ''));
				$hive = strtolower(trim((string) ($feat['hive_account'] ?? '')));
				$who = $username !== '' ? $username : ($hive !== '' ? '@' . $hive : 'Unknown');
				if ($username !== '' && $hive !== '') {
					$who = $username . ' (@' . $hive . ')';
				}
				$lines[] = sprintf(
					'- **%s** — %s · %s',
					$name,
					$who,
					$this->formatDateUtc((int) ($feat['claimed_at'] ?? 0))
				);
			}
			$lines[] = '';
			$lines[] = '*(Unclaimed feats are omitted.)*';
		}

		$lines[] = '';
		$lines[] = '## Top 10 Hall of Fame';
		$lines[] = '';
		$lines[] = 'Largest battles by units destroyed this season:';
		$lines[] = '';
		if ($hallOfFame === []) {
			$lines[] = '_No Hall of Fame battles recorded this season._';
		} else {
			$lines[] = '| # | Units | Result | Attackers | Defenders |';
			$lines[] = '|--:|------:|--------|-----------|-----------|';
			$i = 1;
			foreach ($hallOfFame as $row) {
				$lines[] = sprintf(
					'| %d | %s | %s | %s | %s |',
					$i,
					number_format((float) ($row['units'] ?? 0), 0, '.', ','),
					$this->escapeCell((string) ($row['result'] ?? '')),
					$this->escapeCell((string) ($row['attacker'] ?? '')),
					$this->escapeCell((string) ($row['defender'] ?? ''))
				);
				$i++;
			}
		}

		$lines[] = '';
		$lines[] = '## Season notes';
		$lines[] = '';
		$lines[] = '- Entry fee was paid in Hive Engine **PIZZA** to the season wallet.';
		$lines[] = '- Only entrants meeting the minimum points threshold were eligible for prizes.';
		$lines[] = '- Play the next season at [moon.hive.pizza](https://moon.hive.pizza)';
		$lines[] = '';
		$lines[] = sprintf('*— %s automated season log. Immutable on Hive.*', $gameName);

		return [
			'title'    => $title,
			'permlink' => $permlink,
			'body'     => implode("\n", $lines),
			'tags'     => $tags,
		];
	}

	/**
	 * @param list<array{feat_key: string, username?: string, hive_account?: string, claimed_at: int}> $feats
	 * @return list<array{feat_key: string, username?: string, hive_account?: string, claimed_at: int}>
	 */
	public function filterFeatsInWindow(array $feats, int $startsAt, int $closesAt): array
	{
		$out = [];
		foreach ($feats as $feat) {
			$at = (int) ($feat['claimed_at'] ?? 0);
			if ($at < $startsAt || ($closesAt > 0 && $at > $closesAt)) {
				continue;
			}
			$out[] = $feat;
		}

		return $out;
	}

	private function resolveGameName(string $gameName): string
	{
		$gameName = trim($gameName);

		return $gameName !== '' ? $gameName : 'HiveNova';
	}

	private function gameSlug(string $gameName): string
	{
		$slug = preg_replace('/[^a-z0-9]+/', '', strtolower($this->resolveGameName($gameName))) ?? '';

		return $slug !== '' ? $slug : 'game';
	}

	private function formatUtc(int $ts): string
	{
		if ($ts <= 0) {
			return 'n/a';
		}

		return gmdate('Y-m-d H:i', $ts) . ' UTC';
	}

	private function formatDateUtc(int $ts): string
	{
		if ($ts <= 0) {
			return 'n/a';
		}

		return gmdate('Y-m-d', $ts);
	}

	private function formatPizza(float $amount): string
	{
		return number_format($amount, 3, '.', '');
	}

	private function escapeCell(string $value): string
	{
		return str_replace(['|', "\n", "\r"], ['/', ' ', ''], $value);
	}
}
