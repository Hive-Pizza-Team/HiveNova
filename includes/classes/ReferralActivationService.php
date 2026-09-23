<?php

namespace HiveNova\Core;

/**
 * Copies a captured referral onto a new account at activation.
 *
 * Email verification and skip-verify signup (user_valid = 0) both create the
 * player in ShowVertifyPage. ReferralCronjob only pays when ref_bonus = 1
 * and ref_id points at the referrer, so both paths must write those columns.
 */
class ReferralActivationService
{
	/**
	 * @param array<string, mixed> $pending users_valid row
	 */
	public function attachFromPending(DatabaseInterface $db, int $userId, array $pending): bool
	{
		return $this->attach($db, $userId, (int) ($pending['referralID'] ?? 0));
	}

	public function attach(DatabaseInterface $db, int $userId, int $referralId): bool
	{
		if ($userId <= 0 || $referralId <= 0) {
			return false;
		}

		$db->update(
			'UPDATE %%USERS%% SET `ref_id` = :referralId, `ref_bonus` = 1 WHERE `id` = :userId;',
			[
				':referralId' => $referralId,
				':userId'     => $userId,
			]
		);

		return true;
	}
}
