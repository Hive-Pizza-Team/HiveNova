<?php

namespace HiveNova\Core;

/**
 * Binds one Hive username to one Uni3 seat for a season.
 *
 * Uniqueness is per (universe, season_id, hive_account). The users.hive_account
 * column stays unique per universe as well, so a Hive name cannot sit on two
 * live accounts in that universe at once.
 */
class Uni3HiveLinkService
{
	public function __construct(
		private readonly Uni3ClaimStore $store,
		private readonly Uni3ClaimGate $gate = new Uni3ClaimGate(),
	) {
	}

	/**
	 * @return array{ok: bool, reason: string}
	 */
	public function bind(int $universe, int $seasonId, int $userId, string $hive, string $origin, int $now): array
	{
		$hive = strtolower(trim($hive));
		if ($userId < 1 || !HiveUtil::isAccountValid($hive)) {
			return ['ok' => false, 'reason' => 'invalid'];
		}
		if ($origin !== Uni3ClaimGate::ORIGIN_EMAIL && $origin !== Uni3ClaimGate::ORIGIN_KEYCHAIN) {
			$origin = Uni3ClaimGate::ORIGIN_EMAIL;
		}

		$owner = $this->store->hiveOwnerId($universe, $hive);
		if ($owner !== null && $owner !== $userId) {
			$this->reject($universe, $seasonId, $userId, $hive);
			return ['ok' => false, 'reason' => 'hive_taken'];
		}

		$boundUserId = null;
		if ($seasonId >= 1) {
			$seat = $this->store->findHiveLinkByAccount($universe, $seasonId, $hive);
			$boundUserId = $seat !== null ? (int) ($seat['user_id'] ?? 0) : null;
			if ($boundUserId === 0) {
				$boundUserId = null;
			}
			$decision = $this->gate->evaluateLink($userId, $boundUserId);
			if (!$decision['ok']) {
				$this->reject($universe, $seasonId, $userId, $hive);
				return $decision;
			}
			if ($boundUserId !== $userId) {
				$inserted = $this->store->insertHiveLink([
					'universe'     => $universe,
					'season_id'    => $seasonId,
					'user_id'      => $userId,
					'hive_account' => $hive,
					'origin'       => $origin,
					'linked_at'    => $now,
				]);
				if (!$inserted) {
					$this->reject($universe, $seasonId, $userId, $hive);
					return ['ok' => false, 'reason' => 'hive_taken'];
				}
			}
		}

		if ($owner !== $userId) {
			$this->store->setUserHiveAccount($universe, $userId, $hive);
		}

		Uni3PilotLog::record(Uni3PilotLog::HIVE_LINK, $universe, $seasonId, $userId, $origin . ':' . $hive);

		return ['ok' => true, 'reason' => $boundUserId === $userId ? 'already' : ''];
	}

	private function reject(int $universe, int $seasonId, int $userId, string $hive): void
	{
		Uni3PilotLog::record(Uni3PilotLog::HIVE_LINK_REJECTED, $universe, $seasonId, $userId, $hive);
	}
}
