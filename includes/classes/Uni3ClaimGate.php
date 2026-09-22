<?php

namespace HiveNova\Core;

/**
 * Prize rules for the Uni3 email play + Hive claim-gate pilot.
 *
 * Play is open to email/password accounts. Prize eligibility is:
 * hive linked AND entry paid AND points >= the season minimum.
 *
 * Entry must be paid before the season wipe. Paying after ranks lock would
 * let a player buy a prize only when it beats the fee, and it would resize a
 * pool that Keychain payouts may already have left. The claim window is only
 * for collecting a prize that was reserved at wipe.
 */
class Uni3ClaimGate
{
	public const CLAIM_WINDOW_DAYS = 7;

	public const CLAIM_WINDOW_SECONDS = 604800;

	public const ORIGIN_EMAIL = 'email';

	public const ORIGIN_KEYCHAIN = 'keychain';

	public const PAYOUT_PENDING = 'pending';

	public const PAYOUT_PENDING_CLAIM = 'pending_claim';

	public const PAYOUT_CLAIMING = 'claiming';

	public const PAYOUT_SENT = 'sent';

	public const PAYOUT_FAILED = 'failed';

	public const PAYOUT_FORFEITED = 'forfeited';

	public const MEDAL_PENDING = 'pending_claim';

	public const MEDAL_CLAIMED = 'claimed';

	public const MEDAL_FORFEITED = 'forfeited';

	public const MEDAL_TIER = 'participant';

	/**
	 * Post-wipe buy-in is rejected. See class doc.
	 */
	public function acceptsEntryAfterClose(): bool
	{
		return false;
	}

	public function prizeEligible(bool $hiveLinked, bool $entryPaid, int $points, int $minPoints): bool
	{
		return $hiveLinked && $entryPaid && $points >= $minPoints;
	}

	/**
	 * Mid-season lock: missing Hive or unpaid entry. Points are judged at wipe.
	 */
	public function prizeLocked(bool $hiveLinked, bool $entryPaid): bool
	{
		return !$hiveLinked || !$entryPaid;
	}

	/**
	 * Keychain seats auto-pay. Email seats sit in pending_claim until the player claims.
	 */
	public function payoutStatusForOrigin(string $origin): string
	{
		return $origin === self::ORIGIN_EMAIL ? self::PAYOUT_PENDING_CLAIM : self::PAYOUT_PENDING;
	}

	public function claimWindowOpen(int $closesAt, int $now): bool
	{
		if ($closesAt <= 0 || $now < $closesAt) {
			return false;
		}

		return $now < ($closesAt + self::CLAIM_WINDOW_SECONDS);
	}

	public function claimWindowExpired(int $closesAt, int $now): bool
	{
		return $closesAt > 0 && $now >= ($closesAt + self::CLAIM_WINDOW_SECONDS);
	}

	/**
	 * @return array{ok: bool, reason: string}
	 */
	public function evaluateLink(int $userId, ?int $boundUserId): array
	{
		if ($boundUserId === null || $boundUserId === $userId) {
			return ['ok' => true, 'reason' => $boundUserId === $userId ? 'already' : ''];
		}

		return ['ok' => false, 'reason' => 'hive_taken'];
	}

	/**
	 * Sticky banner: one dismiss hides it for this season. It is not a modal on every click.
	 * A pending claim is not dismissible.
	 *
	 * @return array{show: bool, dismissible: bool, hard: bool, mode: string}
	 */
	public function banner(bool $seasonal, bool $locked, bool $pendingClaim, bool $dismissed): array
	{
		if (!$seasonal) {
			return ['show' => false, 'dismissible' => true, 'hard' => false, 'mode' => ''];
		}
		if ($pendingClaim) {
			return ['show' => true, 'dismissible' => false, 'hard' => true, 'mode' => 'claim'];
		}
		if (!$locked || $dismissed) {
			return ['show' => false, 'dismissible' => true, 'hard' => false, 'mode' => ''];
		}

		return ['show' => true, 'dismissible' => true, 'hard' => false, 'mode' => 'locked'];
	}

	/**
	 * In-game / Discord-ready season medal. No chain, no combat fields.
	 *
	 * @return array<string, int|string|bool>
	 */
	public function medalMetadata(int $universe, int $seasonId, string $hiveUser, string $gameName, int $points, string $status): array
	{
		return [
			'kind'         => 'season_medal',
			'season_id'    => $seasonId,
			'uni'          => $universe,
			'tier'         => self::MEDAL_TIER,
			'hive_user'    => strtolower(trim($hiveUser)),
			'game_name'    => $gameName,
			'points'       => $points,
			'status'       => $status,
			'transferable' => false,
			'chain'        => 'none',
		];
	}
}
