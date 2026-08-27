<?php

namespace HiveNova\Core;

/**
 * Per-universe seasonal-universe operator settings. Active key is write-only.
 */
class SeasonAdminConfig
{
	public const KEY_FIELD = 'season_wallet_active_key';
	public const BLOG_KEY_FIELD = 'season_blog_posting_key';
	public const REDACTED = 'CHANGED';
	public const SPEED_BASE = 2500;
	public const SPEED_FACTOR = 8;

	/**
	 * @param array<string, mixed> $stored
	 * @param array<string, mixed> $posted
	 * @return array{apply: array<string, mixed>, log_old: array<string, mixed>, log_new: array<string, mixed>, template: array<string, mixed>}
	 */
	public static function applyPosted(array $stored, array $posted): array
	{
		$apply = [];

		$apply['season_mode'] = !empty($posted['season_mode']) ? 1 : 0;

		$length = (int) ($posted['season_length_seconds'] ?? 0);
		if ($length >= 3600) {
			$apply['season_length_seconds'] = $length;
		}

		$preclose = (int) ($posted['season_preclose_seconds'] ?? 0);
		if ($preclose >= 60) {
			$apply['season_preclose_seconds'] = $preclose;
		}

		$cut = (float) ($posted['season_house_cut_percent'] ?? -1);
		if ($cut >= 0 && $cut <= 100) {
			$apply['season_house_cut_percent'] = sprintf('%.2f', $cut);
		}

		$minPoints = (int) ($posted['season_min_points'] ?? -1);
		if ($minPoints >= 0) {
			$apply['season_min_points'] = $minPoints;
		}

		$entry = (float) ($posted['season_entry_pizza'] ?? 0);
		if ($entry >= HiveEngineTransfer::MIN_AMOUNT) {
			$apply['season_entry_pizza'] = sprintf('%.3f', $entry);
		}

		$account = strtolower(trim((string) ($posted['season_wallet_account'] ?? '')));
		if ($account === '' || HiveUtil::isAccountValid($account)) {
			$apply['season_wallet_account'] = $account;
		}

		$postedKey = trim((string) ($posted[self::KEY_FIELD] ?? ''));
		if ($postedKey !== '') {
			$apply[self::KEY_FIELD] = ConfigSecret::seal($postedKey);
		}

		$blogAccount = strtolower(trim((string) ($posted['season_blog_account'] ?? '')));
		if ($blogAccount === '' || HiveUtil::isAccountValid($blogAccount)) {
			$apply['season_blog_account'] = $blogAccount;
		}

		$postedBlogKey = trim((string) ($posted[self::BLOG_KEY_FIELD] ?? ''));
		if ($postedBlogKey !== '') {
			$apply[self::BLOG_KEY_FIELD] = ConfigSecret::seal($postedBlogKey);
		}

		$wasOn = (int) ($stored['season_mode'] ?? 0) === 1;
		$nowOn = (int) $apply['season_mode'] === 1;
		if ($nowOn && !$wasOn) {
			$gameSpeed = (int) ($stored['game_speed'] ?? self::SPEED_BASE);
			$fleetSpeed = (int) ($stored['fleet_speed'] ?? self::SPEED_BASE);
			$resource = (int) ($stored['resource_multiplier'] ?? 1);
			if ($gameSpeed === self::SPEED_BASE) {
				$apply['game_speed'] = self::SPEED_BASE * self::SPEED_FACTOR;
			}
			if ($fleetSpeed === self::SPEED_BASE) {
				$apply['fleet_speed'] = self::SPEED_BASE * self::SPEED_FACTOR;
			}
			if ($resource === 1) {
				$apply['resource_multiplier'] = self::SPEED_FACTOR;
			}
		}

		$logOld = $stored;
		$logNew = array_merge($stored, $apply);
		foreach ([self::KEY_FIELD, self::BLOG_KEY_FIELD] as $secretField) {
			$logOld[$secretField] = self::redact((string) ($stored[$secretField] ?? ''));
			$logNew[$secretField] = isset($apply[$secretField])
				? self::REDACTED
				: self::redact((string) ($stored[$secretField] ?? ''));
		}

		$template = [
			'season_mode'                => (int) ($apply['season_mode'] ?? $stored['season_mode'] ?? 0),
			'season_length_seconds'      => (int) ($apply['season_length_seconds'] ?? $stored['season_length_seconds'] ?? 604800),
			'season_preclose_seconds'    => (int) ($apply['season_preclose_seconds'] ?? $stored['season_preclose_seconds'] ?? 14400),
			'season_house_cut_percent'   => (string) ($apply['season_house_cut_percent'] ?? $stored['season_house_cut_percent'] ?? '10.00'),
			'season_min_points'          => (int) ($apply['season_min_points'] ?? $stored['season_min_points'] ?? 0),
			'season_entry_pizza'         => (string) ($apply['season_entry_pizza'] ?? $stored['season_entry_pizza'] ?? '0.100'),
			'season_wallet_account'      => (string) ($apply['season_wallet_account'] ?? $stored['season_wallet_account'] ?? ''),
			'season_wallet_active_key'   => '',
			'season_blog_account'        => (string) ($apply['season_blog_account'] ?? $stored['season_blog_account'] ?? ''),
			'season_blog_posting_key'    => '',
			'season_status'              => (string) ($stored['season_status'] ?? 'idle'),
			'season_id'                  => (int) ($stored['season_id'] ?? 0),
		];

		return [
			'apply'    => $apply,
			'log_old'  => $logOld,
			'log_new'  => $logNew,
			'template' => $template,
		];
	}

	public static function redact(string $value): string
	{
		return $value === '' ? '' : self::REDACTED;
	}
}
