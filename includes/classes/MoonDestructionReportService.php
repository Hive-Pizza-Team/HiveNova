<?php

namespace HiveNova\Core;

/**
 * Moon-destruction attempt outcomes for combat reports, Discord, and detection hooks.
 *
 * Documented shape (GitHub #574 / #575):
 * - Player inbox still gets one combat report; destruction is a mode=1 section on that
 *   report (append footer), not a separate RW row. Subject uses sys_mess_destruc_report.
 * - Detection: every Destroy mission TargetEvent that resolves combat calls
 *   EventFirehoseWriter::recordMoonDestruction plus DiscordWebhookService::notifyMoonDestructionAttempt
 *   (universe discord_feat_webhook), including combat repel and failed Stage-2 rolls.
 * - Stage-2 dual rolls run only when ≥1 Battle Fortress / Deathstar-class ship survives Stage-1.
 */
class MoonDestructionReportService
{
	public const BRANCH_REPELLED = 'repelled';

	public const BRANCH_PYRRHIC = 'pyrrhic';

	public const BRANCH_CLEAN = 'clean';

	public const BRANCH_HELD_BLED = 'held_bled';

	public const BRANCH_HELD_INTACT = 'held_intact';

	public const DEFAULT_PUBLIC_BASE = 'https://moon.hive.pizza';

	/**
	 * Post-combat survivor count for Deathstar / Black Moon (Battle Fortress).
	 *
	 * @param array<int|string, array{unit?: array<int, mixed>}> $fleetAttack
	 */
	public static function countSurvivingDestroyers(array $fleetAttack): int
	{
		$total = 0;
		foreach ($fleetAttack as $fleetDetail) {
			$total += (int) FleetMissionAvailability::moonDestroyerCount($fleetDetail['unit'] ?? []);
		}

		return $total;
	}

	/**
	 * @param int $moonDestroySuccess -1 repel / no Stage-2, 0 moon held, 1 moon destroyed
	 */
	public static function classify(int $moonDestroySuccess, bool $fleetDestroySuccess): string
	{
		if ($moonDestroySuccess < 0) {
			return self::BRANCH_REPELLED;
		}

		$moonYes = $moonDestroySuccess === 1;
		if ($moonYes && $fleetDestroySuccess) {
			return self::BRANCH_PYRRHIC;
		}
		if ($moonYes) {
			return self::BRANCH_CLEAN;
		}
		if ($fleetDestroySuccess) {
			return self::BRANCH_HELD_BLED;
		}

		return self::BRANCH_HELD_INTACT;
	}

	public static function firehoseOutcome(string $branch): string
	{
		return match ($branch) {
			self::BRANCH_PYRRHIC, self::BRANCH_CLEAN => EventFirehoseWriter::OUTCOME_DESTROYED,
			self::BRANCH_REPELLED => EventFirehoseWriter::OUTCOME_REPELLED,
			default => EventFirehoseWriter::OUTCOME_HELD,
		};
	}

	/**
	 * Fortress-loss roll label; N/A when Stage-2 did not run.
	 *
	 * @return 'YES'|'NO'|'—'
	 */
	public static function fortressLossLabel(int $moonDestroySuccess, bool $fleetDestroySuccess): string
	{
		if ($moonDestroySuccess < 0) {
			return '—';
		}

		return $fleetDestroySuccess ? 'YES' : 'NO';
	}

	/**
	 * @return 'YES'|'NO'|'—'
	 */
	public static function moonDestroyedLabel(int $moonDestroySuccess): string
	{
		if ($moonDestroySuccess < 0) {
			return '—';
		}

		return $moonDestroySuccess === 1 ? 'YES' : 'NO';
	}

	/**
	 * Language key for the compact outcome line (sys_destruc_tree_*).
	 */
	public static function branchLanguageKey(string $branch): string
	{
		return match ($branch) {
			self::BRANCH_PYRRHIC => 'sys_destruc_tree_pyrrhic',
			self::BRANCH_CLEAN => 'sys_destruc_tree_clean',
			self::BRANCH_HELD_BLED => 'sys_destruc_tree_held_bled',
			self::BRANCH_HELD_INTACT => 'sys_destruc_tree_held_intact',
			default => 'sys_destruc_tree_repelled',
		};
	}

	/**
	 * Public in-game combat report URL for Lore / Discord.
	 */
	public static function reportUrl(string $reportId, string $baseUrl = ''): string
	{
		$reportId = trim($reportId);
		if ($reportId === '') {
			return '';
		}

		$base = rtrim($baseUrl !== '' ? $baseUrl : self::publicBaseUrl(), '/');

		return $base . '/game.php?' . http_build_query([
			'page'   => 'raport',
			'raport' => $reportId,
		]);
	}

	public static function publicBaseUrl(): string
	{
		if (defined('HTTP_HOST') && HTTP_HOST !== '') {
			$scheme = 'https';
			if (defined('PROTOCOL') && is_string(PROTOCOL) && PROTOCOL !== '') {
				$scheme = rtrim(PROTOCOL, ':/');
			}

			$root = '';
			if (defined('HTTP_ROOT') && is_string(HTTP_ROOT)) {
				$root = rtrim(HTTP_ROOT, '/');
			}

			return $scheme . '://' . HTTP_HOST . $root;
		}

		return self::DEFAULT_PUBLIC_BASE;
	}

	/**
	 * Structured snapshot for GenerateReport / Discord (no unverified %-copy).
	 *
	 * @return array{
	 *   branch: string,
	 *   survivingDestroyers: int,
	 *   moonDestroySuccess: int,
	 *   fleetDestroySuccess: bool,
	 *   moonDestroyedLabel: string,
	 *   fortressLossLabel: string,
	 *   moonName: string,
	 *   moonDiameter: int,
	 *   coords: string
	 * }
	 */
	public static function snapshot(
		int $moonDestroySuccess,
		bool $fleetDestroySuccess,
		int $survivingDestroyers,
		string $moonName,
		int $moonDiameter,
		int $galaxy,
		int $system,
		int $planet
	): array {
		$branch = self::classify($moonDestroySuccess, $fleetDestroySuccess);

		return [
			'branch'                => $branch,
			'survivingDestroyers'   => max(0, $survivingDestroyers),
			'moonDestroySuccess'    => $moonDestroySuccess,
			'fleetDestroySuccess'   => $fleetDestroySuccess,
			'moonDestroyedLabel'    => self::moonDestroyedLabel($moonDestroySuccess),
			'fortressLossLabel'     => self::fortressLossLabel($moonDestroySuccess, $fleetDestroySuccess),
			'moonName'              => $moonName,
			'moonDiameter'          => max(0, $moonDiameter),
			'coords'                => sprintf('[%d:%d:%d] (moon)', $galaxy, $system, $planet),
		];
	}
}
