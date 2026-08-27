<?php

namespace HiveNova\Core;

/**
 * Write-only memo key + enable flag for encrypted social Hive memos.
 */
class SocialHiveMemoAdminConfig
{
	public const KEY_FIELD = 'hive_social_memo_memo_key';
	public const REDACTED = 'CHANGED';

	/**
	 * @param array<string, mixed> $stored
	 * @param array<string, mixed> $posted
	 * @return array{apply: array<string, mixed>, log_old: array<string, mixed>, log_new: array<string, mixed>, template: array<string, mixed>}
	 */
	public static function applyPosted(array $stored, array $posted): array
	{
		$apply = [];
		$apply['hive_social_memo_active'] = !empty($posted['hive_social_memo_active']) ? 1 : 0;

		$postedKey = trim((string) ($posted[self::KEY_FIELD] ?? ''));
		if ($postedKey !== '') {
			$apply[self::KEY_FIELD] = ConfigSecret::seal($postedKey);
		}

		$logOld = $stored;
		$logNew = array_merge($stored, $apply);
		$logOld[self::KEY_FIELD] = InactiveHiveMemoAdminConfig::redact((string) ($stored[self::KEY_FIELD] ?? ''));
		$logNew[self::KEY_FIELD] = isset($apply[self::KEY_FIELD])
			? self::REDACTED
			: InactiveHiveMemoAdminConfig::redact((string) ($stored[self::KEY_FIELD] ?? ''));

		$template = [
			'hive_social_memo_active'   => (int) ($apply['hive_social_memo_active'] ?? $stored['hive_social_memo_active'] ?? 0),
			'hive_social_memo_memo_key' => '',
		];

		return [
			'apply'    => $apply,
			'log_old'  => $logOld,
			'log_new'  => $logNew,
			'template' => $template,
		];
	}
}
