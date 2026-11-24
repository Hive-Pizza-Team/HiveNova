<?php

namespace HiveNova\Core;

/**
 * Value-based pushing detection (pure). Resources moved from a weaker account to
 * a stronger one are valued in reference units at MarketPlaceService::REFERENCE_RATIO
 * (1 unit = 1 deuterium = 2 crystal = 4 metal). Constants rather than Config
 * columns so no schema migration is needed; tune them here.
 *
 * Points are the players' current total points: the game keeps no cheap record of
 * points at the time of a transfer (statpoints only stores current values and ranks).
 */
class PushingRules
{
	public const WINDOW_SECONDS = 14 * 24 * 3600;

	/** One weaker source sending one receiver at least this much in the window (1M metal). */
	public const PAIR_VALUE_THRESHOLD = 250000.0;

	/**
	 * Funnel: at least FUNNEL_MIN_SOURCES weaker accounts that each sent at least
	 * FUNNEL_MIN_SOURCE_VALUE, together at least FUNNEL_VALUE_THRESHOLD, even when
	 * every one of them stays under the per-pair threshold.
	 */
	public const FUNNEL_MIN_SOURCES = 3;
	public const FUNNEL_MIN_SOURCE_VALUE = 10000.0;
	public const FUNNEL_VALUE_THRESHOLD = 500000.0;

	public const REASON_PAIR = 'pair';
	public const REASON_FUNNEL = 'funnel';

	/**
	 * @param array{0: float|int, 1: float|int, 2: float|int} $ratio
	 */
	public static function resourceValue(float $metal, float $crystal, float $deuterium, array $ratio): float
	{
		return MarketPlaceService::referenceValue(array(901 => $metal, 902 => $crystal, 903 => $deuterium), $ratio);
	}

	/**
	 * Per-receiver review report.
	 *
	 * @param list<array{source_id: int|string, dest_id: int|string, source_name?: string, dest_name?: string, fleet_count: int|string, value: float|int}> $transportPairs
	 * @param list<array{source: int, dest: int, source_name?: string, dest_name?: string, value: float|int}> $tradePushes
	 * @return list<array{dest_id: int, dest_name: string, total_value: float, reasons: list<string>, sources: list<array{source_id: int, source_name: string, transports: int, trades: int, value: float, over_pair_threshold: bool}>}>
	 */
	public static function buildReport(array $transportPairs, array $tradePushes): array
	{
		/** @var array<int, array{dest_name: string, sources: array<int, array{source_id: int, source_name: string, transports: int, trades: int, value: float}>}> $byDest */
		$byDest = array();
		$add = static function (int $src, int $dst, string $srcName, string $dstName, int $transports, int $trades, float $value) use (&$byDest): void {
			if (!isset($byDest[$dst])) {
				$byDest[$dst] = array('dest_name' => $dstName, 'sources' => array());
			}
			if ($byDest[$dst]['dest_name'] === '') {
				$byDest[$dst]['dest_name'] = $dstName;
			}
			if (!isset($byDest[$dst]['sources'][$src])) {
				$byDest[$dst]['sources'][$src] = array('source_id' => $src, 'source_name' => $srcName, 'transports' => 0, 'trades' => 0, 'value' => 0.0);
			}
			if ($byDest[$dst]['sources'][$src]['source_name'] === '') {
				$byDest[$dst]['sources'][$src]['source_name'] = $srcName;
			}
			$byDest[$dst]['sources'][$src]['transports'] += $transports;
			$byDest[$dst]['sources'][$src]['trades'] += $trades;
			$byDest[$dst]['sources'][$src]['value'] += $value;
		};

		foreach ($transportPairs as $row) {
			$add((int) $row['source_id'], (int) $row['dest_id'], (string) ($row['source_name'] ?? ''), (string) ($row['dest_name'] ?? ''),
				(int) $row['fleet_count'], 0, (float) $row['value']);
		}
		foreach ($tradePushes as $push) {
			$add((int) $push['source'], (int) $push['dest'], (string) ($push['source_name'] ?? ''), (string) ($push['dest_name'] ?? ''),
				0, 1, (float) $push['value']);
		}

		$report = array();
		foreach ($byDest as $destId => $dest) {
			$sources = array();
			$reasons = array();
			$funnelSources = 0;
			$funnelValue = 0.0;
			$total = 0.0;
			foreach ($dest['sources'] as $source) {
				$over = $source['value'] >= self::PAIR_VALUE_THRESHOLD;
				if ($over) {
					$reasons[self::REASON_PAIR] = true;
				}
				if ($source['value'] >= self::FUNNEL_MIN_SOURCE_VALUE) {
					$funnelSources++;
					$funnelValue += $source['value'];
				}
				$total += $source['value'];
				$sources[] = $source + array('over_pair_threshold' => $over);
			}
			if ($funnelSources >= self::FUNNEL_MIN_SOURCES && $funnelValue >= self::FUNNEL_VALUE_THRESHOLD) {
				$reasons[self::REASON_FUNNEL] = true;
			}
			if ($reasons === array()) {
				continue;
			}
			usort($sources, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
			$report[] = array(
				'dest_id' => (int) $destId,
				'dest_name' => $dest['dest_name'],
				'total_value' => $total,
				'reasons' => array_keys($reasons),
				'sources' => $sources,
			);
		}
		usort($report, static fn(array $a, array $b): int => $b['total_value'] <=> $a['total_value']);
		return $report;
	}

	/**
	 * Plain-text review mail for admins and operators.
	 *
	 * @param list<array{dest_id: int, dest_name: string, total_value: float, reasons: list<string>, sources: list<array{source_id: int, source_name: string, transports: int, trades: int, value: float, over_pair_threshold: bool}>}> $report
	 */
	public static function reportText(array $report, int $windowStart, int $windowEnd): string
	{
		$lines = array();
		$lines[] = 'Possible pushing (resources moved from weaker to stronger accounts) between '
			. gmdate('Y-m-d H:i', $windowStart) . ' and ' . gmdate('Y-m-d H:i', $windowEnd) . ' UTC.';
		$lines[] = 'Values are in deuterium-equivalent at 4:2:1 (metal/4 + crystal/2 + deuterium). '
			. 'Counted: transports, plus the surplus of lopsided marketplace trades. Points are current points.';
		$lines[] = 'Flags: one source >= ' . self::formatValue(self::PAIR_VALUE_THRESHOLD)
			. '; or >= ' . self::FUNNEL_MIN_SOURCES . ' sources of >= ' . self::formatValue(self::FUNNEL_MIN_SOURCE_VALUE)
			. ' each adding up to >= ' . self::formatValue(self::FUNNEL_VALUE_THRESHOLD) . '. Please review before acting.';
		foreach ($report as $receiver) {
			$why = array();
			foreach ($receiver['reasons'] as $reason) {
				$why[] = $reason === self::REASON_FUNNEL ? 'many-to-one funnel' : 'single source over limit';
			}
			$lines[] = '';
			$lines[] = sprintf(
				'%s (id %d): received %s from %d weaker account(s) [%s]',
				$receiver['dest_name'],
				$receiver['dest_id'],
				self::formatValue($receiver['total_value']),
				count($receiver['sources']),
				implode(', ', $why)
			);
			foreach ($receiver['sources'] as $source) {
				$lines[] = sprintf(
					'  - %s (id %d): %d transport(s), %d trade(s), %s%s',
					$source['source_name'],
					$source['source_id'],
					$source['transports'],
					$source['trades'],
					self::formatValue($source['value']),
					$source['over_pair_threshold'] ? ' (over limit)' : ''
				);
			}
		}
		return implode("\n", $lines) . "\n";
	}

	public static function formatValue(float $value): string
	{
		return number_format(round($value), 0, ',', '.');
	}
}
