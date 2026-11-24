<?php

namespace HiveNova\Core;

/**
 * Map season_sbt_grants rows to nameplate prestige badges (#631 B2).
 *
 * Prefer season winner, then top placement, then participant. Overflow is
 * capped by PrestigeBadgeService::NAMEPLATE_CAP at the profile layer.
 */
final class SeasonSbtBadgeMapper
{
	/**
	 * @param list<array<string, mixed>> $grants
	 * @param array<string, string> $copy winner, top, participant, feat templates with %s season
	 * @return list<array{id: string, kind: string, label: string, tooltip: string}>
	 */
	public static function badgesFromGrants(array $grants, array $copy = []): array
	{
		$scored = [];
		foreach ($grants as $grant) {
			if (!is_array($grant)) {
				continue;
			}
			$badge = self::badgeFromGrant($grant, $copy);
			if ($badge === null) {
				continue;
			}
			$scored[] = ['score' => self::score($grant), 'badge' => $badge];
		}

		usort($scored, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

		$out = [];
		foreach ($scored as $entry) {
			$out[] = $entry['badge'];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $grant
	 * @param array<string, string> $copy
	 * @return array{id: string, kind: string, label: string, tooltip: string}|null
	 */
	public static function badgeFromGrant(array $grant, array $copy = []): ?array
	{
		$tier = strtolower(trim((string) ($grant['tier'] ?? '')));
		$seasonId = trim((string) ($grant['season_id'] ?? ''));
		$seasonLabel = self::seasonLabel($seasonId);
		$rank = isset($grant['rank']) && $grant['rank'] !== null && $grant['rank'] !== ''
			? (int) $grant['rank']
			: null;

		$slug = self::slug($seasonId);
		if ($tier === SeasonSbtGrantStore::TIER_RANK && $rank === 1) {
			$label = self::fill(
				(string) ($copy['winner'] ?? 'Uni3 %s Winner'),
				[$seasonLabel],
				'Uni3 %s Winner'
			);

			return [
				'id'      => 'season_winner_'.$slug,
				'kind'    => 'season',
				'label'   => $label,
				'tooltip' => $label,
			];
		}

		if ($tier === SeasonSbtGrantStore::TIER_RANK) {
			$label = self::fill(
				(string) ($copy['top'] ?? 'Uni3 %s Top placement'),
				[$seasonLabel],
				'Uni3 %s Top placement'
			);
			if ($rank !== null && $rank > 0) {
				$label .= ' (#'.$rank.')';
			}

			return [
				'id'      => 'season_top_'.$slug,
				'kind'    => 'season',
				'label'   => $label,
				'tooltip' => $label,
			];
		}

		if ($tier === SeasonSbtGrantStore::TIER_PARTICIPANT) {
			$label = self::fill(
				(string) ($copy['participant'] ?? 'Uni3 %s Participant'),
				[$seasonLabel],
				'Uni3 %s Participant'
			);

			return [
				'id'      => 'season_participant_'.$slug,
				'kind'    => 'season',
				'label'   => $label,
				'tooltip' => $label,
			];
		}

		if ($tier === SeasonSbtGrantStore::TIER_FEAT) {
			$feat = trim((string) ($grant['feat_id'] ?? ''));
			$label = self::fill(
				(string) ($copy['feat'] ?? 'Uni3 %s Feat'),
				[$seasonLabel],
				'Uni3 %s Feat'
			);
			if ($feat !== '') {
				$label .= ': '.$feat;
			}

			return [
				'id'      => 'season_feat_'.$slug.($feat !== '' ? '_'.self::slug($feat) : ''),
				'kind'    => 'season',
				'label'   => $label,
				'tooltip' => $label,
			];
		}

		return null;
	}

	public static function seasonLabel(string $seasonId): string
	{
		$seasonId = trim($seasonId);
		if ($seasonId === '') {
			return 'Season';
		}
		if (preg_match('/^u3-s(\d+)$/i', $seasonId, $m)) {
			return 'Season '.(int) $m[1];
		}
		if (preg_match('/(?:^|[^\d])s(?:eason)?[\s_-]*(\d+)$/i', $seasonId, $m)) {
			return 'Season '.(int) $m[1];
		}

		return $seasonId;
	}

	public static function slug(string $value): string
	{
		$slug = strtolower(trim($value));
		$slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
		$slug = trim($slug, '_');

		return $slug !== '' ? $slug : 'season';
	}

	/**
	 * Lower score sorts first (winner → top → feat → participant).
	 *
	 * @param array<string, mixed> $grant
	 */
	public static function priorityScore(array $grant): int
	{
		$tier = strtolower(trim((string) ($grant['tier'] ?? '')));
		$rank = isset($grant['rank']) ? (int) $grant['rank'] : 0;
		if ($tier === SeasonSbtGrantStore::TIER_RANK && $rank === 1) {
			return 0;
		}
		if ($tier === SeasonSbtGrantStore::TIER_RANK) {
			return 10 + max(0, $rank);
		}
		if ($tier === SeasonSbtGrantStore::TIER_FEAT) {
			return 40;
		}

		return 50;
	}

	/**
	 * @param array<string, mixed> $grant
	 */
	private static function score(array $grant): int
	{
		return self::priorityScore($grant);
	}

	/**
	 * @param list<float|int|string> $args
	 */
	private static function fill(string $format, array $args, string $fallback): string
	{
		try {
			$text = vsprintf($format, $args);
			if (is_string($text) && $text !== '') {
				return $text;
			}
		} catch (\Throwable) {
			// fall through
		}

		$text = vsprintf($fallback, $args);

		return is_string($text) ? $text : (string) ($args[0] ?? '');
	}
}
