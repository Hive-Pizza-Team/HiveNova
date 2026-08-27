<?php

namespace HiveNova\Core;

/**
 * Write-only active key + validation for the server-wide inactive-memo settings.
 */
class InactiveHiveMemoAdminConfig
{
	public const KEY_FIELD = 'hive_inactive_memo_active_key';
	public const REDACTED = 'CHANGED';
	public const MIN_AMOUNT = HiveTransfer::MIN_AMOUNT;

	/**
	 * @param array<string, mixed> $stored
	 * @param array<string, mixed> $posted
	 * @return array{apply: array<string, mixed>, log_old: array<string, mixed>, log_new: array<string, mixed>, template: array<string, mixed>}
	 */
	public static function applyPosted(array $stored, array $posted): array
	{
		$apply = [];

		$apply['hive_inactive_memo_active'] = !empty($posted['hive_inactive_memo_active']) ? 1 : 0;

		$account = strtolower(trim((string) ($posted['hive_inactive_memo_account'] ?? '')));
		if ($account === '' || HiveUtil::isAccountValid($account)) {
			$apply['hive_inactive_memo_account'] = $account;
		}

		$asset = strtoupper(trim((string) ($posted['hive_inactive_memo_asset'] ?? 'HIVE')));
		if ($asset === 'HIVE' || $asset === 'HBD') {
			$apply['hive_inactive_memo_asset'] = $asset;
		}

		$amount = (float) ($posted['hive_inactive_memo_amount'] ?? 0);
		if ($amount >= self::MIN_AMOUNT) {
			$apply['hive_inactive_memo_amount'] = sprintf('%.3f', $amount);
		}

		$postedKey = trim((string) ($posted[self::KEY_FIELD] ?? ''));
		if ($postedKey !== '') {
			$apply[self::KEY_FIELD] = ConfigSecret::seal($postedKey);
		}

		$logOld = $stored;
		$logNew = array_merge($stored, $apply);
		$logOld[self::KEY_FIELD] = self::redact((string) ($stored[self::KEY_FIELD] ?? ''));
		$logNew[self::KEY_FIELD] = isset($apply[self::KEY_FIELD]) ? self::REDACTED : self::redact((string) ($stored[self::KEY_FIELD] ?? ''));

		$template = [
			'hive_inactive_memo_active'  => (int) ($apply['hive_inactive_memo_active'] ?? $stored['hive_inactive_memo_active'] ?? 0),
			'hive_inactive_memo_account' => (string) ($apply['hive_inactive_memo_account'] ?? $stored['hive_inactive_memo_account'] ?? ''),
			'hive_inactive_memo_asset'   => (string) ($apply['hive_inactive_memo_asset'] ?? $stored['hive_inactive_memo_asset'] ?? 'HIVE'),
			'hive_inactive_memo_amount'  => (string) ($apply['hive_inactive_memo_amount'] ?? $stored['hive_inactive_memo_amount'] ?? '0.003'),
			'hive_inactive_memo_active_key' => '',
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
