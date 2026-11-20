<?php

namespace HiveNova\Core;

/**
 * Persists which release notes a player has already seen.
 */
class ReleaseSeenRepository
{
	public function __construct(
		private readonly ?DatabaseInterface $db = null,
	) {}

	private function db(): DatabaseInterface
	{
		return $this->db ?? Database::get();
	}

	/**
	 * Writes last_seen_release only when the stored value would change.
	 *
	 * @return bool True when an UPDATE was issued
	 */
	public function markSeen(int $userId, ?string $version, ?string $previousVersion): bool
	{
		if ($version === null || $version === '') {
			return false;
		}

		if ($previousVersion === $version) {
			return false;
		}

		$this->db()->update(
			'UPDATE %%USERS%% SET last_seen_release = :v WHERE id = :id;',
			[
				':v'  => $version,
				':id' => $userId,
			]
		);

		return true;
	}
}
