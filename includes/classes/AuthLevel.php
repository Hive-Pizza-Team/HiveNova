<?php

namespace HiveNova\Core;

class AuthLevel
{
	public static function isStaff(int $authlevel): bool
	{
		return $authlevel >= self::staffMinAuthlevel();
	}

	/** Lowest authlevel shown as staff (Active Admins, Administration link). Promoters are below this. */
	public static function staffMinAuthlevel(): int
	{
		return AUTH_MOD;
	}

	public static function canEnterAdmin(int $authlevel): bool
	{
		return self::isStaff($authlevel);
	}

	public static function canViewReferralDashboard(int $authlevel): bool
	{
		return $authlevel === AUTH_PROMO || $authlevel === AUTH_ADM;
	}

	/**
	 * @param array<string, string>|\ArrayAccess $lng
	 * @return array<int, string>
	 */
	public static function rankLabels(array|\ArrayAccess $lng): array
	{
		$labels = [];
		for ($level = AUTH_USR; $level <= AUTH_ADM; $level++) {
			$labels[$level] = (string) ($lng['rank_' . $level] ?? '');
		}

		return $labels;
	}
}
