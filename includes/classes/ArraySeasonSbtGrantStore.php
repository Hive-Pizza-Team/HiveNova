<?php

namespace HiveNova\Core;

/**
 * In-memory SeasonSbtGrantStore for unit tests and dry-run rehearsal.
 */
class ArraySeasonSbtGrantStore implements SeasonSbtGrantStore
{
	/** @var list<array<string, mixed>> */
	private array $rows = [];

	private int $nextId = 1;

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	public function __construct(array $rows = [])
	{
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$this->insert($row);
		}
	}

	public function listForUser(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}

		return $this->sorted(array_values(array_filter(
			$this->rows,
			static fn (array $row): bool => (int) ($row['user_id'] ?? 0) === $userId
		)));
	}

	public function listForHive(string $hiveUser): array
	{
		$hive = strtolower(trim($hiveUser));
		if ($hive === '') {
			return [];
		}

		return $this->sorted(array_values(array_filter(
			$this->rows,
			static fn (array $row): bool => strtolower((string) ($row['hive_user'] ?? '')) === $hive
		)));
	}

	public function exists(string $seasonId, string $hiveUser, string $tier, string $featId = ''): bool
	{
		$key = self::idempotencyKey($seasonId, $hiveUser, $tier, $featId);
		foreach ($this->rows as $row) {
			if (self::rowKey($row) === $key) {
				return true;
			}
		}

		return false;
	}

	public function insert(array $grant): bool
	{
		$normalized = SeasonSbtGrantNormalizer::normalize($grant);
		if ($normalized === null) {
			return false;
		}
		if ($this->exists(
			$normalized['season_id'],
			$normalized['hive_user'],
			$normalized['tier'],
			$normalized['feat_id']
		)) {
			return false;
		}

		$normalized['id'] = $this->nextId++;
		$this->rows[] = $normalized;

		return true;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function all(): array
	{
		return $this->sorted($this->rows);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private function sorted(array $rows): array
	{
		usort($rows, static function (array $a, array $b): int {
			$season = strcmp((string) ($a['season_id'] ?? ''), (string) ($b['season_id'] ?? ''));
			if ($season !== 0) {
				return $season;
			}
			$tier = strcmp((string) ($a['tier'] ?? ''), (string) ($b['tier'] ?? ''));
			if ($tier !== 0) {
				return $tier;
			}

			return ((int) ($a['rank'] ?? PHP_INT_MAX)) <=> ((int) ($b['rank'] ?? PHP_INT_MAX));
		});

		return $rows;
	}

	public static function idempotencyKey(string $seasonId, string $hiveUser, string $tier, string $featId = ''): string
	{
		return strtolower(trim($seasonId))
			.'|'.strtolower(trim($hiveUser))
			.'|'.strtolower(trim($tier))
			.'|'.strtolower(trim($featId));
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function rowKey(array $row): string
	{
		return self::idempotencyKey(
			(string) ($row['season_id'] ?? ''),
			(string) ($row['hive_user'] ?? ''),
			(string) ($row['tier'] ?? ''),
			(string) ($row['feat_id'] ?? '')
		);
	}
}
