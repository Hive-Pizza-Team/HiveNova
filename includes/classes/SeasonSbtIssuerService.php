<?php

namespace HiveNova\Core;

/**
 * Build idempotent HAF nft_tracker issue payloads from eligibility rows (#628 / #630).
 *
 * Does not broadcast. Dry-run returns custom_json bodies; --record-only can
 * write grants after an offline mint.
 */
class SeasonSbtIssuerService
{
	public function __construct(
		private readonly SeasonSbtGrantStore $store = new ArraySeasonSbtGrantStore(),
	) {
	}

	/**
	 * @param list<array<string, mixed>> $rows CSV-like rows
	 * @param array{
	 *     season_id: string,
	 *     nft_namespace: string,
	 *     nft_symbol: string,
	 *     universe?: int,
	 *     retroactive?: bool,
	 *     recap_url?: string,
	 *     image_url?: string,
	 *     tags?: list<string>,
	 *     window_start?: string,
	 *     window_end?: string,
	 *     issued_at?: int
	 * } $meta
	 * @return array{
	 *     payloads: list<array{hive_user: string, custom_json: array<string, mixed>, grant: array<string, mixed>}>,
	 *     skipped: list<array{hive_user: string, reason: string}>,
	 *     recorded: int
	 * }
	 */
	public function plan(array $rows, array $meta, bool $record = false): array
	{
		$seasonId = trim((string) ($meta['season_id'] ?? ''));
		$namespace = strtolower(trim((string) ($meta['nft_namespace'] ?? '')));
		$symbol = strtoupper(trim((string) ($meta['nft_symbol'] ?? '')));
		if ($seasonId === '' || $namespace === '' || $symbol === '') {
			return ['payloads' => [], 'skipped' => [['hive_user' => '', 'reason' => 'missing_meta']], 'recorded' => 0];
		}

		$payloads = [];
		$skipped = [];
		$recorded = 0;
		$seen = [];

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$hive = strtolower(trim((string) ($row['hive_user'] ?? $row['hive'] ?? $row['account'] ?? '')));
			$tier = strtolower(trim((string) ($row['tier'] ?? SeasonSbtGrantStore::TIER_PARTICIPANT)));
			$featId = trim((string) ($row['feat_id'] ?? ''));
			if ($hive === '' || !HiveUtil::isAccountValid($hive)) {
				$skipped[] = ['hive_user' => $hive, 'reason' => 'invalid_hive'];
				continue;
			}
			if (!in_array($tier, [
				SeasonSbtGrantStore::TIER_PARTICIPANT,
				SeasonSbtGrantStore::TIER_RANK,
				SeasonSbtGrantStore::TIER_FEAT,
			], true)) {
				$skipped[] = ['hive_user' => $hive, 'reason' => 'invalid_tier'];
				continue;
			}

			$dedupe = ArraySeasonSbtGrantStore::idempotencyKey($seasonId, $hive, $tier, $featId);
			if (isset($seen[$dedupe])) {
				$skipped[] = ['hive_user' => $hive, 'reason' => 'duplicate_input'];
				continue;
			}
			$seen[$dedupe] = true;

			if ($this->store->exists($seasonId, $hive, $tier, $featId)) {
				$skipped[] = ['hive_user' => $hive, 'reason' => 'already_granted'];
				continue;
			}

			$rank = isset($row['rank']) && $row['rank'] !== '' && $row['rank'] !== null
				? (int) $row['rank']
				: null;
			$universe = (int) ($meta['universe'] ?? $row['universe'] ?? 0);
			$tags = self::tagsFor($meta, $tier, !empty($meta['retroactive']));
			$data = [
				'season_id'    => $seasonId,
				'uni'          => $universe,
				'tier'         => $tier,
				'rank'         => $rank,
				'feat_id'      => $featId !== '' ? $featId : null,
				'game_name'    => (string) ($row['game_name'] ?? $row['username'] ?? ''),
				'hive_user'    => $hive,
				'window_start' => (string) ($meta['window_start'] ?? ''),
				'window_end'   => (string) ($meta['window_end'] ?? ''),
				'points'       => isset($row['points']) ? (int) $row['points'] : null,
				'image'        => (string) ($meta['image_url'] ?? $row['image_url'] ?? ''),
				'recap_url'    => (string) ($meta['recap_url'] ?? ''),
				'retroactive'  => !empty($meta['retroactive']),
			];

			$customJson = [
				'action'    => 'issue',
				'namespace' => $namespace,
				'symbol'    => $symbol,
				'to'        => $hive,
				'soulbound' => true,
				'tags'      => $tags,
				'data'      => $data,
			];

			$grant = [
				'universe'      => $universe,
				'user_id'       => (int) ($row['user_id'] ?? 0),
				'hive_user'     => $hive,
				'season_id'     => $seasonId,
				'tier'          => $tier,
				'rank'          => $rank,
				'feat_id'       => $featId,
				'nft_namespace' => $namespace,
				'nft_symbol'    => $symbol,
				'instance_id'   => (string) ($row['instance_id'] ?? ''),
				'trx_id'        => (string) ($row['trx_id'] ?? ''),
				'recap_url'     => (string) ($meta['recap_url'] ?? ''),
				'image_url'     => (string) ($meta['image_url'] ?? $row['image_url'] ?? ''),
				'game_name'     => (string) ($row['game_name'] ?? $row['username'] ?? ''),
				'data_json'     => $data,
				'retroactive'   => !empty($meta['retroactive']) ? 1 : 0,
				'issued_at'     => (int) ($meta['issued_at'] ?? time()),
			];

			if ($record) {
				if ($this->store->insert($grant)) {
					$recorded++;
				} else {
					$skipped[] = ['hive_user' => $hive, 'reason' => 'record_failed'];
					continue;
				}
			}

			$payloads[] = [
				'hive_user'   => $hive,
				'custom_json' => $customJson,
				'grant'       => $grant,
			];
		}

		return [
			'payloads' => $payloads,
			'skipped'  => $skipped,
			'recorded' => $recorded,
		];
	}

	/**
	 * Parse a simple CSV with header row into associative rows.
	 *
	 * @return list<array<string, string>>
	 */
	public static function parseCsv(string $csv): array
	{
		$csv = trim($csv);
		if ($csv === '') {
			return [];
		}
		$lines = preg_split('/\R/', $csv) ?: [];
		$header = null;
		$out = [];
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}
			$cells = str_getcsv($line, ',', '"', '\\');
			if ($header === null) {
				$header = array_map(
					static fn ($h): string => strtolower(trim((string) $h)),
					$cells
				);
				continue;
			}
			$row = [];
			foreach ($header as $i => $key) {
				if ($key === '') {
					continue;
				}
				$row[$key] = trim((string) ($cells[$i] ?? ''));
			}
			if ($row !== []) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return list<string>
	 */
	public static function tagsFor(array $meta, string $tier, bool $retroactive): array
	{
		$tags = [];
		if (isset($meta['tags']) && is_array($meta['tags'])) {
			foreach ($meta['tags'] as $tag) {
				$tag = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $tag) ?? '', 0, 8));
				if ($tag !== '') {
					$tags[] = $tag;
				}
			}
		}

		$seasonId = (string) ($meta['season_id'] ?? '');
		if (preg_match('/s(\d+)$/i', $seasonId, $m)) {
			$tags[] = 'S'.(int) $m[1];
		}

		$tags[] = match ($tier) {
			SeasonSbtGrantStore::TIER_RANK => 'TOP',
			SeasonSbtGrantStore::TIER_FEAT => 'FEAT',
			default => 'PART',
		};
		if ($retroactive) {
			$tags[] = 'RETRO';
		}

		$unique = [];
		foreach ($tags as $tag) {
			$unique[$tag] = $tag;
			if (count($unique) >= 4) {
				break;
			}
		}

		return array_values($unique);
	}
}
